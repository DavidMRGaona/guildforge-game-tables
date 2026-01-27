<?php

declare(strict_types=1);

namespace Modules\GameTables\Domain\Exceptions;

use DomainException;

final class ContentWarningNotFoundException extends DomainException
{
    public static function withId(string $id): self
    {
        return new self("Content warning with ID '{$id}' not found.");
    }

    public static function withName(string $name): self
    {
        return new self("Content warning with name '{$name}' not found.");
    }
}
