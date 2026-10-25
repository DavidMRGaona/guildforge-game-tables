<?php

declare(strict_types=1);

namespace Modules\GameTables\Domain\Entities;

use DateTimeImmutable;
use Modules\GameTables\Domain\Enums\JoinRequestStatus;
use Modules\GameTables\Domain\Exceptions\RosterActionRefusedException;
use Modules\GameTables\Domain\ValueObjects\CampaignId;
use Modules\GameTables\Domain\ValueObjects\JoinRequestId;

final class CampaignJoinRequest
{
    public function __construct(
        public readonly JoinRequestId $id,
        public readonly CampaignId $campaignId,
        public readonly string $userId,
        public JoinRequestStatus $status,
        public readonly ?string $message,
        public ?string $decisionReason = null,
        public ?string $decidedBy = null,
        public ?DateTimeImmutable $decidedAt = null,
        public readonly ?DateTimeImmutable $createdAt = null,
    ) {}

    public function isPending(): bool
    {
        return $this->status === JoinRequestStatus::Pending;
    }

    public function accept(?string $decidedBy, DateTimeImmutable $now): void
    {
        $this->decide(JoinRequestStatus::Accepted, $decidedBy, null, $now);
    }

    public function reject(string $decidedBy, ?string $reason, DateTimeImmutable $now): void
    {
        $this->decide(JoinRequestStatus::Rejected, $decidedBy, $reason, $now);
    }

    public function withdraw(DateTimeImmutable $now): void
    {
        $this->decide(JoinRequestStatus::Withdrawn, null, null, $now);
    }

    private function decide(JoinRequestStatus $status, ?string $decidedBy, ?string $reason, DateTimeImmutable $now): void
    {
        if (! $this->isPending()) {
            throw RosterActionRefusedException::because('not_pending');
        }

        $this->status = $status;
        $this->decidedBy = $decidedBy;
        $this->decisionReason = $reason !== null && trim($reason) !== '' ? $reason : null;
        $this->decidedAt = $now;
    }
}
