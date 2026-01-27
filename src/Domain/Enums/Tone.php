<?php

declare(strict_types=1);

namespace Modules\GameTables\Domain\Enums;

enum Tone: string
{
    case Serious = 'serious';
    case Light = 'light';
    case Mixed = 'mixed';
    case Dark = 'dark';

    public function label(): string
    {
        return match ($this) {
            self::Serious => __('game-tables::messages.enums.tone.serious'),
            self::Light => __('game-tables::messages.enums.tone.light'),
            self::Mixed => __('game-tables::messages.enums.tone.mixed'),
            self::Dark => __('game-tables::messages.enums.tone.dark'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Serious => 'gray',
            self::Light => 'success',
            self::Mixed => 'info',
            self::Dark => 'danger',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Serious => 'heroicon-o-scale',
            self::Light => 'heroicon-o-face-smile',
            self::Mixed => 'heroicon-o-arrows-right-left',
            self::Dark => 'heroicon-o-moon',
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
