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
use Modules\GameTables\Domain\Exceptions\RosterActionRefusedException;
use Modules\GameTables\Domain\Repositories\CampaignJoinRequestRepositoryInterface;
use Modules\GameTables\Domain\Repositories\CampaignRepositoryInterface;
use Modules\GameTables\Domain\Repositories\CampaignRosterRepositoryInterface;
use Modules\GameTables\Domain\ValueObjects\CampaignId;
use Modules\GameTables\Domain\ValueObjects\JoinRequestId;

final readonly class CampaignRosterService implements CampaignRosterServiceInterface
{
    public function __construct(
        private CampaignRepositoryInterface $campaigns,
        private CampaignRosterRepositoryInterface $roster,
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

            $this->ensureRosterCanChange($campaign);

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

            $this->ensureRoomIn($campaign);

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

            $this->ensureRosterCanChange($campaign);

            if ($this->direction->isDirector($campaignId, $request->userId)) {
                throw RosterActionRefusedException::because('is_director');
            }

            if ($this->roster->contains($campaign->id, $request->userId)) {
                throw RosterActionRefusedException::because('already_member');
            }

            $this->ensureRoomIn($campaign);

            $now = CarbonImmutable::now();
            $this->roster->add($campaign->id, $request->userId, $now);
            $request->accept($actorUserId, $now);
            $this->requests->save($request);

            return $request;
        });

        // Each session takes its own table lock: never inside the campaign lock
        $sessions = $this->sessions->onMemberJoined($campaignId, $request->userId);

        CampaignJoinRequestAccepted::dispatch($request->id->value, $campaignId, $request->userId, $this->toArray($sessions));
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
        // Unknown, anonymized or deleted: the same answer, so nobody can probe for accounts
        $account = $this->accounts->findByEmail($email)
            ?? throw RosterActionRefusedException::because('account_not_found');

        $this->addUser($campaignId, $account->userId, $actorUserId);
    }

    public function addUser(string $campaignId, string $userId, string $actorUserId): void
    {
        $this->lock->forCampaign($campaignId, function () use ($campaignId, $userId, $actorUserId): void {
            $campaign = $this->campaigns->findOrFail(new CampaignId($campaignId));

            $this->ensureRosterCanChange($campaign);

            if ($this->direction->isDirector($campaignId, $userId)) {
                throw RosterActionRefusedException::because('is_director');
            }

            if ($this->roster->contains($campaign->id, $userId)) {
                throw RosterActionRefusedException::because('account_already_member');
            }

            $this->ensureRoomIn($campaign);

            $now = CarbonImmutable::now();
            $this->roster->add($campaign->id, $userId, $now);

            $pending = $this->requests->findPending($campaign->id, $userId);
            if ($pending !== null) {
                $pending->accept($actorUserId, $now);
                $this->requests->save($pending);
            }
        });

        // Each session takes its own table lock: never inside the campaign lock
        $sessions = $this->sessions->onMemberJoined($campaignId, $userId);

        CampaignRosterMemberAdded::dispatch($campaignId, $userId, $this->toArray($sessions));
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

    private function ensureRosterCanChange(Campaign $campaign): void
    {
        if (! $campaign->isApprovedOrPublished()) {
            throw RosterActionRefusedException::because('unavailable');
        }

        if ($campaign->isFinished()) {
            throw RosterActionRefusedException::because('finished');
        }
    }

    /**
     * Counted under the campaign lock, so two changes never take the last place.
     */
    private function ensureRoomIn(Campaign $campaign): void
    {
        if (! $campaign->hasRoomFor($this->roster->count($campaign->id))) {
            throw RosterActionRefusedException::because('roster_full');
        }
    }

    /**
     * @param  list<EnrolledSessionDTO>  $sessions
     * @return list<array{game_table_id: string, waiting: bool}>
     */
    private function toArray(array $sessions): array
    {
        return array_map(fn (EnrolledSessionDTO $session): array => $session->toArray(), $sessions);
    }
}
