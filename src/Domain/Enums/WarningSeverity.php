<?php

declare(strict_types=1);

namespace Modules\GameTables\Domain\Enums;

enum WarningSeverity: string
{
    case Mild = 'mild';
    case Moderate = 'moderate';
    case Severe = 'severe';

    public function label(): string
    {
        return match ($this) {
            self::Mild => __('game-tables::messages.enums.warning_severity.mild'),
            self::Moderate => __('game-tables::messages.enums.warning_severity.moderate'),
            self::Severe => __('game-tables::messages.enums.warning_severity.severe'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Mild => 'info',
            self::Moderate => 'warning',
            self::Severe => 'danger',
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
}
