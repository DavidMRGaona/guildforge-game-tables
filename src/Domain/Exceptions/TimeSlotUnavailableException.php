<?php

declare(strict_types=1);

namespace Modules\GameTables\Domain\Exceptions;

use DomainException;

/**
 * On an event scheduled by time slots, a table starts at one of its slots, and only while that
 * slot has room for it.
 */
final class TimeSlotUnavailableException extends DomainException
{
    public static function notASlot(): self
    {
        return new self(__('game-tables::messages.validation.starts_at_not_a_slot'));
    }

    public static function full(string $slotLabel): self
    {
        return new self(__('game-tables::messages.validation.time_slot_full', ['slot' => $slotLabel]));
    }
}
