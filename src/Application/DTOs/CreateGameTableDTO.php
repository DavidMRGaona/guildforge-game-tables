<?php

declare(strict_types=1);

namespace Modules\GameTables\Application\DTOs;

use DateTimeImmutable;
use Modules\GameTables\Domain\Enums\CharacterCreation;
use Modules\GameTables\Domain\Enums\ExperienceLevel;
use Modules\GameTables\Domain\Enums\Genre;
use Modules\GameTables\Domain\Enums\RegistrationType;
use Modules\GameTables\Domain\Enums\SafetyTool;
use Modules\GameTables\Domain\Enums\TableFormat;
use Modules\GameTables\Domain\Enums\TableType;
use Modules\GameTables\Domain\Enums\Tone;

final readonly class CreateGameTableDTO
{
    /**
     * @param array<Genre>|null $genres
     * @param array<SafetyTool>|null $safetyTools
     * @param array<string>|null $contentWarningIds
     * @param array<string>|null $customWarnings
     */
    public function __construct(
        public string $gameSystemId,
        public string $createdBy,
        public string $title,
        public DateTimeImmutable $startsAt,
        public int $durationMinutes,
        public TableType $tableType,
        public TableFormat $tableFormat,
        public int $minPlayers,
        public int $maxPlayers,
        public ?string $campaignId = null,
        public ?string $eventId = null,
        public int $maxSpectators = 0,
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
        public RegistrationType $registrationType = RegistrationType::Everyone,
        public int $membersEarlyAccessDays = 0,
        public ?DateTimeImmutable $registrationOpensAt = null,
        public ?DateTimeImmutable $registrationClosesAt = null,
        public bool $autoConfirm = true,
        public bool $acceptsRegistrationsInProgress = false,
        public ?string $notes = null,
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            gameSystemId: $data['game_system_id'],
            createdBy: $data['created_by'],
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
            campaignId: $data['campaign_id'] ?? null,
            eventId: $data['event_id'] ?? null,
            maxSpectators: (int) ($data['max_spectators'] ?? 0),
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
            registrationType: isset($data['registration_type'])
                ? ($data['registration_type'] instanceof RegistrationType ? $data['registration_type'] : RegistrationType::from($data['registration_type']))
                : RegistrationType::Everyone,
            membersEarlyAccessDays: (int) ($data['members_early_access_days'] ?? 0),
            registrationOpensAt: isset($data['registration_opens_at'])
                ? ($data['registration_opens_at'] instanceof DateTimeImmutable ? $data['registration_opens_at'] : new DateTimeImmutable($data['registration_opens_at']))
                : null,
            registrationClosesAt: isset($data['registration_closes_at'])
                ? ($data['registration_closes_at'] instanceof DateTimeImmutable ? $data['registration_closes_at'] : new DateTimeImmutable($data['registration_closes_at']))
                : null,
            autoConfirm: (bool) ($data['auto_confirm'] ?? true),
            acceptsRegistrationsInProgress: (bool) ($data['accepts_registrations_in_progress'] ?? false),
            notes: $data['notes'] ?? null,
        );
    }
}
