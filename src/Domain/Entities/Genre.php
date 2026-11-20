<?php

declare(strict_types=1);

namespace Modules\GameTables\Domain\Entities;

use Modules\GameTables\Domain\Enums\GenreColor;
use Modules\GameTables\Domain\ValueObjects\GenreId;
use Modules\GameTables\Domain\ValueObjects\GenreKey;

/**
 * A genre of the catalog that tables and campaigns choose from. Its key is what they store,
 * so it is fixed; the name, colour, position and whether it is offered can change.
 */
final class Genre
{
    public function __construct(
        public readonly GenreId $id,
        public readonly GenreKey $key,
        public string $name,
        public GenreColor $color,
        public int $sortOrder,
        public bool $isActive,
    ) {}

    public function rename(string $name): void
    {
        $this->name = $name;
    }

    public function recolour(GenreColor $color): void
    {
        $this->color = $color;
    }

    public function moveTo(int $sortOrder): void
    {
        $this->sortOrder = $sortOrder;
    }

    public function activate(): void
    {
        $this->isActive = true;
    }

    /**
     * No longer offered on forms; the tables and campaigns that have it keep it
     */
    public function deactivate(): void
    {
        $this->isActive = false;
    }
}
