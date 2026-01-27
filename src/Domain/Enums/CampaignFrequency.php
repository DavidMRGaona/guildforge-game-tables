<?php

declare(strict_types=1);

namespace Modules\GameTables\Domain\Enums;

enum CampaignFrequency: string
{
    case Weekly = 'weekly';
    case Biweekly = 'biweekly';
    case Monthly = 'monthly';
    case Irregular = 'irregular';

    public function label(): string
    {
        return match ($this) {
            self::Weekly => __('game-tables::messages.enums.campaign_frequency.weekly'),
            self::Biweekly => __('game-tables::messages.enums.campaign_frequency.biweekly'),
            self::Monthly => __('game-tables::messages.enums.campaign_frequency.monthly'),
            self::Irregular => __('game-tables::messages.enums.campaign_frequency.irregular'),
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Weekly => 'heroicon-o-calendar',
            self::Biweekly => 'heroicon-o-calendar-days',
            self::Monthly => 'heroicon-o-calendar',
            self::Irregular => 'heroicon-o-question-mark-circle',
        };
    }

    /**
     * Get approximate days between sessions.
     */
    public function daysBetweenSessions(): ?int
    {
        return match ($this) {
            self::Weekly => 7,
            self::Biweekly => 14,
            self::Monthly => 30,
            self::Irregular => null,
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
