<?php

declare(strict_types=1);

namespace Modules\GameTables\Http\Support;

use Illuminate\Http\Request;
use Modules\GameTables\Application\DTOs\GenreDTO;
use Modules\GameTables\Application\Services\GenreCatalogInterface;
use Modules\GameTables\Domain\ValueObjects\GenreKey;

/**
 * The genre filter of the public lists (`?genre=<key>`). The catalog is resolved on each call:
 * it is scoped, and a route keeps its controller.
 */
final class GenreFilter
{
    public const string PARAMETER = 'genre';

    /**
     * The key to filter by: one the catalog knows, active or not (tables keep deactivated genres).
     * Anything else (no value, a list, a malformed or unknown key) is no filter, never an error.
     */
    public static function chosen(Request $request): ?string
    {
        $key = GenreKey::tryFrom($request->query(self::PARAMETER));

        if (! $key instanceof GenreKey) {
            return null;
        }

        return self::catalog()->resolve([$key->value]) !== [] ? $key->value : null;
    }

    /**
     * What the filter offers: the active genres some record of the list has, in the catalog's
     * order, plus the chosen one (deactivated or on no record now), so its choice still shows.
     *
     * @param  list<string>  $keysInList
     * @return list<array{value: string, label: string}>
     */
    public static function options(array $keysInList, ?string $chosen): array
    {
        $inList = array_fill_keys($keysInList, true);
        $genres = array_filter(
            self::catalog()->resolve([...$keysInList, ...($chosen !== null ? [$chosen] : [])]),
            static fn (GenreDTO $genre): bool => ($genre->isActive && isset($inList[$genre->key])) || $genre->key === $chosen,
        );

        return array_values(array_map(
            static fn (GenreDTO $genre): array => ['value' => $genre->key, 'label' => $genre->name],
            $genres,
        ));
    }

    private static function catalog(): GenreCatalogInterface
    {
        return app(GenreCatalogInterface::class);
    }
}
