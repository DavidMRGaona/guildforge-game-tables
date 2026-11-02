<?php

declare(strict_types=1);

namespace Modules\GameTables\Filament\Support;

use Closure;
use Filament\Schemas\Components\Utilities\Get;
use Modules\GameTables\Domain\Enums\TableFormat;

/**
 * Where a table or campaign is played, in the panel as on the web: the location in person, the
 * link online and at least one of the two when hybrid or without a format (TableFormat decides).
 */
final class PlaceRequirement
{
    /**
     * @return Closure(Get): bool
     */
    public static function location(): Closure
    {
        return static function (Get $get): bool {
            $url = $get('online_url');

            return TableFormat::needsLocation(self::format($get('table_format')), is_string($url) ? $url : null);
        };
    }

    /**
     * @return Closure(Get): bool
     */
    public static function onlineUrl(): Closure
    {
        return static fn (Get $get): bool => TableFormat::needsOnlineUrl(self::format($get('table_format')));
    }

    private static function format(mixed $state): ?TableFormat
    {
        return $state instanceof TableFormat ? $state : TableFormat::tryFrom(is_string($state) ? $state : '');
    }
}
