<?php

declare(strict_types=1);

namespace Modules\GameTables\Domain\Enums;

enum TableStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Open = 'open';
    case Full = 'full';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => __('game-tables::messages.enums.table_status.draft'),
            self::Published => __('game-tables::messages.enums.table_status.published'),
            self::Open => __('game-tables::messages.enums.table_status.open'),
            self::Full => __('game-tables::messages.enums.table_status.full'),
            self::InProgress => __('game-tables::messages.enums.table_status.in_progress'),
            self::Completed => __('game-tables::messages.enums.table_status.completed'),
            self::Cancelled => __('game-tables::messages.enums.table_status.cancelled'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Published => 'info',
            self::Open => 'success',
            self::Full => 'warning',
            self::InProgress => 'primary',
            self::Completed => 'success',
            self::Cancelled => 'danger',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Draft => 'heroicon-o-pencil',
            self::Published => 'heroicon-o-eye',
            self::Open => 'heroicon-o-lock-open',
            self::Full => 'heroicon-o-user-group',
            self::InProgress => 'heroicon-o-play',
            self::Completed => 'heroicon-o-check-circle',
            self::Cancelled => 'heroicon-o-x-circle',
        };
    }

    public function isActive(): bool
    {
        return in_array($this, [self::Published, self::Open, self::Full, self::InProgress], true);
    }

    public function canRegister(): bool
    {
        return $this === self::Open;
    }

    public function isFinal(): bool
    {
        return in_array($this, [self::Completed, self::Cancelled], true);
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
