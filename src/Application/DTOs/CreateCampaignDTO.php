<?php

declare(strict_types=1);

namespace Modules\GameTables\Application\DTOs;

use Modules\GameTables\Domain\Enums\CampaignFrequency;

final readonly class CreateCampaignDTO
{
    public function __construct(
        public string $gameSystemId,
        public string $createdBy,
        public string $title,
        public ?string $description = null,
        public ?CampaignFrequency $frequency = null,
        public ?int $sessionCount = null,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            gameSystemId: $data['game_system_id'],
            createdBy: $data['created_by'],
            title: $data['title'],
            description: $data['description'] ?? null,
            frequency: isset($data['frequency'])
                ? CampaignFrequency::from($data['frequency'])
                : null,
            sessionCount: $data['session_count'] ?? null,
        );
    }
}
