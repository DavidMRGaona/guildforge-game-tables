<?php

declare(strict_types=1);

namespace Modules\GameTables\Application\DTOs;

use Modules\GameTables\Domain\Entities\Genre;

/**
 * A genre as forms, pages and search show it
 */
final readonly class GenreDTO
{
    public function __construct(
        public string $key,
        public string $name,
        public string $color,
        public bool $isActive,
    ) {}

    public static function fromEntity(Genre $genre): self
    {
        return new self(
            key: $genre->key->value,
            name: $genre->name,
            color: $genre->color->value,
            isActive: $genre->isActive,
        );
    }

    /**
     * What a page needs to draw the genre's badge
     *
     * @return array{key: string, label: string, color: string}
     */
    public function toBadge(): array
    {
        return ['key' => $this->key, 'label' => $this->name, 'color' => $this->color];
    }
}
