<?php

declare(strict_types=1);

namespace Modules\GameTables\Infrastructure\Activity;

use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * The opaque cursor of an activity history (core#774): the last id of a page, marked with the
 * source it belongs to and encoded, so a new row never shifts it and a cursor of another source is
 * never read as one of this one. Anything else throws, so a walk never starts again from the top.
 */
final class ActivityCursor
{
    private const string VERSION = 'gt1';

    public static function after(string $scope, string $id): string
    {
        return rtrim(strtr(base64_encode(self::VERSION.'|'.$scope.'|'.strtolower($id)), '+/', '-_'), '=');
    }

    /**
     * The id the next page starts after, in lower case.
     *
     * @throws InvalidArgumentException when it is not a cursor of this scope
     */
    public static function read(string $scope, string $cursor): string
    {
        $decoded = base64_decode(strtr($cursor, '-_', '+/'), true);
        $prefix = self::VERSION.'|'.$scope.'|';

        if (! is_string($decoded) || ! str_starts_with($decoded, $prefix)) {
            throw new InvalidArgumentException('The activity history cursor cannot be read.');
        }

        $id = substr($decoded, strlen($prefix));

        if (! Str::isUuid($id)) {
            throw new InvalidArgumentException('The activity history cursor cannot be read.');
        }

        return strtolower($id);
    }
}
