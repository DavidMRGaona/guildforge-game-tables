<?php

declare(strict_types=1);

namespace Modules\GameTables\Domain\Enums;

enum GameMasterRole: string
{
    case Main = 'main';
    case CoGm = 'co_gm';

    public function label(): string
    {
        return match ($this) {
            self::Main => __('game-tables::messages.enums.gm_role.main'),
            self::CoGm => __('game-tables::messages.enums.gm_role.co_gm'),
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Main => 'heroicon-o-star',
            self::CoGm => 'heroicon-o-user-plus',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Main => 'warning',
            self::CoGm => 'info',
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
