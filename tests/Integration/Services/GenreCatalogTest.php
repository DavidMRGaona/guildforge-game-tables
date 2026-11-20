<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Integration\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\GameTables\Application\DTOs\GenreDTO;
use Modules\GameTables\Application\Services\GenreCatalogInterface;
use Modules\GameTables\Domain\Entities\Genre;
use Modules\GameTables\Domain\Repositories\GenreRepositoryInterface;
use Modules\GameTables\Domain\ValueObjects\GenreKey;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GenreModel;
use Modules\GameTables\Infrastructure\Services\GenreCatalog;
use RuntimeException;
use Tests\Support\Modules\ModuleTestCase;

/**
 * Forms, pages and search read the genres through the catalog: what is offered, what is
 * accepted and how the keys a table stores are shown.
 */
final class GenreCatalogTest extends ModuleTestCase
{
    protected ?string $moduleName = 'game-tables';

    protected bool $autoEnableModule = true;

    protected function setUp(): void
    {
        parent::setUp();

        GenreModel::query()->delete();
        $this->genre('horror', 'Terror', 2, color: 'danger');
        $this->genre('fantasy', 'Fantasía', 1, color: 'primary');
        $this->genre('western', 'Western', 3, active: false);
        $this->genre('mystery', 'Misterio', 4);
    }

    public function test_active_gives_the_active_genres_in_their_order(): void
    {
        $this->assertSame(['fantasy', 'horror', 'mystery'], $this->keys($this->catalog()->active()));
    }

    public function test_a_genre_carries_its_name_colour_and_state(): void
    {
        $horror = $this->catalog()->resolve(['horror'])[0];

        $this->assertSame('horror', $horror->key);
        $this->assertSame('Terror', $horror->name);
        $this->assertSame('danger', $horror->color);
        $this->assertTrue($horror->isActive);
    }

    public function test_resolve_follows_the_catalog_order_and_keeps_inactive_genres(): void
    {
        $this->assertSame(['fantasy', 'horror', 'western'], $this->keys($this->catalog()->resolve(['western', 'horror', 'fantasy'])));
    }

    public function test_resolve_leaves_out_unknown_malformed_and_repeated_keys_without_failing(): void
    {
        $genres = $this->catalog()->resolve(['horror', 'deleted_genre', 'Fantasy', 7, null, ['x'], 'horror']);

        $this->assertSame(['horror'], $this->keys($genres));
    }

    public function test_resolve_of_nothing_is_nothing(): void
    {
        $this->assertSame([], $this->catalog()->resolve([]));
    }

    public function test_options_offer_the_active_genres_by_name(): void
    {
        $this->assertSame(
            ['fantasy' => 'Fantasía', 'horror' => 'Terror', 'mystery' => 'Misterio'],
            $this->catalog()->options(),
        );
    }

    public function test_options_also_offer_the_inactive_genres_a_record_already_has(): void
    {
        $this->assertSame(
            ['fantasy' => 'Fantasía', 'horror' => 'Terror', 'western' => 'Western', 'mystery' => 'Misterio'],
            $this->catalog()->options(['western', 'deleted_genre']),
        );
    }

    public function test_accepted_keys_are_the_active_ones_and_those_kept(): void
    {
        $this->assertSame(['fantasy', 'horror', 'mystery'], $this->catalog()->acceptedKeys());
        $this->assertSame(['fantasy', 'horror', 'western', 'mystery'], $this->catalog()->acceptedKeys(['western']));
    }

    public function test_the_genres_are_read_once_per_request(): void
    {
        $catalog = $this->catalog();
        $catalog->active();

        DB::flushQueryLog();
        DB::enableQueryLog();
        $catalog->resolve(['horror']);
        $catalog->options(['western']);
        $this->assertSame([], DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame($catalog, $this->catalog());
    }

    public function test_the_catalog_is_empty_before_the_genres_exist(): void
    {
        GenreModel::query()->delete();
        $this->app->forgetScopedInstances();

        $this->assertSame([], $this->catalog()->active());
        $this->assertSame([], $this->catalog()->resolve(['horror']));
        $this->assertSame([], $this->catalog()->options(['horror']));
    }

    public function test_a_read_that_failed_is_tried_again_instead_of_giving_no_genres(): void
    {
        $real = app(GenreRepositoryInterface::class);
        $failing = new class($real) implements GenreRepositoryInterface
        {
            public int $reads = 0;

            public function __construct(private readonly GenreRepositoryInterface $real) {}

            public function save(Genre $genre): void
            {
                $this->real->save($genre);
            }

            public function findByKey(GenreKey $key): ?Genre
            {
                return $this->real->findByKey($key);
            }

            public function all(): array
            {
                if (++$this->reads === 1) {
                    throw new RuntimeException('The database is not reachable');
                }

                return $this->real->all();
            }
        };
        $catalog = new GenreCatalog($failing);

        try {
            $catalog->active();
            $this->fail('The first read should have failed');
        } catch (RuntimeException) {
            // The caller decides what to do with it
        }

        $this->assertSame(['fantasy', 'horror', 'mystery'], $this->keys($catalog->active()));
    }

    private function catalog(): GenreCatalogInterface
    {
        return app(GenreCatalogInterface::class);
    }

    /**
     * @param  list<GenreDTO>  $genres
     * @return list<string>
     */
    private function keys(array $genres): array
    {
        return array_map(static fn (GenreDTO $genre): string => $genre->key, $genres);
    }

    private function genre(string $key, string $name, int $order, bool $active = true, string $color = 'gray'): void
    {
        GenreModel::query()->create([
            'id' => (string) Str::uuid(),
            'key' => $key,
            'name' => $name,
            'color' => $color,
            'sort_order' => $order,
            'is_active' => $active,
        ]);
    }
}
