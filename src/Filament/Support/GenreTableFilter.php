<?php

declare(strict_types=1);

namespace Modules\GameTables\Filament\Support;

use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;
use Modules\GameTables\Application\Services\GenreCatalogInterface;
use Modules\GameTables\Domain\ValueObjects\GenreKey;

/**
 * The genre filter of the panel's table and campaign lists: any genre of the catalog, active or
 * not, since the panel also shows what the site no longer offers.
 */
final class GenreTableFilter
{
    public static function make(): SelectFilter
    {
        return SelectFilter::make('genre')
            ->label(__('game-tables::messages.fields.genre'))
            ->options(static function (): array {
                $options = [];

                foreach (app(GenreCatalogInterface::class)->all() as $genre) {
                    $options[$genre->key] = $genre->name;
                }

                return $options;
            })
            // A key of the JSON list, compared whole; a forged value is no key and filters nothing
            ->query(static function (Builder $query, array $data): Builder {
                $key = GenreKey::tryFrom($data['value'] ?? null);

                return $key instanceof GenreKey ? $query->whereJsonContains('genres', $key->value) : $query;
            });
    }
}
