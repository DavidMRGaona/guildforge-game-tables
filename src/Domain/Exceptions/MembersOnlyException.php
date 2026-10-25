<?php

declare(strict_types=1);

namespace Modules\GameTables\Domain\Exceptions;

use DomainException;

final class MembersOnlyException extends DomainException
{
    private function __construct(string $message, public readonly string $reason)
    {
        parent::__construct($message);
    }

    public static function forTable(string $tableId): self
    {
        return new self(
            "Game table '{$tableId}' is only available for members.",
            'members_only'
        );
    }
}
