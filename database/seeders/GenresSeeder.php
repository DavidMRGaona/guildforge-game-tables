<?php

declare(strict_types=1);

namespace Modules\GameTables\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Modules\GameTables\Domain\Enums\GenreColor;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GenreModel;

/**
 * The genres of the fixed list the module had before the catalog, with the keys tables and
 * campaigns already store, so none of them loses its genres. It runs once per installation,
 * new or updated; afterwards the genres belong to the panel.
 */
final class GenresSeeder extends Seeder
{
    /** key => colour, in the order of the old list */
    private const array GENRES = [
        'fantasy' => GenreColor::Primary,
        'horror' => GenreColor::Danger,
        'sci_fi' => GenreColor::Info,
        'post_apocalyptic' => GenreColor::Warning,
        'cyberpunk' => GenreColor::Success,
        'steampunk' => GenreColor::Warning,
        'historical' => GenreColor::Gray,
        'modern' => GenreColor::Info,
        'superhero' => GenreColor::Primary,
        'mystery' => GenreColor::Gray,
        'western' => GenreColor::Warning,
        'comedy' => GenreColor::Success,
        'other' => GenreColor::Gray,
    ];

    public function run(): void
    {
        $position = 0;

        foreach (self::GENRES as $key => $color) {
            $position++;

            // Only add missing genres: never overwrite what an admin edited
            GenreModel::query()->firstOrCreate(['key' => $key], [
                'id' => (string) Str::uuid(),
                // In the language of the site, which is the only name a genre has
                'name' => __('game-tables::messages.enums.genre.'.$key),
                'color' => $color->value,
                'sort_order' => $position,
                'is_active' => true,
            ]);
        }
    }
}
