<?php

declare(strict_types=1);

namespace Modules\GameTables\Domain\Exceptions;

use DomainException;

/**
 * The table has ended (completed) or been cancelled: a director can no longer approve its
 * registrations.
 */
final class TableClosedException extends DomainException
{
    public readonly string $reason;

    private function __construct(string $message, string $reason)
    {
        parent::__construct($message);
        $this->reason = $reason;
    }

    public static function forTable(string $gameTableId): self
    {
        return new self(
            "Game table '{$gameTableId}' has ended or been cancelled.",
            'table_closed'
        );
    }
}
