<?php

declare(strict_types=1);

namespace Modules\GameTables\Domain\Exceptions;

use DomainException;

final class CannotCancelException extends DomainException
{
    private function __construct(string $message, /**
     * Why: 'already_cancelled', 'not_allowed' (the registration is no longer active) or
     * 'table_started' (participants cannot cancel by themselves once the table has started).
     */
        public readonly string $reason)
    {
        parent::__construct($message);
    }

    public static function alreadyCancelled(string $participantId): self
    {
        return new self("Participant '{$participantId}' registration is already cancelled.", 'already_cancelled');
    }

    public static function notAllowed(string $participantId): self
    {
        return new self("Participant '{$participantId}' registration cannot be cancelled in its current status.", 'not_allowed');
    }

    public static function tableStarted(string $participantId): self
    {
        return new self("Participant '{$participantId}' cannot cancel the registration: the table has started.", 'table_started');
    }
}
