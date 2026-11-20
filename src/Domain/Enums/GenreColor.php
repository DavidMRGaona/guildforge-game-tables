<?php

declare(strict_types=1);

namespace Modules\GameTables\Domain\Enums;

/**
 * The colour of a genre's badge: one of the states of the theme, so it reads well in light and
 * dark mode on the site and in the panel.
 */
enum GenreColor: string
{
    case Primary = 'primary';
    case Info = 'info';
    case Success = 'success';
    case Warning = 'warning';
    case Danger = 'danger';
    case Gray = 'gray';

    public static function default(): self
    {
        return self::Gray;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
