<?php

declare(strict_types=1);

namespace Modules\GameTables\Domain\Entities;

use DateTimeImmutable;
use Modules\GameTables\Domain\Enums\CampaignFrequency;
use Modules\GameTables\Domain\Enums\CampaignStatus;
use Modules\GameTables\Domain\ValueObjects\CampaignId;
use Modules\GameTables\Domain\ValueObjects\GameSystemId;

final class Campaign
{
    public function __construct(
        public readonly CampaignId $id,
        public readonly GameSystemId $gameSystemId,
        public readonly string $createdBy,
        public string $title,
        public string $slug,
        public CampaignStatus $status,
        public ?string $description = null,
        public ?CampaignFrequency $frequency = null,
        public ?int $sessionCount = null,
        public int $currentSession = 0,
        public bool $acceptsNewPlayers = true,
        public ?int $maxPlayers = null,
        public ?string $imagePublicId = null,
        public bool $isPublished = false,
        public ?DateTimeImmutable $createdAt = null,
        public ?DateTimeImmutable $updatedAt = null,
    ) {
    }

    public function updateInfo(
        string $title,
        ?string $description = null,
        ?CampaignFrequency $frequency = null,
        ?int $sessionCount = null,
    ): void {
        $this->title = $title;
        $this->description = $description;
        $this->frequency = $frequency;
        $this->sessionCount = $sessionCount;
    }

    public function changeStatus(CampaignStatus $status): void
    {
        $this->status = $status;
    }

    public function start(): void
    {
        $this->status = CampaignStatus::Active;
    }

    public function putOnHold(): void
    {
        $this->status = CampaignStatus::OnHold;
    }

    public function resume(): void
    {
        $this->status = CampaignStatus::Active;
    }

    public function complete(): void
    {
        $this->status = CampaignStatus::Completed;
    }

    public function cancel(): void
    {
        $this->status = CampaignStatus::Cancelled;
    }

    public function advanceSession(): void
    {
        $this->currentSession++;
    }

    public function openRecruitment(): void
    {
        $this->acceptsNewPlayers = true;
    }

    public function closeRecruitment(): void
    {
        $this->acceptsNewPlayers = false;
    }

    public function isActive(): bool
    {
        return $this->status->isActive();
    }

    public function isRecruiting(): bool
    {
        return $this->status === CampaignStatus::Recruiting;
    }

    public function canAcceptNewPlayers(): bool
    {
        return $this->acceptsNewPlayers && $this->status->acceptsNewPlayers();
    }

    public function publish(): void
    {
        $this->isPublished = true;
    }

    public function unpublish(): void
    {
        $this->isPublished = false;
    }

    public function progressPercentage(): ?float
    {
        if ($this->sessionCount === null || $this->sessionCount === 0) {
            return null;
        }

        return ($this->currentSession / $this->sessionCount) * 100;
    }
}
