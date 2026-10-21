<?php

declare(strict_types=1);

namespace Modules\GameTables\Infrastructure\Services;

use Carbon\CarbonImmutable;
use DateTimeImmutable;
use Illuminate\Support\Str;
use Modules\GameTables\Application\DTOs\CampaignInvitationViewDTO;
use Modules\GameTables\Application\Services\AccountLookupInterface;
use Modules\GameTables\Application\Services\CampaignDirectionInterface;
use Modules\GameTables\Application\Services\CampaignInvitationSendLogInterface;
use Modules\GameTables\Application\Services\CampaignInvitationServiceInterface;
use Modules\GameTables\Application\Services\CampaignRosterServiceInterface;
use Modules\GameTables\Application\Services\RegistrationLockInterface;
use Modules\GameTables\Domain\Entities\CampaignInvitation;
use Modules\GameTables\Domain\Events\CampaignInvitationAccepted;
use Modules\GameTables\Domain\Events\CampaignInvitationSent;
use Modules\GameTables\Domain\Exceptions\RosterActionRefusedException;
use Modules\GameTables\Domain\Repositories\CampaignInvitationRepositoryInterface;
use Modules\GameTables\Domain\Repositories\CampaignRepositoryInterface;
use Modules\GameTables\Domain\Repositories\CampaignRosterRepositoryInterface;
use Modules\GameTables\Domain\ValueObjects\CampaignId;
use Modules\GameTables\Domain\ValueObjects\CampaignInvitationId;

