<?php

declare(strict_types=1);

namespace Modules\GameTables\Domain\Exceptions;

use DomainException;

final class RosterActionRefusedException extends DomainException
{
    private function __construct(string $message, public readonly string $reason)
    {
        parent::__construct($message);
    }

    /**
     * @param  array<string, mixed>  $replace
     */
    public static function because(string $reason, array $replace = []): self
    {
        return new self(__('game-tables::messages.roster.errors.'.$reason, $replace), $reason);
    }
}
