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
use Modules\GameTables\Application\Services\RegistrationLockInterface;
use Modules\GameTables\Domain\Entities\CampaignInvitation;
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

    public function resend(string $invitationId, string $actorUserId): void
    {
        $campaignId = $this->liveInvitation($invitationId)->campaignId->value;

        $sent = $this->lock->forCampaign($campaignId, function () use ($invitationId, $campaignId): array {
            $invitation = $this->liveInvitation($invitationId);
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

    public function cancel(string $invitationId): void
    {
        $invitation = $this->invitations->find($this->idOf($invitationId))
            ?? throw RosterActionRefusedException::because('invitation_not_found');

        $this->lock->forCampaign($invitation->campaignId->value, function () use ($invitation): void {
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

            $views[] = new CampaignInvitationViewDTO(
                id: $invitation->id->value,
                campaignId: $campaignId,
                campaignTitle: $campaign->title,
                campaignSlug: $campaign->slug,
                inviterName: $invitation->inviterName,
                email: $invitation->email,
                expiresAt: CarbonImmutable::instance($invitation->expiresAt)->toIso8601String(),
                sendCount: $invitation->sendCount,
                canResend: $this->sendLimitsAllow($campaignId, $invitation->email, $now),
            );
        }

        return $views;
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
     * @throws RosterActionRefusedException when it does not exist or has expired
     */
    private function liveInvitation(string $invitationId): CampaignInvitation
    {
        $invitation = $this->invitations->find($this->idOf($invitationId));

        if ($invitation === null || $invitation->isExpired(CarbonImmutable::now()->toDateTimeImmutable())) {
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
