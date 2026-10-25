<?php

declare(strict_types=1);

namespace Modules\GameTables\Domain\Exceptions;

use DomainException;

final class TableFullException extends DomainException
{
    private function __construct(string $message, public readonly string $reason)
    {
        parent::__construct($message);
    }

    public static function forTable(string $tableId): self
    {
        return new self(
            "Game table '{$tableId}' is full and cannot accept more players.",
            'table_full'
        );
    }

    public static function forSpectators(string $tableId): self
    {
        return new self(
            "Game table '{$tableId}' cannot accept more spectators.",
            'spectators_full'
        );
    }
}
