<?php

declare(strict_types=1);

namespace Modules\GameTables\Domain\Exceptions;

use DomainException;

final class CannotCancelException extends DomainException
{
    public static function alreadyCancelled(string $participantId): self
    {
        return new self("Participant '{$participantId}' registration is already cancelled.");
    }

    public static function notAllowed(string $participantId): self
    {
        return new self("Participant '{$participantId}' registration cannot be cancelled in its current status.");
    }
}
