<?php

declare(strict_types=1);

namespace Modules\GameTables\Domain\Exceptions;

use DomainException;

final class NotEligibleToCreateException extends DomainException
{
    private function __construct(string $message, public readonly string $reason)
    {
        parent::__construct($message);
    }

    public static function withReason(string $reason): self
    {
        return new self(
            "You are not eligible to create tables or campaigns: {$reason}",
            $reason
        );
    }
}
