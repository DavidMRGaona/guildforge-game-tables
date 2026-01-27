<?php

declare(strict_types=1);

namespace Modules\GameTables\Domain\Enums;

enum ExperienceLevel: string
{
    case None = 'none';
    case Beginner = 'beginner';
    case Intermediate = 'intermediate';
    case Advanced = 'advanced';

    public function label(): string
    {
        return match ($this) {
            self::None => __('game-tables::messages.enums.experience_level.none'),
            self::Beginner => __('game-tables::messages.enums.experience_level.beginner'),
            self::Intermediate => __('game-tables::messages.enums.experience_level.intermediate'),
            self::Advanced => __('game-tables::messages.enums.experience_level.advanced'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::None => 'success',
            self::Beginner => 'info',
            self::Intermediate => 'warning',
            self::Advanced => 'danger',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::None => 'heroicon-o-hand-raised',
            self::Beginner => 'heroicon-o-star',
            self::Intermediate => 'heroicon-o-sparkles',
            self::Advanced => 'heroicon-o-fire',
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
