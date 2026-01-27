<?php

declare(strict_types=1);

namespace Modules\GameTables\Domain\Enums;

enum ParticipantStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case WaitingList = 'waiting_list';
    case Cancelled = 'cancelled';
    case Rejected = 'rejected';
    case NoShow = 'no_show';

    public function label(): string
    {
        return match ($this) {
            self::Pending => __('game-tables::messages.enums.participant_status.pending'),
            self::Confirmed => __('game-tables::messages.enums.participant_status.confirmed'),
            self::WaitingList => __('game-tables::messages.enums.participant_status.waiting_list'),
            self::Cancelled => __('game-tables::messages.enums.participant_status.cancelled'),
            self::Rejected => __('game-tables::messages.enums.participant_status.rejected'),
            self::NoShow => __('game-tables::messages.enums.participant_status.no_show'),
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Pending => 'heroicon-o-clock',
            self::Confirmed => 'heroicon-o-check-circle',
            self::WaitingList => 'heroicon-o-queue-list',
            self::Cancelled => 'heroicon-o-x-circle',
            self::Rejected => 'heroicon-o-no-symbol',
            self::NoShow => 'heroicon-o-user-minus',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Confirmed => 'success',
            self::WaitingList => 'info',
            self::Cancelled => 'gray',
            self::Rejected => 'danger',
            self::NoShow => 'danger',
        };
    }

    public function isActive(): bool
    {
        return in_array($this, [self::Pending, self::Confirmed, self::WaitingList], true);
    }

    public function isConfirmed(): bool
    {
        return $this === self::Confirmed;
    }

    public function isWaiting(): bool
    {
        return $this === self::WaitingList;
    }

    public function isFinal(): bool
    {
        return in_array($this, [self::Cancelled, self::Rejected, self::NoShow], true);
    }

    public function canBeCancelled(): bool
    {
        return in_array($this, [self::Pending, self::Confirmed, self::WaitingList], true);
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
