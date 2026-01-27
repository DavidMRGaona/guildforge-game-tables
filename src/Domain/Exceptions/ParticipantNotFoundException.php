<?php

declare(strict_types=1);

namespace Modules\GameTables\Domain\Exceptions;

use DomainException;

final class ParticipantNotFoundException extends DomainException
{
    public static function withId(string $id): self
    {
        return new self("Participant with ID '{$id}' not found.");
    }

    public static function forUserInTable(string $userId, string $tableId): self
    {
        return new self("User '{$userId}' is not registered for game table '{$tableId}'.");
    }

    public static function byToken(string $token): self
    {
        return new self("No participant found with cancellation token '{$token}'.");
    }
}
