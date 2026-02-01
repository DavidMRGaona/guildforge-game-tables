<?php

declare(strict_types=1);

namespace Modules\GameTables\Application\DTOs;

use Modules\GameTables\Domain\Enums\CampaignFrequency;
use Modules\GameTables\Domain\Enums\CharacterCreation;
use Modules\GameTables\Domain\Enums\ExperienceLevel;
use Modules\GameTables\Domain\Enums\Genre;
use Modules\GameTables\Domain\Enums\SafetyTool;
use Modules\GameTables\Domain\Enums\TableFormat;
use Modules\GameTables\Domain\Enums\Tone;

/**
 * Simplified DTO for frontend campaign creation.
 * Contains only fields that users can fill in, not admin-only fields.
 */
final readonly class FrontendCreateCampaignDTO
{
    /**
     * @param array<Genre>|null $genres
     * @param array<SafetyTool>|null $safetyTools
     * @param array<string>|null $contentWarningIds
     * @param array<string>|null $customWarnings
     */
    public function __construct(
        public string $createdBy,
        public string $gameSystemId,
        public string $title,
        public ?string $synopsis,
        public CampaignFrequency $frequency,
        public ?string $scheduleNotes,
        public int $minPlayers,
        public int $maxPlayers,
        public TableFormat $tableFormat,
        public ?string $location = null,
        public ?string $onlineUrl = null,
        public string $language = 'es',
        public ?array $genres = null,
        public ?Tone $tone = null,
        public ?ExperienceLevel $experienceLevel = null,
        public ?CharacterCreation $characterCreation = null,
        public ?array $safetyTools = null,
        public ?array $contentWarningIds = null,
        public ?array $customWarnings = null,
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
            synopsis: $data['synopsis'] ?? null,
            frequency: $data['frequency'] instanceof CampaignFrequency
                ? $data['frequency']
                : CampaignFrequency::from($data['frequency']),
            scheduleNotes: $data['schedule_notes'] ?? null,
            minPlayers: (int) $data['min_players'],
            maxPlayers: (int) $data['max_players'],
            tableFormat: $data['table_format'] instanceof TableFormat
                ? $data['table_format']
                : TableFormat::from($data['table_format']),
            location: $data['location'] ?? null,
            onlineUrl: $data['online_url'] ?? null,
            language: $data['language'] ?? 'es',
            genres: $data['genres'] ?? null,
            tone: isset($data['tone'])
                ? ($data['tone'] instanceof Tone ? $data['tone'] : Tone::from($data['tone']))
                : null,
            experienceLevel: isset($data['experience_level'])
                ? ($data['experience_level'] instanceof ExperienceLevel ? $data['experience_level'] : ExperienceLevel::from($data['experience_level']))
                : null,
            characterCreation: isset($data['character_creation'])
                ? ($data['character_creation'] instanceof CharacterCreation ? $data['character_creation'] : CharacterCreation::from($data['character_creation']))
                : null,
            safetyTools: $data['safety_tools'] ?? null,
            contentWarningIds: $data['content_warning_ids'] ?? null,
            customWarnings: $data['custom_warnings'] ?? null,
        );
    }

    /**
     * Convert to full CreateCampaignDTO for service layer.
     * Sets sensible defaults for admin-only fields.
     */
    public function toCreateCampaignDTO(): CreateCampaignDTO
    {
        return new CreateCampaignDTO(
            gameSystemId: $this->gameSystemId,
            createdBy: $this->createdBy,
            title: $this->title,
            description: $this->synopsis,
            frequency: $this->frequency,
        );
    }
}
