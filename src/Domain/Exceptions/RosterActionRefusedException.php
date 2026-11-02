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
     * @param  string  $copy  prefix of the message key, when the same reason is worded for another reader
     */
    public static function because(string $reason, array $replace = [], string $copy = ''): self
    {
        return new self(__('game-tables::messages.roster.errors.'.$copy.$reason, $replace), $reason);
    }
}
