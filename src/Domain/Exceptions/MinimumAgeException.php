<?php

declare(strict_types=1);

namespace Modules\GameTables\Domain\Exceptions;

use DomainException;

final class MinimumAgeException extends DomainException
{
    public static function notMet(string $tableId, int $minimumAge): self
    {
        return new self("You must be at least {$minimumAge} years old to register for game table '{$tableId}'.");
    }

    public static function ageNotProvided(string $tableId): self
    {
        return new self("Age verification is required to register for game table '{$tableId}'.");
    }
}
