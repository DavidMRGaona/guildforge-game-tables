<?php

declare(strict_types=1);

namespace Modules\GameTables\Domain\Exceptions;

use DomainException;

final class ParticipantNotPendingException extends DomainException
{
    private function __construct(string $message, public readonly string $reason)
    {
        parent::__construct($message);
    }

    public static function forParticipant(string $participantId): self
    {
        return new self(
            "Participant '{$participantId}' is neither pending nor on the waiting list.",
            'not_pending'
        );
    }
}
