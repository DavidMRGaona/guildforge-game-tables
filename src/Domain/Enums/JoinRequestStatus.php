<?php

declare(strict_types=1);

namespace Modules\GameTables\Domain\Enums;

enum JoinRequestStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case Withdrawn = 'withdrawn';

    public function label(): string
    {
        return match ($this) {
            self::Pending => __('game-tables::messages.enums.join_request_status.pending'),
            self::Accepted => __('game-tables::messages.enums.join_request_status.accepted'),
            self::Rejected => __('game-tables::messages.enums.join_request_status.rejected'),
            self::Withdrawn => __('game-tables::messages.enums.join_request_status.withdrawn'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Accepted => 'success',
            self::Rejected => 'danger',
            self::Withdrawn => 'gray',
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