final readonly class CampaignInvitationService implements CampaignInvitationServiceInterface
{
    private const int EXPIRES_AFTER_DAYS = 14;

    private const int WINDOW_DAYS = 30;

    private const int MAX_SENDS_IN_WINDOW = 3;

    private const int MIN_HOURS_BETWEEN_SENDS = 24;

    public function __construct(
        private CampaignRepositoryInterface $campaigns,
        private CampaignRosterRepositoryInterface $roster,
        private CampaignRosterGuard $guard,
        private CampaignInvitationRepositoryInterface $invitations,
        private CampaignInvitationSendLogInterface $sendLog,
        private CampaignDirectionInterface $direction,
        private AccountLookupInterface $accounts,
        private RegistrationLockInterface $lock,
        private CampaignRosterServiceInterface $rosterService,
    ) {}

    public function invite(string $campaignId, string $email, string $actorUserId): void
    {
        $email = $this->normalise($email);

        $sent = $this->lock->forCampaign($campaignId, function () use ($campaignId, $email, $actorUserId): array {
            $campaign = $this->campaigns->findOrFail(new CampaignId($campaignId));

            $this->guard->ensureCanChange($campaign);
            $this->guard->ensureRoomIn($campaign);

            $account = $this->accounts->findByEmail($email);
            if ($account !== null) {
                if ($this->direction->isDirector($campaignId, $account->userId)) {
                    throw RosterActionRefusedException::because('is_director');
                }

                if ($this->roster->contains($campaign->id, $account->userId)) {
                    throw RosterActionRefusedException::because('account_already_member');
                }
            }

            $now = CarbonImmutable::now()->toDateTimeImmutable();
            $this->ensureWithinLimits($campaignId, $email, $now);

            $token = $this->newToken();
            $pending = $this->invitations->findPending($campaign->id, $email);

            // The daily cleanup may not have run yet: an expired one is replaced, not revived
            if ($pending !== null && $pending->isExpired($now)) {
                $this->invitations->delete($pending->id);
                $pending = null;
            }

            if ($pending !== null) {
                $pending->resend(hash('sha256', $token), $now);
                $invitation = $pending;
            } else {
                $invitation = new CampaignInvitation(
                    id: CampaignInvitationId::generate(),
                    campaignId: $campaign->id,
                    email: $email,
                    invitedBy: $actorUserId,
                    inviterName: $this->nameOf($actorUserId),
                    tokenHash: hash('sha256', $token),
                    expiresAt: $now->modify('+'.self::EXPIRES_AFTER_DAYS.' days'),
                    sendCount: 1,
                    lastSentAt: $now,
                );
            }

            $this->invitations->save($invitation);
            $this->sendLog->recordSend($campaignId, $email, $now);

            return [$invitation, $token];
        });

        $this->announce(...$sent);
    }

    public function resend(string $campaignId, string $invitationId, string $actorUserId): void
    {
        $this->liveInvitation($campaignId, $invitationId);

        $sent = $this->lock->forCampaign($campaignId, function () use ($invitationId, $campaignId): array {
            $invitation = $this->liveInvitation($campaignId, $invitationId);
            $campaign = $this->campaigns->findOrFail(new CampaignId($campaignId));

            $this->guard->ensureCanChange($campaign);
            $this->guard->ensureRoomIn($campaign);

            $now = CarbonImmutable::now()->toDateTimeImmutable();
            $this->ensureWithinLimits($campaignId, $invitation->email, $now);

            $token = $this->newToken();
            $invitation->resend(hash('sha256', $token), $now);
            $this->invitations->save($invitation);
            $this->sendLog->recordSend($campaignId, $invitation->email, $now);

            return [$invitation, $token];
        });

        $this->announce(...$sent);
    }

    public function cancel(string $campaignId, string $invitationId): void
    {
        $this->lock->forCampaign($campaignId, function () use ($campaignId, $invitationId): void {
            $invitation = $this->invitations->find($this->idOf($invitationId));

            if ($invitation === null || $invitation->campaignId->value !== $campaignId) {
                throw RosterActionRefusedException::because('invitation_not_found');
            }

            $this->invitations->delete($invitation->id);
        });
    }

    public function pendingFor(string $campaignId, DateTimeImmutable $now): array
    {
        $campaign = $this->campaigns->findOrFail(new CampaignId($campaignId));

        $views = [];
        foreach ($this->invitations->pendingFor($campaign->id) as $invitation) {
            if ($invitation->isExpired($now)) {
                continue;
            }

            $views[] = $this->viewOf($invitation, $campaign->title, $campaign->slug, $this->sendLimitsAllow($campaignId, $invitation->email, $now));
        }

        return $views;
    }

    public function pendingForEmail(string $email, DateTimeImmutable $now): array
    {
        $views = [];
        foreach ($this->invitations->pendingForEmail($this->normalise($email), $now) as $invitation) {
            $campaign = $this->campaigns->find($invitation->campaignId);
            if ($campaign === null) {
                continue;
            }

            $views[] = $this->viewOf($invitation, $campaign->title, $campaign->slug, false);
        }

        return $views;
    }

    public function viewByToken(string $token, DateTimeImmutable $now): ?CampaignInvitationViewDTO
    {
        $invitation = $this->liveByToken($token, $now);
        if ($invitation === null) {
            return null;
        }

        $campaign = $this->campaigns->findOrFail($invitation->campaignId);

        return $this->viewOf($invitation, $campaign->title, $campaign->slug, false);
    }

    public function accept(string $token, string $userId): void
    {
        $invitation = $this->liveByToken($token, CarbonImmutable::now()->toDateTimeImmutable())
            ?? throw RosterActionRefusedException::because('invitation_not_found');

        $account = $this->accounts->findActiveById($userId)
            ?? throw RosterActionRefusedException::because('invitation_not_found');

        if ($this->normalise($account->email) !== $invitation->email) {
            throw RosterActionRefusedException::because('invitation_other_account');
        }

        $this->join($invitation, $userId);
    }

    public function reject(string $token): void
    {
        $invitation = $this->liveByToken($token, CarbonImmutable::now()->toDateTimeImmutable())
            ?? throw RosterActionRefusedException::because('invitation_not_found');

        $this->decline($invitation);
    }

    public function acceptById(string $invitationId, string $userId): void
    {
        $this->join($this->ownInvitation($invitationId, $userId), $userId);
    }

    public function rejectById(string $invitationId, string $userId): void
    {
        $this->decline($this->ownInvitation($invitationId, $userId));
    }

    /**
     * The roster takes its own campaign lock, so none is held here: a second call that already read the
     * row meets the roster's account_already_member, and one that comes later finds no row.
     *
     * @throws RosterActionRefusedException
     */
    private function join(CampaignInvitation $invitation, string $userId): void
    {
        $campaignId = $invitation->campaignId->value;

        try {
            $this->rosterService->addUser($campaignId, $userId, $invitation->invitedBy ?? $userId);
        } catch (RosterActionRefusedException $e) {
            // A full roster may have room later; these will not change
            if (in_array($e->reason, ['unavailable', 'finished', 'account_already_member'], true)) {
                $this->invitations->delete($invitation->id);
            }

            throw $e;
        }

        $this->invitations->delete($invitation->id);

        CampaignInvitationAccepted::dispatch($campaignId, $userId);
    }

    private function decline(CampaignInvitation $invitation): void
    {
        $this->invitations->delete($invitation->id);
        $this->sendLog->recordRejection($invitation->campaignId->value, $invitation->email, CarbonImmutable::now()->toDateTimeImmutable());
    }

    /**
     * The live invitation addressed to the account's verified email; anything else answers
     * invitation_not_found so nobody learns that another email has one.
     *
     * @throws RosterActionRefusedException
     */
    private function ownInvitation(string $invitationId, string $userId): CampaignInvitation
    {
        $account = $this->accounts->findActiveById($userId)
            ?? throw RosterActionRefusedException::because('invitation_not_found');

        if (! $account->emailVerified) {
            throw RosterActionRefusedException::because('email_not_verified');
        }

        $invitation = $this->invitations->find($this->idOf($invitationId));

        if ($invitation === null
            || $invitation->isExpired(CarbonImmutable::now()->toDateTimeImmutable())
            || $this->normalise($account->email) !== $invitation->email) {
            throw RosterActionRefusedException::because('invitation_not_found');
        }

        return $invitation;
    }

    private function liveByToken(string $token, DateTimeImmutable $now): ?CampaignInvitation
    {
        // Tokens are 64 alphanumerics: anything else cannot exist, so it is not even looked up
        if (preg_match('/\A[A-Za-z0-9]{64}\z/', $token) !== 1) {
            return null;
        }

        $invitation = $this->invitations->findByTokenHash(hash('sha256', $token));

        return $invitation === null || $invitation->isExpired($now) ? null : $invitation;
    }

    private function viewOf(CampaignInvitation $invitation, string $title, string $slug, bool $canResend): CampaignInvitationViewDTO
    {
        return new CampaignInvitationViewDTO(
            id: $invitation->id->value,
            campaignId: $invitation->campaignId->value,
            campaignTitle: $title,
            campaignSlug: $slug,
            inviterName: $invitation->inviterName,
            email: $invitation->email,
            expiresAt: CarbonImmutable::instance($invitation->expiresAt)->toIso8601String(),
            sendCount: $invitation->sendCount,
            canResend: $canResend,
        );
    }

    /**
     * @throws RosterActionRefusedException
     */
    private function ensureWithinLimits(string $campaignId, string $email, DateTimeImmutable $now): void
    {
        $window = $now->modify('-'.self::WINDOW_DAYS.' days');

        if ($this->sendLog->rejectedSince($campaignId, $email, $window)
            || $this->sendLog->sendsSince($campaignId, $email, $window) >= self::MAX_SENDS_IN_WINDOW) {
            throw RosterActionRefusedException::because('invitation_limit');
        }

        if (! $this->enoughTimeSinceLastSend($campaignId, $email, $now)) {
            throw RosterActionRefusedException::because('invitation_too_soon');
        }
    }

    private function sendLimitsAllow(string $campaignId, string $email, DateTimeImmutable $now): bool
    {
        $window = $now->modify('-'.self::WINDOW_DAYS.' days');

        return ! $this->sendLog->rejectedSince($campaignId, $email, $window)
            && $this->sendLog->sendsSince($campaignId, $email, $window) < self::MAX_SENDS_IN_WINDOW
            && $this->enoughTimeSinceLastSend($campaignId, $email, $now);
    }

    private function enoughTimeSinceLastSend(string $campaignId, string $email, DateTimeImmutable $now): bool
    {
        $last = $this->sendLog->lastSendAt($campaignId, $email);

        return $last === null || $last <= $now->modify('-'.self::MIN_HOURS_BETWEEN_SENDS.' hours');
    }

    /**
     * @throws RosterActionRefusedException when it does not exist has expired or belongs to another campaign
     */
    private function liveInvitation(string $campaignId, string $invitationId): CampaignInvitation
    {
        $invitation = $this->invitations->find($this->idOf($invitationId));

        if ($invitation === null || $invitation->campaignId->value !== $campaignId || $invitation->isExpired(CarbonImmutable::now()->toDateTimeImmutable())) {
            throw RosterActionRefusedException::because('invitation_not_found');
        }

        return $invitation;
    }

    private function idOf(string $invitationId): CampaignInvitationId
    {
        // A malformed id would make PostgreSQL reject the query on the uuid column
        if (! Str::isUuid($invitationId)) {
            throw RosterActionRefusedException::because('invitation_not_found');
        }

        return new CampaignInvitationId($invitationId);
    }

    private function announce(CampaignInvitation $invitation, string $token): void
    {
        CampaignInvitationSent::dispatch($invitation->id->value, $invitation->campaignId->value, $invitation->email, $token);
    }

    private function newToken(): string
    {
        return Str::random(64);
    }

    private function nameOf(string $userId): string
    {
        return $this->accounts->findById($userId)->name ?? '';
    }

    private function normalise(string $email): string
    {
        return mb_strtolower(trim($email));
    }
}
