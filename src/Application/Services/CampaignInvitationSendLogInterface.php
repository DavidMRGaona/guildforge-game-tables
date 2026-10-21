<?php

declare(strict_types=1);

namespace Modules\GameTables\Application\Services;

use DateTimeImmutable;

/**
 * Record of invitation sends and rejections that survives the invitation itself, so deleting an
 * invitation does not reset the limits. Addresses are stored only as a per-campaign HMAC.
 */
interface CampaignInvitationSendLogInterface
{
    public function recordSend(string $campaignId, string $email, DateTimeImmutable $at): void;

    public function recordRejection(string $campaignId, string $email, DateTimeImmutable $at): void;

    public function sendsSince(string $campaignId, string $email, DateTimeImmutable $since): int;

    public function lastSendAt(string $campaignId, string $email): ?DateTimeImmutable;

    public function rejectedSince(string $campaignId, string $email, DateTimeImmutable $since): bool;

    /**
     * @return int the number of records deleted
     */
    public function pruneBefore(DateTimeImmutable $before): int;
}
