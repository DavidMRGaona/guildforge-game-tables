<?php

declare(strict_types=1);

namespace Modules\GameTables\Domain\Enums;

enum TableFormat: string
{
    case InPerson = 'in_person';
    case Online = 'online';
    case Hybrid = 'hybrid';

    public function label(): string
    {
        return match ($this) {
            self::InPerson => __('game-tables::messages.enums.table_format.in_person'),
            self::Online => __('game-tables::messages.enums.table_format.online'),
            self::Hybrid => __('game-tables::messages.enums.table_format.hybrid'),
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::InPerson => 'heroicon-o-user-group',
            self::Online => 'heroicon-o-computer-desktop',
            self::Hybrid => 'heroicon-o-arrows-right-left',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::InPerson => 'success',
            self::Online => 'info',
            self::Hybrid => 'warning',
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
