<?php

declare(strict_types=1);

namespace Modules\GameTables\Domain\Exceptions;

use DomainException;

final class NotEditableException extends DomainException
{
    private function __construct(string $message, public readonly string $status)
    {
        parent::__construct($message);
    }

    public static function forStatus(string $status): self
    {
        return new self(
            "The table cannot be edited in its current status: '{$status}'.",
            $status
        );
    }
}
