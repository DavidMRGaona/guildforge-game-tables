<?php

declare(strict_types=1);

namespace Modules\GameTables\Application\DTOs;

use DateTimeInterface;
use Modules\GameTables\Domain\Entities\Campaign;
use Modules\GameTables\Domain\Enums\CampaignFrequency;
use Modules\GameTables\Domain\Enums\CampaignStatus;

final readonly class CampaignResponseDTO
{
    /**
     * @param array<CampaignGameMasterResponseDTO> $gameMasters
     * @param array<GameTableListDTO> $gameTables
     */
    public function __construct(
        public string $id,
        public string $title,
        public ?string $slug,
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
        public bool $acceptsNewPlayers,
        public ?int $sessionCount,
        public int $currentSession,
        public int $totalSessions,
        public ?string $imagePublicId,
        public array $gameMasters,
        public array $gameTables,
        public bool $hasActiveOrUpcomingTables,
        public ?DateTimeInterface $createdAt,
        public ?DateTimeInterface $updatedAt,
    ) {}

    /**
     * @param array<CampaignGameMasterResponseDTO> $gameMasters
     * @param array<GameTableListDTO> $gameTables
     */
    public static function fromEntity(
        Campaign $campaign,
        string $gameSystemName = '',
        string $creatorName = '',
        array $gameMasters = [],
        array $gameTables = [],
        bool $hasActiveOrUpcomingTables = false,
    ): self {
        return new self(
            id: $campaign->id->value,
            title: $campaign->title,
            slug: $campaign->slug,
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
            acceptsNewPlayers: $campaign->acceptsNewPlayers,
            sessionCount: $campaign->sessionCount,
            currentSession: $campaign->currentSession,
            totalSessions: 0,
            imagePublicId: $campaign->imagePublicId,
            gameMasters: $gameMasters,
            gameTables: $gameTables,
            hasActiveOrUpcomingTables: $hasActiveOrUpcomingTables,
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
            'slug' => $this->slug,
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
            'accepts_new_players' => $this->acceptsNewPlayers,
            'session_count' => $this->sessionCount,
            'current_session' => $this->currentSession,
            'total_sessions' => $this->totalSessions,
            'image_public_id' => $this->imagePublicId,
            'game_masters' => array_map(fn (CampaignGameMasterResponseDTO $gm) => $gm->toArray(), $this->gameMasters),
            'game_tables' => array_map(fn (GameTableListDTO $table) => $table->toArray(), $this->gameTables),
            'has_active_or_upcoming_tables' => $this->hasActiveOrUpcomingTables,
            'created_at' => $this->createdAt?->format('c'),
            'updated_at' => $this->updatedAt?->format('c'),
        ];
    }
}
