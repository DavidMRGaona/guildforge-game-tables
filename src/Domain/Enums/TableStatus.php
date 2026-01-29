<?php

declare(strict_types=1);

namespace Modules\GameTables\Domain\Enums;

enum TableStatus: string
{
    case Draft = 'draft';
    case Scheduled = 'scheduled';
    case Full = 'full';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => __('game-tables::messages.enums.table_status.draft'),
            self::Scheduled => __('game-tables::messages.enums.table_status.scheduled'),
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
            self::Scheduled => 'success',
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
            self::Scheduled => 'heroicon-o-calendar',
            self::Full => 'heroicon-o-user-group',
            self::InProgress => 'heroicon-o-play',
            self::Completed => 'heroicon-o-check-circle',
            self::Cancelled => 'heroicon-o-x-circle',
        };
    }

    public function isActive(): bool
    {
        return in_array($this, [self::Scheduled, self::Full, self::InProgress], true);
    }

    /**
     * Determines if the status allows registrations (based on dates).
     */
    public function isRegistrable(): bool
    {
        return in_array($this, [self::Scheduled, self::Full], true);
    }

    public function isFinal(): bool
    {
        return in_array($this, [self::Completed, self::Cancelled], true);
    }

    /**
     * Validates if a transition to the given status is allowed.
     */
    public function canTransitionTo(self $newStatus): bool
    {
        return match ($this) {
            self::Draft => in_array($newStatus, [self::Scheduled, self::Cancelled], true),
            self::Scheduled => in_array($newStatus, [self::Full, self::InProgress, self::Cancelled], true),
            self::Full => in_array($newStatus, [self::Scheduled, self::InProgress, self::Cancelled], true),
            self::InProgress => in_array($newStatus, [self::Completed, self::Cancelled], true),
            self::Completed, self::Cancelled => false,
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
