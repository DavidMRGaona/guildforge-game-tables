<?php

declare(strict_types=1);

namespace Modules\GameTables\Domain\Exceptions;

use DomainException;

final class GameTableNotFoundException extends DomainException
{
    public static function withId(string $id): self
    {
        return new self("Game table with ID '{$id}' not found.");
    }
}
