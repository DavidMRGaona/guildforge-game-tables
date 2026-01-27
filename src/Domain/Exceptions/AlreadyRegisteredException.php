<?php

declare(strict_types=1);

namespace Modules\GameTables\Domain\Exceptions;

use DomainException;

final class AlreadyRegisteredException extends DomainException
{
    public readonly string $reason;

    private function __construct(string $message, string $reason)
    {
        parent::__construct($message);
        $this->reason = $reason;
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
}
