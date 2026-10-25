<?php

declare(strict_types=1);

namespace Modules\GameTables\Application\DTOs;

use DateTimeInterface;
use Modules\GameTables\Domain\Entities\Campaign;
use Modules\GameTables\Domain\Enums\CampaignFrequency;
use Modules\GameTables\Domain\Enums\CampaignStatus;
use Modules\GameTables\Domain\Enums\ExperienceLevel;
use Modules\GameTables\Domain\Enums\FrontendCreationStatus;
use Modules\GameTables\Domain\Enums\RosterAccess;

final readonly class CampaignResponseDTO
{
    /**
     * @param  array<CampaignGameMasterResponseDTO>  $gameMasters
     * @param  array<GameTableListDTO>  $gameTables
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
        public ?FrontendCreationStatus $frontendCreationStatus = null,
        public ?string $moderationNotes = null,
        public ?DateTimeInterface $createdAt = null,
        public ?DateTimeInterface $updatedAt = null,
        // Why it was rejected, while the author may still change it (see Campaign::rejectionReason())
        public ?string $rejectionReason = null,
        public int $pendingRequests = 0,
        public bool $directedByViewer = false,
        public bool $acceptsSessions = false,
        public RosterAccess $rosterAccess = RosterAccess::Anyone,
        public ?string $language = null,
        public ?ExperienceLevel $experienceLevel = null,
    ) {}

    /**
     * @param  array<CampaignGameMasterResponseDTO>  $gameMasters
     * @param  array<GameTableListDTO>  $gameTables
     */
    public static function fromEntity(
        Campaign $campaign,
        string $gameSystemName = '',
        string $creatorName = '',
        array $gameMasters = [],
        array $gameTables = [],
        bool $hasActiveOrUpcomingTables = false,
        int $currentPlayers = 0,
        int $pendingRequests = 0,
        bool $directedByViewer = false,
        bool $acceptsSessions = false,
        bool $showModeratorFeedback = true,
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
            currentPlayers: $currentPlayers,
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
            frontendCreationStatus: $campaign->frontendCreationStatus,
            moderationNotes: $showModeratorFeedback ? $campaign->moderationNotes : null,
            createdAt: $campaign->createdAt,
            updatedAt: $campaign->updatedAt,
            rejectionReason: $showModeratorFeedback ? $campaign->rejectionReason() : null,
            pendingRequests: $pendingRequests,
            directedByViewer: $directedByViewer,
            acceptsSessions: $acceptsSessions,
            rosterAccess: $campaign->rosterAccess,
            language: $campaign->language,
            experienceLevel: $campaign->experienceLevel,
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
            'frontend_creation_status' => $this->frontendCreationStatus?->value,
            'frontend_creation_status_label' => $this->frontendCreationStatus?->label(),
            'moderation_notes' => $this->moderationNotes,
            'rejection_reason' => $this->rejectionReason,
            'can_edit' => FrontendCreationStatus::allowsAuthorChanges($this->frontendCreationStatus, $this->isPublished),
            'pending_requests' => $this->pendingRequests,
            'directed_by_viewer' => $this->directedByViewer,
            'can_add_session' => $this->acceptsSessions,
            'roster_access' => $this->rosterAccess->value,
            'roster_access_label' => $this->rosterAccess->label(),
            'language' => $this->language,
            'experience_level' => $this->experienceLevel?->value,
            'created_at' => $this->createdAt?->format('c'),
            'updated_at' => $this->updatedAt?->format('c'),
        ];
    }
}
