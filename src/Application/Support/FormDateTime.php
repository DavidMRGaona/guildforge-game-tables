<?php

declare(strict_types=1);

namespace Modules\GameTables\Application\Support;

use App\Application\Services\TimezoneServiceInterface;
use DateTimeImmutable;
use Exception;
use InvalidArgumentException;

/**
 * A date and time sent by a web form. A wall time without offset (a datetime-local field) is the
 * site's wall time; a string with an offset (the slot of an event) is already an instant.
 */
final class FormDateTime
{
    /**
     * @throws Exception when the string is not a date
     */
    public static function read(string $value): DateTimeImmutable
    {
        $timezone = app(TimezoneServiceInterface::class);

        try {
            return $timezone->fromDisplayWallTime($value);
        } catch (InvalidArgumentException) {
            return $timezone->toStorage(new DateTimeImmutable($value, $timezone->displayTimezone()));
        }
    }
}
