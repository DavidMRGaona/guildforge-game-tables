<?php

declare(strict_types=1);

namespace Modules\GameTables\Application\DTOs;

use DateTimeInterface;
use Modules\GameTables\Domain\Entities\Campaign;
use Modules\GameTables\Domain\Enums\CampaignFrequency;
use Modules\GameTables\Domain\Enums\CampaignStatus;

final readonly class CampaignResponseDTO
{
    public function __construct(
        public string $id,
        public string $title,
        public ?string $description,
        public string $gameSystemId,
        public string $gameSystemName,
        public string $createdBy,
        public string $creatorName,
        public CampaignStatus $status,
        public ?CampaignFrequency $frequency,
        public ?int $maxPlayers,
        public int $currentPlayers,
        public bool $isPublished,
        public bool $isRecruiting,
        public int $totalSessions,
        public ?DateTimeInterface $createdAt,
        public ?DateTimeInterface $updatedAt,
    ) {}

    public static function fromEntity(Campaign $campaign, string $gameSystemName = '', string $creatorName = ''): self
    {
        return new self(
            id: $campaign->id->value,
            title: $campaign->title,
            description: $campaign->description,
            gameSystemId: $campaign->gameSystemId->value,
            gameSystemName: $gameSystemName,
            createdBy: $campaign->createdBy,
            creatorName: $creatorName,
            status: $campaign->status,
            frequency: $campaign->frequency,
            maxPlayers: $campaign->maxPlayers,
            currentPlayers: 0,
            isPublished: $campaign->isPublished,
            isRecruiting: $campaign->status === CampaignStatus::Recruiting,
            totalSessions: 0,
            createdAt: $campaign->createdAt,
            updatedAt: $campaign->updatedAt,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'game_system_id' => $this->gameSystemId,
            'game_system_name' => $this->gameSystemName,
            'created_by' => $this->createdBy,
            'creator_name' => $this->creatorName,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'status_color' => $this->status->color(),
            'frequency' => $this->frequency?->value,
            'frequency_label' => $this->frequency?->label(),
            'max_players' => $this->maxPlayers,
            'current_players' => $this->currentPlayers,
            'is_published' => $this->isPublished,
            'is_recruiting' => $this->isRecruiting,
            'total_sessions' => $this->totalSessions,
            'created_at' => $this->createdAt?->format('c'),
            'updated_at' => $this->updatedAt?->format('c'),
        ];
    }
}
