<?php

declare(strict_types=1);

namespace Modules\GameTables\Domain\Exceptions;

use DomainException;

final class NotEditableException extends DomainException
{
    public readonly string $status;

    private function __construct(string $message, string $status)
    {
        parent::__construct($message);
        $this->status = $status;
    }

    public static function forStatus(string $status): self
    {
        return new self(
            "The table cannot be edited in its current status: '{$status}'.",
            $status
        );
    }
}
