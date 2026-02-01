<?php

declare(strict_types=1);

namespace Modules\GameTables\Domain\Enums;

enum CreationAccessLevel: string
{
    case Everyone = 'everyone';
    case Registered = 'registered';
    case Role = 'role';
    case Permission = 'permission';

    public function label(): string
    {
        return match ($this) {
            self::Everyone => __('game-tables::messages.enums.creation_access_level.everyone'),
            self::Registered => __('game-tables::messages.enums.creation_access_level.registered'),
            self::Role => __('game-tables::messages.enums.creation_access_level.role'),
            self::Permission => __('game-tables::messages.enums.creation_access_level.permission'),
        };
    }

    public function requiresAuthentication(): bool
    {
        return match ($this) {
            self::Everyone => false,
            self::Registered, self::Role, self::Permission => true,
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
