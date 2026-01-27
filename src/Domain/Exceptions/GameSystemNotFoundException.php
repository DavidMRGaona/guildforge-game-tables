<?php

declare(strict_types=1);

namespace Modules\GameTables\Domain\Exceptions;

use DomainException;

final class GameSystemNotFoundException extends DomainException
{
    public static function withId(string $id): self
    {
        return new self("Game system with ID '{$id}' not found.");
    }

    public static function withSlug(string $slug): self
    {
        return new self("Game system with slug '{$slug}' not found.");
    }
}
