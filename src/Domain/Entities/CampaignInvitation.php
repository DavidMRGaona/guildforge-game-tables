<?php

declare(strict_types=1);

namespace Modules\GameTables\Domain\Entities;

use DateTimeImmutable;
use Modules\GameTables\Domain\ValueObjects\CampaignId;
use Modules\GameTables\Domain\ValueObjects\CampaignInvitationId;

/**
 * An invitation to join a campaign, sent to an email address. Only pending invitations exist: once
 * accepted, rejected, cancelled or expired they are deleted.
 */
final class CampaignInvitation
{
    public function __construct(
        public readonly CampaignInvitationId $id,
        public readonly CampaignId $campaignId,
        public readonly string $email,
        public readonly ?string $invitedBy,
        public readonly string $inviterName,
        public string $tokenHash,
        public readonly DateTimeImmutable $expiresAt,
        public int $sendCount,
        public DateTimeImmutable $lastSentAt,
    ) {
    }

    public function isExpired(DateTimeImmutable $now): bool
    {
        return $now >= $this->expiresAt;
    }

    /**
     * A resend replaces the token and counts as a send; it does not extend the expiry.
     */
    public function resend(string $tokenHash, DateTimeImmutable $now): void
    {
        $this->tokenHash = $tokenHash;
        $this->sendCount++;
        $this->lastSentAt = $now;
    }
}
