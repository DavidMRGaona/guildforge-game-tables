<?php

declare(strict_types=1);

namespace Modules\GameTables\Domain\Exceptions;

use DomainException;

final class AlreadyRegisteredException extends DomainException
{
    private function __construct(string $message, public readonly string $reason)
    {
        parent::__construct($message);
    }

    public static function forTable(string $userId, string $tableId): self
    {
        return new self(
            "User '{$userId}' is already registered for game table '{$tableId}'.",
            'already_registered'
        );
    }

    public static function forGuestEmail(string $email, string $tableId): self
    {
        return new self(
            "Guest with email '{$email}' is already registered for game table '{$tableId}'.",
            'guest_already_registered'
        );
    }

    /**
     * A guest registration whose email already has a registration at the table, made by a
     * signed-in user (or the other way round); the message is the one shown to the person.
     */
    public static function emailRegisteredAsGuest(string $gameTableId): self
    {
        return new self(
            __('game-tables::messages.errors.email_registered_as_guest'),
            'email_registered_as_guest'
        );
    }
}
