<?php

declare(strict_types=1);

namespace Modules\GameTables\Domain\Enums;

enum CampaignStatus: string
{
    case Recruiting = 'recruiting';
    case Active = 'active';
    case OnHold = 'on_hold';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Recruiting => __('game-tables::messages.enums.campaign_status.recruiting'),
            self::Active => __('game-tables::messages.enums.campaign_status.active'),
            self::OnHold => __('game-tables::messages.enums.campaign_status.on_hold'),
            self::Completed => __('game-tables::messages.enums.campaign_status.completed'),
            self::Cancelled => __('game-tables::messages.enums.campaign_status.cancelled'),
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Recruiting => 'heroicon-o-megaphone',
            self::Active => 'heroicon-o-play',
            self::OnHold => 'heroicon-o-pause',
            self::Completed => 'heroicon-o-check-circle',
            self::Cancelled => 'heroicon-o-x-circle',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Recruiting => 'info',
            self::Active => 'success',
            self::OnHold => 'warning',
            self::Completed => 'success',
            self::Cancelled => 'danger',
        };
    }

    public function isActive(): bool
    {
        return in_array($this, [self::Recruiting, self::Active], true);
    }

    public function acceptsNewPlayers(): bool
    {
        return $this === self::Recruiting;
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
