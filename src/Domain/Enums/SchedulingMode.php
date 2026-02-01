<?php

declare(strict_types=1);

namespace Modules\GameTables\Domain\Enums;

/**
 * Defines how game tables can be scheduled within an event.
 */
enum SchedulingMode: string
{
    /**
     * Any time within the event's duration.
     */
    case FreeSchedule = 'free';

    /**
     * Predefined time slots (morning, afternoon, etc.).
     */
    case SlotBased = 'slots';

    public function label(): string
    {
        return match ($this) {
            self::FreeSchedule => __('game-tables::messages.enums.scheduling_mode.free'),
            self::SlotBased => __('game-tables::messages.enums.scheduling_mode.slots'),
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::FreeSchedule => __('game-tables::messages.enums.scheduling_mode.free_description'),
            self::SlotBased => __('game-tables::messages.enums.scheduling_mode.slots_description'),
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
