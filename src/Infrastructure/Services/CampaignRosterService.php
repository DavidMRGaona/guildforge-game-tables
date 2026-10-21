<?php

declare(strict_types=1);

namespace Modules\GameTables\Infrastructure\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Modules\GameTables\Application\DTOs\EnrolledSessionDTO;
use Modules\GameTables\Application\Services\AccountLookupInterface;
use Modules\GameTables\Application\Services\CampaignDirectionInterface;
use Modules\GameTables\Application\Services\CampaignRosterServiceInterface;
use Modules\GameTables\Application\Services\CampaignRosterSessionSyncInterface;
use Modules\GameTables\Application\Services\MembershipCheckerInterface;
use Modules\GameTables\Application\Services\RegistrationLockInterface;
use Modules\GameTables\Domain\Entities\Campaign;
use Modules\GameTables\Domain\Entities\CampaignJoinRequest;
use Modules\GameTables\Domain\Enums\JoinRequestStatus;
use Modules\GameTables\Domain\Enums\RosterAccess;
use Modules\GameTables\Domain\Events\CampaignJoinRequestAccepted;
use Modules\GameTables\Domain\Events\CampaignJoinRequested;
use Modules\GameTables\Domain\Events\CampaignJoinRequestRejected;
use Modules\GameTables\Domain\Events\CampaignRosterMemberAdded;
use Modules\GameTables\Domain\Events\CampaignRosterMemberLeft;
use Modules\GameTables\Domain\Events\CampaignRosterMemberRemoved;
use Modules\GameTables\Domain\Exceptions\RosterActionRefusedException;
use Modules\GameTables\Domain\Repositories\CampaignJoinRequestRepositoryInterface;
use Modules\GameTables\Domain\Repositories\CampaignRepositoryInterface;
use Modules\GameTables\Domain\Repositories\CampaignRosterRepositoryInterface;
use Modules\GameTables\Domain\ValueObjects\CampaignId;
use Modules\GameTables\Domain\ValueObjects\JoinRequestId;
use Throwable;

