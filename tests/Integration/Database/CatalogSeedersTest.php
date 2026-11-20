<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Integration\Database;

use Modules\GameTables\Database\Seeders\ContentWarningsSeeder;
use Modules\GameTables\Database\Seeders\GameSystemsSeeder;
use Modules\GameTables\Database\Seeders\GenresSeeder;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\ContentWarningModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameSystemModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GenreModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\PublisherModel;
use Tests\Support\Modules\ModuleTestCase;

/**
 * The catalog seeders only add what is missing: running them again must not
 * overwrite what admins edited in the panel.
 */
final class CatalogSeedersTest extends ModuleTestCase
{
    protected ?string $moduleName = 'game-tables';

    protected bool $autoEnableModule = true;

    protected function setUp(): void
    {
        parent::setUp();

        // database/ is outside the module's autoloaded src/
        foreach (glob(dirname(__DIR__, 3).'/database/seeders/*Seeder.php') ?: [] as $seeder) {
            require_once $seeder;
        }
    }

    public function test_running_the_catalog_seeders_again_keeps_admin_edits(): void
    {
        $this->seed(GameSystemsSeeder::class);
        $this->seed(ContentWarningsSeeder::class);

        $system = GameSystemModel::query()->firstOrFail();
        $publisher = PublisherModel::query()->firstOrFail();
        $warning = ContentWarningModel::query()->firstOrFail();
        $system->update(['name' => 'Renamed by an admin']);
        $publisher->update(['name' => 'Publisher renamed by an admin']);
        $warning->update(['label' => 'Label edited by an admin']);

        $this->seed(GameSystemsSeeder::class);
        $this->seed(ContentWarningsSeeder::class);

        $this->assertSame('Renamed by an admin', $system->fresh()?->name);
        $this->assertSame('Publisher renamed by an admin', $publisher->fresh()?->name);
        $this->assertSame('Label edited by an admin', $warning->fresh()?->label);
    }

    public function test_the_genres_seeder_creates_the_genres_tables_already_store(): void
    {
        GenreModel::query()->delete();

        $this->seed(GenresSeeder::class);

        $genres = GenreModel::query()->orderBy('sort_order')->get();

        // The keys the fixed list wrote into tables and campaigns, so none loses its genres
        $this->assertSame(
            ['fantasy', 'horror', 'sci_fi', 'post_apocalyptic', 'cyberpunk', 'steampunk', 'historical', 'modern', 'superhero', 'mystery', 'western', 'comedy', 'other'],
            $genres->pluck('key')->all(),
        );
        $this->assertSame(
            ['Fantasía', 'Terror', 'Ciencia ficción', 'Post-apocalíptico', 'Cyberpunk', 'Steampunk', 'Histórico', 'Moderno', 'Superhéroes', 'Misterio', 'Western', 'Comedia', 'Otro'],
            $genres->pluck('name')->all(),
        );
        $this->assertSame(
            ['primary', 'danger', 'info', 'warning', 'success', 'warning', 'gray', 'info', 'primary', 'gray', 'warning', 'success', 'gray'],
            $genres->pluck('color')->all(),
        );
        $this->assertSame(range(1, 13), $genres->pluck('sort_order')->all());
        $this->assertTrue($genres->every(static fn (GenreModel $genre): bool => $genre->is_active));
    }

    public function test_the_genres_seeder_takes_the_names_in_the_language_of_the_site(): void
    {
        GenreModel::query()->delete();
        app()->setLocale('en');

        $this->seed(GenresSeeder::class);

        $this->assertSame('Science fiction', GenreModel::query()->where('key', 'sci_fi')->value('name'));
    }

    public function test_running_the_genres_seeder_again_keeps_admin_edits(): void
    {
        $this->seed(GenresSeeder::class);
        GenreModel::query()->where('key', 'horror')->update(['name' => 'Horror cósmico', 'color' => 'info', 'is_active' => false]);
        GenreModel::query()->where('key', 'other')->delete();

        $this->seed(GenresSeeder::class);

        $horror = GenreModel::query()->where('key', 'horror')->sole();
        $this->assertSame('Horror cósmico', $horror->name);
        $this->assertSame('info', $horror->color);
        $this->assertFalse($horror->is_active);
        $this->assertSame(1, GenreModel::query()->where('key', 'horror')->count());
        // Run by hand it adds back what is missing; an installation runs it only once
        $this->assertSame(13, GenreModel::query()->count());
    }
}
