<?php

declare(strict_types=1);

namespace Modules\GameTables\Application\DTOs;

use DateTimeInterface;
use Modules\GameTables\Domain\Entities\GameTable;
use Modules\GameTables\Domain\Enums\CharacterCreation;
use Modules\GameTables\Domain\Enums\ExperienceLevel;
use Modules\GameTables\Domain\Enums\Genre;
use Modules\GameTables\Domain\Enums\RegistrationType;
use Modules\GameTables\Domain\Enums\SafetyTool;
use Modules\GameTables\Domain\Enums\TableFormat;
use Modules\GameTables\Domain\Enums\TableStatus;
use Modules\GameTables\Domain\Enums\TableType;
use Modules\GameTables\Domain\Enums\Tone;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameMasterModel;

final readonly class GameTableResponseDTO
{
    /**
     * @param array<Genre> $genres
     * @param array<SafetyTool> $safetyTools
     * @param array<string> $contentWarnings
     * @param array<string> $customWarnings
     * @param array<ParticipantResponseDTO> $participants
     * @param array<GameMasterResponseDTO> $gameMasters
     */
    public function __construct(
        public string $id,
        public string $title,
        public ?string $slug,
        public ?string $synopsis,
        public string $gameSystemId,
        public string $gameSystemName,
        public ?string $campaignId,
        public ?string $campaignTitle,
        public ?string $eventId,
        public ?string $eventTitle,
        public string $createdBy,
        public string $creatorName,
        public TableType $tableType,
        public TableFormat $tableFormat,
        public TableStatus $status,
        public ?DateTimeInterface $startsAt,
        public int $durationMinutes,
        public ?string $location,
        public ?string $onlineUrl,
        public int $minPlayers,
        public int $maxPlayers,
        public int $maxSpectators,
        public ?int $minimumAge,
        public string $language,
        public ?ExperienceLevel $experienceLevel,
        public ?CharacterCreation $characterCreation,
        public array $genres,
        public ?Tone $tone,
        public array $safetyTools,
        public array $contentWarnings,
        public array $customWarnings,
        public RegistrationType $registrationType,
        public int $membersEarlyAccessDays,
        public ?DateTimeInterface $registrationOpensAt,
        public ?DateTimeInterface $registrationClosesAt,
        public bool $autoConfirm,
        public bool $acceptsRegistrationsInProgress,
        public bool $isPublished,
        public ?DateTimeInterface $publishedAt,
        public ?string $notes,
        public ?string $imagePublicId,
        public array $participants,
        public array $gameMasters,
        public ?DateTimeInterface $createdAt,
        public ?DateTimeInterface $updatedAt,
    ) {}

    /**
     * @param array<ParticipantResponseDTO> $participants
     * @param array<GameMasterResponseDTO> $gameMasters
     */
    public static function fromEntity(
        GameTable $gameTable,
        string $gameSystemName = '',
        ?string $campaignTitle = null,
        ?string $eventTitle = null,
        string $creatorName = '',
        array $participants = [],
        array $gameMasters = [],
    ): self {
        return new self(
            id: $gameTable->id->value,
            title: $gameTable->title,
            slug: $gameTable->slug,
            synopsis: $gameTable->synopsis,
            gameSystemId: $gameTable->gameSystemId->value,
            gameSystemName: $gameSystemName,
            campaignId: $gameTable->campaignId?->value,
            campaignTitle: $campaignTitle,
            eventId: $gameTable->eventId,
            eventTitle: $eventTitle,
            createdBy: $gameTable->createdBy,
            creatorName: $creatorName,
            tableType: $gameTable->tableType,
            tableFormat: $gameTable->tableFormat,
            status: $gameTable->status,
            startsAt: $gameTable->timeSlot->startsAt,
            durationMinutes: $gameTable->timeSlot->durationMinutes,
            location: $gameTable->location,
            onlineUrl: $gameTable->onlineUrl,
            minPlayers: $gameTable->minPlayers,
            maxPlayers: $gameTable->maxPlayers,
            maxSpectators: $gameTable->maxSpectators,
            minimumAge: $gameTable->minimumAge,
            language: $gameTable->language,
            experienceLevel: $gameTable->experienceLevel,
            characterCreation: $gameTable->characterCreation,
            genres: $gameTable->genres ?? [],
            tone: $gameTable->tone,
            safetyTools: $gameTable->safetyTools ?? [],
            contentWarnings: $gameTable->contentWarningIds ?? [],
            customWarnings: $gameTable->customWarnings ?? [],
            registrationType: $gameTable->registrationType,
            membersEarlyAccessDays: $gameTable->membersEarlyAccessDays,
            registrationOpensAt: $gameTable->registrationOpensAt,
            registrationClosesAt: $gameTable->registrationClosesAt,
            autoConfirm: $gameTable->autoConfirm,
            acceptsRegistrationsInProgress: $gameTable->acceptsRegistrationsInProgress,
            isPublished: $gameTable->isPublished,
            publishedAt: $gameTable->publishedAt,
            notes: $gameTable->notes,
            imagePublicId: $gameTable->imagePublicId,
            participants: $participants,
            gameMasters: $gameMasters,
            createdAt: $gameTable->createdAt,
            updatedAt: $gameTable->updatedAt,
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
            'synopsis' => $this->synopsis,
            'game_system_id' => $this->gameSystemId,
            'game_system_name' => $this->gameSystemName,
            'campaign_id' => $this->campaignId,
            'campaign_title' => $this->campaignTitle,
            'event_id' => $this->eventId,
            'event_title' => $this->eventTitle,
            'created_by' => $this->createdBy,
            'creator_name' => $this->creatorName,
            'table_type' => $this->tableType->value,
            'table_type_label' => $this->tableType->label(),
            'table_format' => $this->tableFormat->value,
            'table_format_label' => $this->tableFormat->label(),
            'table_format_color' => $this->tableFormat->color(),
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'status_color' => $this->status->color(),
            'starts_at' => $this->startsAt?->format('c'),
            'duration_minutes' => $this->durationMinutes,
            'location' => $this->location,
            'online_url' => $this->onlineUrl,
            'min_players' => $this->minPlayers,
            'max_players' => $this->maxPlayers,
            'max_spectators' => $this->maxSpectators,
            'minimum_age' => $this->minimumAge,
            'language' => $this->language,
            'experience_level' => $this->experienceLevel?->value,
            'experience_level_label' => $this->experienceLevel?->label(),
            'character_creation' => $this->characterCreation?->value,
            'character_creation_label' => $this->characterCreation?->label(),
            'genres' => array_map(fn (Genre $g) => ['value' => $g->value, 'label' => $g->label()], $this->genres),
            'tone' => $this->tone?->value,
            'tone_label' => $this->tone?->label(),
            'safety_tools' => array_map(fn (SafetyTool $s) => ['value' => $s->value, 'label' => $s->label()], $this->safetyTools),
            'content_warnings' => $this->contentWarnings,
            'custom_warnings' => $this->customWarnings,
            'registration_type' => $this->registrationType->value,
            'registration_type_label' => $this->registrationType->label(),
            'members_early_access_days' => $this->membersEarlyAccessDays,
            'registration_opens_at' => $this->registrationOpensAt?->format('c'),
            'registration_closes_at' => $this->registrationClosesAt?->format('c'),
            'auto_confirm' => $this->autoConfirm,
            'accepts_registrations_in_progress' => $this->acceptsRegistrationsInProgress,
            'is_published' => $this->isPublished,
            'published_at' => $this->publishedAt?->format('c'),
            'notes' => $this->notes,
            'image_public_id' => $this->imagePublicId,
            'participants' => array_map(fn (ParticipantResponseDTO $p) => $p->toArray(), $this->participants),
            'game_masters' => array_map(fn (GameMasterResponseDTO $gm) => $gm->toArray(), $this->gameMasters),
            'created_at' => $this->createdAt?->format('c'),
            'updated_at' => $this->updatedAt?->format('c'),
        ];
    }
}