final readonly class CampaignRosterService implements CampaignRosterServiceInterface
{
    public function __construct(
        private CampaignRepositoryInterface $campaigns,
        private CampaignRosterRepositoryInterface $roster,
        private CampaignRosterGuard $guard,
        private CampaignJoinRequestRepositoryInterface $requests,
        private CampaignDirectionInterface $direction,
        private MembershipCheckerInterface $membership,
        private AccountLookupInterface $accounts,
        private RegistrationLockInterface $lock,
        private CampaignRosterSessionSyncInterface $sessions,
    ) {}

    public function requestToJoin(string $campaignId, string $userId, ?string $message): void
    {
        $message = $message !== null && trim($message) !== '' ? trim($message) : null;

        $request = $this->lock->forCampaign($campaignId, function () use ($campaignId, $userId, $message): CampaignJoinRequest {
            $campaign = $this->campaigns->findOrFail(new CampaignId($campaignId));

            $this->guard->ensureCanChange($campaign);

            if ($this->direction->isDirector($campaignId, $userId)) {
                throw RosterActionRefusedException::because('is_director');
            }

            if ($this->roster->contains($campaign->id, $userId)) {
                throw RosterActionRefusedException::because('already_member');
            }

            if ($this->requests->findPending($campaign->id, $userId) !== null) {
                throw RosterActionRefusedException::because('request_pending');
            }

            if (! $campaign->canAcceptNewPlayers()) {
                throw RosterActionRefusedException::because('not_accepting');
            }

            $this->guard->ensureRoomIn($campaign);

            if ($campaign->rosterAccess === RosterAccess::MembersOnly && ! $this->membership->isActiveMember($userId)) {
                throw RosterActionRefusedException::because('members_only');
            }

            $request = new CampaignJoinRequest(
                id: JoinRequestId::generate(),
                campaignId: $campaign->id,
                userId: $userId,
                status: JoinRequestStatus::Pending,
                message: $message,
            );
            $this->requests->save($request);

            return $request;
        });

        CampaignJoinRequested::dispatch($request->id->value, $campaignId, $userId);
    }

    public function withdrawRequest(string $campaignId, string $userId): void
    {
        $this->lock->forCampaign($campaignId, function () use ($campaignId, $userId): void {
            $campaign = $this->campaigns->findOrFail(new CampaignId($campaignId));
            $request = $this->requests->findPending($campaign->id, $userId)
                ?? throw RosterActionRefusedException::because('not_pending');

            $request->withdraw(CarbonImmutable::now());
            $this->requests->save($request);
        });
    }

    public function acceptRequest(string $requestId, string $actorUserId): void
    {
        $campaignId = $this->campaignOfRequest($requestId);

        $request = $this->lock->forCampaign($campaignId, function () use ($requestId, $campaignId, $actorUserId): CampaignJoinRequest {
            $request = $this->pendingRequest($requestId);
            $campaign = $this->campaigns->findOrFail(new CampaignId($campaignId));

            $this->guard->ensureCanChange($campaign);

            if ($this->direction->isDirector($campaignId, $request->userId)) {
                throw RosterActionRefusedException::because('is_director');
            }

            if ($this->roster->contains($campaign->id, $request->userId)) {
                throw RosterActionRefusedException::because('already_member');
            }

            $this->guard->ensureRoomIn($campaign);

            $now = CarbonImmutable::now();
            $this->roster->add($campaign->id, $request->userId, $now);
            $request->accept($actorUserId, $now);
            $this->requests->save($request);

            return $request;
        });

        CampaignJoinRequestAccepted::dispatch($request->id->value, $campaignId, $request->userId, $this->enrolInSessions($campaignId, $request->userId));
    }

    public function rejectRequest(string $requestId, string $actorUserId, ?string $reason): void
    {
        $campaignId = $this->campaignOfRequest($requestId);
        $reason = $reason !== null && trim($reason) !== '' ? trim($reason) : null;

        $request = $this->lock->forCampaign($campaignId, function () use ($requestId, $actorUserId, $reason): CampaignJoinRequest {
            $request = $this->pendingRequest($requestId);

            $request->reject($actorUserId, $reason, CarbonImmutable::now());
            $this->requests->save($request);

            return $request;
        });

        CampaignJoinRequestRejected::dispatch($request->id->value, $campaignId, $request->userId, $request->decisionReason);
    }

    public function addByEmail(string $campaignId, string $email, string $actorUserId): void
    {
        // The campaign's own refusals first: answered after the lookup, a real account would get
        // «completa» or «terminada» where an unknown email gets the generic message. addUser()
        // checks them again under the lock
        $campaign = $this->campaigns->findOrFail(new CampaignId($campaignId));
        $this->guard->ensureCanChange($campaign);
        $this->guard->ensureRoomIn($campaign);

        // Unknown, anonymized or deleted: the same answer, so nobody can probe for accounts
        $account = $this->accounts->findByEmail($email)
            ?? throw RosterActionRefusedException::because('account_not_found');

        $this->addUser($campaignId, $account->userId, $actorUserId);
    }

    public function addUser(string $campaignId, string $userId, string $actorUserId): void
    {
        $this->lock->forCampaign($campaignId, function () use ($campaignId, $userId, $actorUserId): void {
            $campaign = $this->campaigns->findOrFail(new CampaignId($campaignId));

            $this->guard->ensureCanChange($campaign);

            // Same answer as an unknown email: an anonymized account would be a dead roster row
            if ($this->accounts->findActiveById($userId) === null) {
                throw RosterActionRefusedException::because('account_not_found');
            }

            if ($this->direction->isDirector($campaignId, $userId)) {
                throw RosterActionRefusedException::because('is_director');
            }

            if ($this->roster->contains($campaign->id, $userId)) {
                throw RosterActionRefusedException::because('account_already_member');
            }

            $this->guard->ensureRoomIn($campaign);

            $now = CarbonImmutable::now();
            $this->roster->add($campaign->id, $userId, $now);

            $pending = $this->requests->findPending($campaign->id, $userId);
            if ($pending !== null) {
                $pending->accept($actorUserId, $now);
                $this->requests->save($pending);
            }
        });

        CampaignRosterMemberAdded::dispatch($campaignId, $userId, $this->enrolInSessions($campaignId, $userId));
    }

    public function removeMember(string $campaignId, string $userId, string $actorUserId): void
    {
        $this->removeFromRoster($campaignId, $userId);

        CampaignRosterMemberRemoved::dispatch($campaignId, $userId);
    }

    public function leave(string $campaignId, string $userId): void
    {
        $this->removeFromRoster($campaignId, $userId);

        CampaignRosterMemberLeft::dispatch($campaignId, $userId);
    }

    public function updateSettings(string $campaignId, bool $acceptsNewPlayers, RosterAccess $access, ?int $maxPlayers): void
    {
        $this->lock->forCampaign($campaignId, function () use ($campaignId, $acceptsNewPlayers, $access, $maxPlayers): void {
            $campaign = $this->campaigns->findOrFail(new CampaignId($campaignId));

            $this->guard->ensureCanChange($campaign);

            // Counted under the lock, so a join meanwhile never leaves the roster above the maximum
            $campaign->changeRosterSettings($acceptsNewPlayers, $access, $maxPlayers, $this->roster->count($campaign->id));
            $this->campaigns->save($campaign);
        });
    }

    /**
     * Allowed whatever the campaign's state, finished included. The sessions are released once the
     * campaign lock is: the member is already out of the roster, so a failure there is reported
     * instead of failing an action that succeeded.
     */
    private function removeFromRoster(string $campaignId, string $userId): void
    {
        $this->lock->forCampaign($campaignId, function () use ($campaignId, $userId): void {
            $campaign = $this->campaigns->findOrFail(new CampaignId($campaignId));

            if (! $this->roster->contains($campaign->id, $userId)) {
                throw RosterActionRefusedException::because('not_member');
            }

            $this->roster->remove($campaign->id, $userId);
        });

        try {
            $this->sessions->onMemberLeft($campaignId, $userId);
        } catch (Throwable $e) {
            report($e);
        }
    }

    /**
     * Runs once the campaign lock is released: each session takes its own table lock. The member
     * is already in the roster, so a failure here is reported and the join still announced (with
     * no sessions) instead of failing an action that succeeded.
     *
     * @return list<array{game_table_id: string, waiting: bool}>
     */
    private function enrolInSessions(string $campaignId, string $userId): array
    {
        try {
            $sessions = $this->sessions->onMemberJoined($campaignId, $userId);
        } catch (Throwable $e) {
            report($e);

            return [];
        }

        return array_map(fn (EnrolledSessionDTO $session): array => $session->toArray(), $sessions);
    }

    /**
     * Read before taking the lock, to know which campaign to lock; read again under it.
     */
    private function campaignOfRequest(string $requestId): string
    {
        $request = Str::isUuid($requestId) ? $this->requests->find(new JoinRequestId($requestId)) : null;

        if ($request === null) {
            throw RosterActionRefusedException::because('not_pending');
        }

        return $request->campaignId->value;
    }

    private function pendingRequest(string $requestId): CampaignJoinRequest
    {
        $request = $this->requests->find(new JoinRequestId($requestId));

        if ($request === null || ! $request->isPending()) {
            throw RosterActionRefusedException::because('not_pending');
        }

        return $request;
    }
}
