<?php

declare(strict_types=1);

namespace Modules\GameTables\Domain\Enums;

enum RegistrationType: string
{
    case Everyone = 'everyone';
    case MembersOnly = 'members_only';
    case Invite = 'invite';

    public function label(): string
    {
        return match ($this) {
            self::Everyone => __('game-tables::messages.enums.registration_type.everyone'),
            self::MembersOnly => __('game-tables::messages.enums.registration_type.members_only'),
            self::Invite => __('game-tables::messages.enums.registration_type.invite'),
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Everyone => 'heroicon-o-globe-alt',
            self::MembersOnly => 'heroicon-o-user-group',
            self::Invite => 'heroicon-o-envelope',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Everyone => 'success',
            self::MembersOnly => 'info',
            self::Invite => 'warning',
        };
    }

    public function requiresMembership(): bool
    {
        return $this === self::MembersOnly;
    }

    public function requiresInvitation(): bool
    {
        return $this === self::Invite;
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
