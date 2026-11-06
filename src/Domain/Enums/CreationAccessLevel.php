<?php

declare(strict_types=1);

namespace Modules\GameTables\Domain\Enums;

/**
 * Who may create tables or campaigns from the web. Creating always needs a session, so there is no
 * level for people without an account: the old "everyone" is read as Registered (fromStored()).
 */
enum CreationAccessLevel: string
{
    case Registered = 'registered';
    case Role = 'role';
    case Permission = 'permission';

    public function label(): string
    {
        return match ($this) {
            self::Registered => __('game-tables::messages.enums.creation_access_level.registered'),
            self::Role => __('game-tables::messages.enums.creation_access_level.role'),
            self::Permission => __('game-tables::messages.enums.creation_access_level.permission'),
        };
    }

    /**
     * A level as saved in the settings or an event's configuration. "everyone" was offered until it was
     * found to work as "registered" (creating always needed a session), so it is read as that.
     */
    public static function fromStored(string $value): self
    {
        return $value === 'everyone' ? self::Registered : self::from($value);
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
