<?php

declare(strict_types=1);

namespace Modules\GameTables\Application\DTOs;

use Modules\GameTables\Domain\Entities\GameSystem;

final readonly class GameSystemDTO
{
    public function __construct(
        public string $id,
        public string $name,
        public string $slug,
        public bool $isActive,
        public ?string $description,
        public ?string $publisher,
        public ?string $edition,
        public ?int $year,
        public ?string $logoUrl,
        public ?string $websiteUrl,
        public ?string $createdAt,
    ) {
    }

    public static function fromEntity(GameSystem $gameSystem): self
    {
        return new self(
            id: $gameSystem->id->value,
            name: $gameSystem->name,
            slug: $gameSystem->slug,
            isActive: $gameSystem->isActive,
            description: $gameSystem->description,
            publisher: $gameSystem->publisher,
            edition: $gameSystem->edition,
            year: $gameSystem->year,
            logoUrl: $gameSystem->logoUrl,
            websiteUrl: $gameSystem->websiteUrl,
            createdAt: $gameSystem->createdAt?->format('c'),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'is_active' => $this->isActive,
            'description' => $this->description,
            'publisher' => $this->publisher,
            'edition' => $this->edition,
            'year' => $this->year,
            'logo_url' => $this->logoUrl,
            'website_url' => $this->websiteUrl,
            'created_at' => $this->createdAt,
        ];
    }
}
