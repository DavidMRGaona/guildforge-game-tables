<?php

declare(strict_types=1);

namespace Modules\GameTables\Domain\Enums;

enum TableType: string
{
    case OneShot = 'one_shot';
    case Adventure = 'adventure';
    case CampaignSession = 'campaign_session';
    case Demo = 'demo';
    case Tutorial = 'tutorial';

    public function label(): string
    {
        return match ($this) {
            self::OneShot => __('game-tables::messages.enums.table_type.one_shot'),
            self::Adventure => __('game-tables::messages.enums.table_type.adventure'),
            self::CampaignSession => __('game-tables::messages.enums.table_type.campaign_session'),
            self::Demo => __('game-tables::messages.enums.table_type.demo'),
            self::Tutorial => __('game-tables::messages.enums.table_type.tutorial'),
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::OneShot => 'heroicon-o-bolt',
            self::Adventure => 'heroicon-o-map',
            self::CampaignSession => 'heroicon-o-book-open',
            self::Demo => 'heroicon-o-presentation-chart-bar',
            self::Tutorial => 'heroicon-o-academic-cap',
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
