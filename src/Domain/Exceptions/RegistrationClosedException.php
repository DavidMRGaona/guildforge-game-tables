<?php

declare(strict_types=1);

namespace Modules\GameTables\Domain\Exceptions;

use DomainException;

final class RegistrationClosedException extends DomainException
{
    public readonly string $reason;

    private function __construct(string $message, string $reason)
    {
        parent::__construct($message);
        $this->reason = $reason;
    }

    public static function forTable(string $tableId): self
    {
        return new self(
            "Registration for game table '{$tableId}' is closed.",
            'registration_closed'
        );
    }

    public static function notYetOpen(string $tableId): self
    {
        return new self(
            "Registration for game table '{$tableId}' has not opened yet.",
            'registration_not_open'
        );
    }

    public static function alreadyClosed(string $tableId): self
    {
        return new self(
            "Registration for game table '{$tableId}' has already closed.",
            'registration_closed'
        );
    }

    public static function tableStarted(string $tableId): self
    {
        return new self(
            "Game table '{$tableId}' has started: registration is closed.",
            'table_started'
        );
    }

    public static function tableEnded(string $tableId): self
    {
        return new self(
            "Game table '{$tableId}' has ended: registration is closed.",
            'table_ended'
        );
    }

    public static function invitationOnly(string $tableId): self
    {
        return new self(
            "Game table '{$tableId}' is by invitation only: the organisation registers its players.",
            'invitation_only'
        );
    }
}
