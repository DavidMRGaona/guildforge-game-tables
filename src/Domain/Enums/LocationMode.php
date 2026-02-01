<?php

declare(strict_types=1);

namespace Modules\GameTables\Domain\Enums;

/**
 * Defines how game table locations are determined within an event.
 */
enum LocationMode: string
{
    /**
     * Creator chooses any location.
     */
    case FreeChoice = 'free';

    /**
     * Predefined fixed location for all tables.
     */
    case FixedLocation = 'fixed';

    /**
     * Use the event's location.
     */
    case EventLocation = 'event';

    public function label(): string
    {
        return match ($this) {
            self::FreeChoice => __('game-tables::messages.enums.location_mode.free'),
            self::FixedLocation => __('game-tables::messages.enums.location_mode.fixed'),
            self::EventLocation => __('game-tables::messages.enums.location_mode.event'),
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::FreeChoice => __('game-tables::messages.enums.location_mode.free_description'),
            self::FixedLocation => __('game-tables::messages.enums.location_mode.fixed_description'),
            self::EventLocation => __('game-tables::messages.enums.location_mode.event_description'),
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return array_combine(
            array_column(self::cases(), 'value'),
            array_map(fn (self $case): string => $case->label(), self::cases()),
        );
    }

    /**
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
