<?php

declare(strict_types=1);

namespace Modules\GameTables\Domain\Exceptions;

use DomainException;

/**
 * A guest has not confirmed their email yet: only the organisation may vouch for it, so a
 * director cannot approve the registration until the guest confirms.
 */
final class ParticipantAwaitingEmailException extends DomainException
{
    private function __construct(string $message, public readonly string $reason)
    {
        parent::__construct($message);
    }

    public static function forParticipant(string $participantId): self
    {
        return new self(
            "Participant '{$participantId}' has not confirmed their email yet.",
            'awaiting_email'
        );
    }
}
