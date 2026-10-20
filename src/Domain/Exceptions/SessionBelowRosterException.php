<?php

declare(strict_types=1);

namespace Modules\GameTables\Domain\Exceptions;

use DomainException;

/**
 * A campaign session cannot be published with fewer seats than its campaign roster.
 */
final class SessionBelowRosterException extends DomainException
{
    private function __construct(string $message, public readonly string $reason)
    {
        parent::__construct($message);
    }

    public static function forTable(string $gameTableId, int $maxPlayers, int $rosterSize): self
    {
        return new self(
            __('game-tables::messages.errors.session_below_roster', ['max' => $maxPlayers, 'count' => $rosterSize]),
            'session_below_roster',
        );
    }

    /**
     * For a session refused before it is saved.
     */
    public static function forNewSession(int $maxPlayers, int $rosterSize): self
    {
        return new self(
            __('game-tables::messages.errors.session_below_roster', ['max' => $maxPlayers, 'count' => $rosterSize]),
            'session_below_roster',
        );
    }
}
