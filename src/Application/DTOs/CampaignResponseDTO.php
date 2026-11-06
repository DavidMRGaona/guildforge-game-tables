<?php

declare(strict_types=1);

namespace Modules\GameTables\Application\DTOs;

use App\Application\Services\TimezoneServiceInterface;
use DateTimeInterface;
use Modules\GameTables\Application\Services\AssociationRoleNameInterface;
use Modules\GameTables\Domain\Entities\Campaign;
use Modules\GameTables\Domain\Enums\CampaignFrequency;
use Modules\GameTables\Domain\Enums\CampaignStatus;
use Modules\GameTables\Domain\Enums\CharacterCreation;
use Modules\GameTables\Domain\Enums\ExperienceLevel;
use Modules\GameTables\Domain\Enums\FrontendCreationStatus;
use Modules\GameTables\Domain\Enums\Genre;
use Modules\GameTables\Domain\Enums\RosterAccess;
use Modules\GameTables\Domain\Enums\SafetyTool;
use Modules\GameTables\Domain\Enums\TableFormat;
use Modules\GameTables\Domain\Enums\Tone;

final readonly class CampaignResponseDTO
{
    /**
     * @param  array<CampaignGameMasterResponseDTO>  $gameMasters
     * @param  array<GameTableListDTO>  $gameTables
     * @param  array<Genre>  $genres
     * @param  array<SafetyTool>  $safetyTools
     * @param  array<string>  $contentWarningIds
     * @param  array<string>  $contentWarnings  their names, for the campaign page
     * @param  array<string>  $customWarnings
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
        public ?int $minPlayers = null,
        public ?TableFormat $tableFormat = null,
        public ?string $location = null,
        public ?string $onlineUrl = null,
        public ?string $scheduleNotes = null,
        public array $genres = [],
        public ?Tone $tone = null,
        public ?CharacterCreation $characterCreation = null,
        public array $safetyTools = [],
        public array $contentWarningIds = [],
        public array $contentWarnings = [],
        public array $customWarnings = [],
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
            minPlayers: $campaign->minPlayers,
            tableFormat: $campaign->tableFormat,
            location: $campaign->location,
            onlineUrl: $campaign->onlineUrl,
            scheduleNotes: $campaign->scheduleNotes,
            genres: $campaign->genres ?? [],
            tone: $campaign->tone,
            characterCreation: $campaign->characterCreation,
            safetyTools: $campaign->safetyTools ?? [],
            contentWarningIds: $campaign->contentWarningIds ?? [],
            customWarnings: $campaign->customWarnings ?? [],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $timezone = app(TimezoneServiceInterface::class);

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
            'roster_access_label' => $this->rosterAccess->label(app(AssociationRoleNameInterface::class)->displayName()),
            'language' => $this->language,
            'experience_level' => $this->experienceLevel?->value,
            'min_players' => $this->minPlayers,
            'table_format' => $this->tableFormat?->value,
            'location' => $this->location,
            'online_url' => $this->onlineUrl,
            'schedule_notes' => $this->scheduleNotes,
            'genres' => array_map(static fn (Genre $genre): string => $genre->value, $this->genres),
            'tone' => $this->tone?->value,
            'character_creation' => $this->characterCreation?->value,
            'safety_tools' => array_map(static fn (SafetyTool $tool): string => $tool->value, $this->safetyTools),
            'content_warning_ids' => $this->contentWarningIds,
            'custom_warnings' => $this->customWarnings,
            'created_at' => $timezone->iso($this->createdAt),
            'updated_at' => $timezone->iso($this->updatedAt),
        ];
    }
}
