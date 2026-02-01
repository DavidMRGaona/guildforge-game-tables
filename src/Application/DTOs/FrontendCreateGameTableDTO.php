<?php

declare(strict_types=1);

namespace Modules\GameTables\Application\DTOs;

use DateTimeImmutable;
use Modules\GameTables\Domain\Enums\CharacterCreation;
use Modules\GameTables\Domain\Enums\ExperienceLevel;
use Modules\GameTables\Domain\Enums\Genre;
use Modules\GameTables\Domain\Enums\SafetyTool;
use Modules\GameTables\Domain\Enums\TableFormat;
use Modules\GameTables\Domain\Enums\TableType;
use Modules\GameTables\Domain\Enums\Tone;

/**
 * Simplified DTO for frontend game table creation.
 * Contains only fields that users can fill in, not admin-only fields.
 */
final readonly class FrontendCreateGameTableDTO
{
    /**
     * @param array<Genre>|null $genres
     * @param array<SafetyTool>|null $safetyTools
     * @param array<string>|null $contentWarningIds
     * @param array<string>|null $customWarnings
     * @param array<int, array<string, mixed>>|null $gameMasters
     */
    public function __construct(
        public string $createdBy,
        public string $gameSystemId,
        public string $title,
        public DateTimeImmutable $startsAt,
        public int $durationMinutes,
        public TableType $tableType,
        public TableFormat $tableFormat,
        public int $minPlayers,
        public int $maxPlayers,
        public int $maxSpectators = 0,
        public ?string $eventId = null,
        public ?string $campaignId = null,
        public ?string $synopsis = null,
        public ?string $location = null,
        public ?string $onlineUrl = null,
        public ?int $minimumAge = null,
        public string $language = 'es',
        public ?array $genres = null,
        public ?Tone $tone = null,
        public ?ExperienceLevel $experienceLevel = null,
        public ?CharacterCreation $characterCreation = null,
        public ?array $safetyTools = null,
        public ?array $contentWarningIds = null,
        public ?array $customWarnings = null,
        public ?array $gameMasters = null,
        public ?string $notes = null,
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            createdBy: $data['created_by'],
            gameSystemId: $data['game_system_id'],
            title: $data['title'],
            startsAt: $data['starts_at'] instanceof DateTimeImmutable
                ? $data['starts_at']
                : new DateTimeImmutable($data['starts_at']),
            durationMinutes: (int) $data['duration_minutes'],
            tableType: $data['table_type'] instanceof TableType
                ? $data['table_type']
                : TableType::from($data['table_type']),
            tableFormat: $data['table_format'] instanceof TableFormat
                ? $data['table_format']
                : TableFormat::from($data['table_format']),
            minPlayers: (int) $data['min_players'],
            maxPlayers: (int) $data['max_players'],
            maxSpectators: (int) ($data['max_spectators'] ?? 0),
            eventId: $data['event_id'] ?? null,
            campaignId: $data['campaign_id'] ?? null,
            synopsis: $data['synopsis'] ?? null,
            location: $data['location'] ?? null,
            onlineUrl: $data['online_url'] ?? null,
            minimumAge: isset($data['minimum_age']) ? (int) $data['minimum_age'] : null,
            language: $data['language'] ?? 'es',
            genres: isset($data['genres'])
                ? array_map(fn ($g) => $g instanceof Genre ? $g : Genre::from($g), $data['genres'])
                : null,
            tone: isset($data['tone'])
                ? ($data['tone'] instanceof Tone ? $data['tone'] : Tone::from($data['tone']))
                : null,
            experienceLevel: isset($data['experience_level'])
                ? ($data['experience_level'] instanceof ExperienceLevel ? $data['experience_level'] : ExperienceLevel::from($data['experience_level']))
                : null,
            characterCreation: isset($data['character_creation'])
                ? ($data['character_creation'] instanceof CharacterCreation ? $data['character_creation'] : CharacterCreation::from($data['character_creation']))
                : null,
            safetyTools: isset($data['safety_tools'])
                ? array_map(fn ($s) => $s instanceof SafetyTool ? $s : SafetyTool::from($s), $data['safety_tools'])
                : null,
            contentWarningIds: $data['content_warning_ids'] ?? null,
            customWarnings: $data['custom_warnings'] ?? null,
            gameMasters: $data['game_masters'] ?? null,
            notes: $data['notes'] ?? null,
        );
    }

    /**
     * Convert to full CreateGameTableDTO for service layer.
     * Sets sensible defaults for admin-only fields.
     *
     * @param DateTimeImmutable|null $registrationClosesAt Default registration close date (e.g., from event)
     */
    public function toCreateGameTableDTO(?DateTimeImmutable $registrationClosesAt = null): CreateGameTableDTO
    {
        return new CreateGameTableDTO(
            gameSystemId: $this->gameSystemId,
            createdBy: $this->createdBy,
            title: $this->title,
            startsAt: $this->startsAt,
            durationMinutes: $this->durationMinutes,
            tableType: $this->tableType,
            tableFormat: $this->tableFormat,
            minPlayers: $this->minPlayers,
            maxPlayers: $this->maxPlayers,
            campaignId: $this->campaignId,
            eventId: $this->eventId,
            maxSpectators: $this->maxSpectators,
            synopsis: $this->synopsis,
            location: $this->location,
            onlineUrl: $this->onlineUrl,
            minimumAge: $this->minimumAge,
            language: $this->language,
            genres: $this->genres,
            tone: $this->tone,
            experienceLevel: $this->experienceLevel,
            characterCreation: $this->characterCreation,
            safetyTools: $this->safetyTools,
            contentWarningIds: $this->contentWarningIds,
            customWarnings: $this->customWarnings,
            registrationClosesAt: $registrationClosesAt,
            notes: $this->notes,
        );
    }
}
