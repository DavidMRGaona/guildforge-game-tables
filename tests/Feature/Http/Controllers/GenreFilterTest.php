<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Feature\Http\Controllers;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Livewire\Livewire;
use Modules\GameTables\Database\Seeders\GenresSeeder;
use Modules\GameTables\Filament\Resources\CampaignResource;
use Modules\GameTables\Filament\Resources\CampaignResource\Pages\ListCampaigns;
use Modules\GameTables\Filament\Resources\GameTableResource;
use Modules\GameTables\Filament\Resources\GameTableResource\Pages\ListGameTables;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\CampaignModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameSystemModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameTableModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GenreModel;
use Tests\Support\Modules\InteractsWithModulePanel;
use Tests\Support\Modules\ModuleTestCase;

/**
 * /mesas and /campanas filter by one genre (`?genre=<key>`), offering the active genres some
 * listed table or campaign has; the panel lists filter by any genre of the catalog.
 */
final class GenreFilterTest extends ModuleTestCase
{
    use InteractsWithModulePanel;

    protected ?string $moduleName = 'game-tables';

    protected bool $autoEnableModule = true;

    private GameSystemModel $system;

    protected function setUp(): void
    {
        parent::setUp();

        app('router')->getRoutes()->refreshNameLookups();
        config()->set('modules.settings.game-tables', []);

        // database/ is outside the module's autoloaded src/
        require_once dirname(__DIR__, 4).'/database/seeders/GenresSeeder.php';
        $this->seed(GenresSeeder::class);

        $this->system = GameSystemModel::create([
            'id' => (string) Str::uuid(),
            'name' => 'Sistema de prueba',
            'slug' => 'sistema-de-prueba',
            'is_active' => true,
        ]);
    }

    public function test_the_tables_list_shows_only_the_tables_of_the_genre(): void
    {
        $this->table('Mesa de terror', ['genres' => ['horror', 'mystery']]);
        $this->table('Mesa de fantasía', ['genres' => ['fantasy']]);
        $this->table('Mesa sin géneros');
        // Only starts like the key
        $this->table('Mesa de comedia de terror', ['genres' => ['horror_comedy']]);

        $this->get('/mesas?genre=horror')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('tables.data', fn (Collection $tables): bool => $tables->pluck('title')->all() === ['Mesa de terror'])
                ->where('tables.meta.total', 1)
                ->where('currentFilters.genre', 'horror'));
    }

    public function test_the_genre_combines_with_the_other_filters(): void
    {
        $this->table('Mesa de terror presencial', ['genres' => ['horror'], 'table_format' => 'in_person']);
        $this->table('Mesa de terror online', ['genres' => ['horror'], 'table_format' => 'online', 'online_url' => 'https://example.com/mesa']);

        $this->get('/mesas?genre=horror&format=online')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('tables.data', fn (Collection $tables): bool => $tables->pluck('title')->all() === ['Mesa de terror online'])
                ->where('tables.meta.total', 1));
    }

    public function test_an_unknown_or_malformed_genre_is_ignored(): void
    {
        $this->table('Mesa de terror', ['genres' => ['horror']]);
        $this->table('Mesa de fantasía', ['genres' => ['fantasy']]);

        foreach (['space_opera', 'Terror!', ''] as $genre) {
            $this->get('/mesas?genre='.urlencode($genre))
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->where('tables.meta.total', 2)
                    ->where('currentFilters.genre', null));
        }

        $this->get('/mesas?genre[]=horror')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('tables.meta.total', 2));
    }

    public function test_a_deactivated_genre_still_filters_and_is_offered_while_chosen(): void
    {
        $this->table('Mesa del oeste', ['genres' => ['western']]);
        $this->table('Mesa de fantasía', ['genres' => ['fantasy']]);
        GenreModel::query()->where('key', 'western')->update(['is_active' => false]);
        // A write that skips the model: the catalog read while saving the tables is dropped by hand
        $this->app->forgetScopedInstances();

        $this->get('/mesas?genre=western')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('tables.meta.total', 1)
                ->where('genres', [
                    ['value' => 'fantasy', 'label' => 'Fantasía'],
                    ['value' => 'western', 'label' => 'Western'],
                ]));

        // Not chosen, it is not offered
        $this->get('/mesas')
            ->assertInertia(fn (Assert $page) => $page->where('genres', [['value' => 'fantasy', 'label' => 'Fantasía']]));
    }

    public function test_the_tables_list_offers_the_active_genres_its_tables_have_in_catalog_order(): void
    {
        $this->table('Mesa de terror', ['genres' => ['mystery', 'horror']]);
        $this->table('Otra mesa de terror', ['genres' => ['horror']]);
        // Not in the list: a draft, a past table, a table nobody published
        $this->table('Borrador', ['genres' => ['western'], 'status' => 'draft', 'is_published' => false]);
        $this->table('Mesa pasada', ['genres' => ['comedy'], 'starts_at' => now()->subWeek()]);
        $this->table('Mesa sin publicar', ['genres' => ['cyberpunk'], 'is_published' => false, 'published_at' => null]);

        $this->get('/mesas')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('genres', [
                ['value' => 'horror', 'label' => 'Terror'],
                ['value' => 'mystery', 'label' => 'Misterio'],
            ]));
    }

    public function test_browsing_a_campaign_offers_only_the_genres_of_its_tables(): void
    {
        $campaign = $this->campaign('Campaña de terror');
        $this->table('Sesión pasada', ['genres' => ['horror'], 'campaign_id' => $campaign->id, 'starts_at' => now()->subWeek()]);
        // Listed on /mesas, but not part of the campaign
        $this->table('Mesa de fantasía', ['genres' => ['fantasy']]);

        $this->get('/mesas?campaign='.$campaign->id)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('tables.meta.total', 1)
                ->where('genres', [['value' => 'horror', 'label' => 'Terror']]));
    }

    public function test_without_genres_in_the_list_nothing_is_offered(): void
    {
        $this->table('Mesa sin géneros');

        $this->get('/mesas')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('genres', []));
    }

    public function test_the_campaigns_list_filters_and_offers_by_genre(): void
    {
        $this->campaign('Campaña de terror', ['genres' => ['horror']]);
        $this->campaign('Campaña de fantasía', ['genres' => ['fantasy', 'horror']]);
        $this->campaign('Campaña de misterio', ['genres' => ['mystery']]);
        $this->campaign('Campaña oculta', ['genres' => ['western'], 'is_published' => false]);

        $this->get('/campanas?genre=fantasy')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('campaigns.data', fn (Collection $campaigns): bool => $campaigns->pluck('title')->all() === ['Campaña de fantasía'])
                ->where('campaigns.meta.total', 1)
                ->where('currentFilters.genre', 'fantasy')
                ->where('genres', [
                    ['value' => 'fantasy', 'label' => 'Fantasía'],
                    ['value' => 'horror', 'label' => 'Terror'],
                    ['value' => 'mystery', 'label' => 'Misterio'],
                ]));
    }

    public function test_the_panel_tables_filter_by_any_genre_of_the_catalog(): void
    {
        $this->registerOnAdminPanel([GameTableResource::class]);
        $this->actingAsPanelAdmin();
        GenreModel::query()->where('key', 'western')->update(['is_active' => false]);
        $horror = $this->table('Mesa de terror', ['genres' => ['horror']]);
        $western = $this->table('Mesa del oeste', ['genres' => ['western']]);

        $list = Livewire::test(ListGameTables::class);
        $page = $list->instance();
        $this->assertInstanceOf(ListGameTables::class, $page);
        $filter = $page->getTable()->getFilter('genre');
        $this->assertInstanceOf(SelectFilter::class, $filter);
        $this->assertSame('Western', $filter->getOptions()['western'] ?? null);
        $this->assertCount(13, $filter->getOptions());

        $list->filterTable('genre', 'western')
            ->assertCanSeeTableRecords([$western])
            ->assertCanNotSeeTableRecords([$horror]);
    }

    public function test_the_panel_campaigns_filter_by_genre(): void
    {
        $this->registerOnAdminPanel([CampaignResource::class]);
        $this->actingAsPanelAdmin();
        $horror = $this->campaign('Campaña de terror', ['genres' => ['horror']]);
        $fantasy = $this->campaign('Campaña de fantasía', ['genres' => ['fantasy']]);

        Livewire::test(ListCampaigns::class)
            ->filterTable('genre', 'horror')
            ->assertCanSeeTableRecords([$horror])
            ->assertCanNotSeeTableRecords([$fantasy]);
    }

    /**
     * A table of the public listing unless the attributes say otherwise
     *
     * @param  array<string, mixed>  $attributes
     */
    private function table(string $title, array $attributes = []): GameTableModel
    {
        return GameTableModel::create([
            'id' => (string) Str::uuid(),
            'game_system_id' => $this->system->id,
            'created_by' => UserModel::factory()->create()->id,
            'title' => $title,
            'slug' => Str::slug($title).'-'.Str::random(4),
            'starts_at' => now()->addWeek(),
            'duration_minutes' => 240,
            'table_type' => 'one_shot',
            'table_format' => 'in_person',
            'location' => 'Local de la asociación',
            'status' => 'scheduled',
            'min_players' => 1,
            'max_players' => 5,
            'language' => 'es',
            'experience_level' => 'none',
            'character_creation' => 'pre_generated',
            'registration_type' => 'everyone',
            'auto_confirm' => true,
            'is_published' => true,
            'published_at' => now(),
            ...$attributes,
        ]);
    }

    /**
     * A campaign of the public listing unless the attributes say otherwise
     *
     * @param  array<string, mixed>  $attributes
     */
    private function campaign(string $title, array $attributes = []): CampaignModel
    {
        return CampaignModel::create([
            'id' => (string) Str::uuid(),
            'game_system_id' => $this->system->id,
            'created_by' => UserModel::factory()->create()->id,
            'title' => $title,
            'slug' => Str::slug($title).'-'.Str::random(4),
            'frequency' => 'weekly',
            'status' => 'active',
            'max_players' => 5,
            'roster_access' => 'anyone',
            'accepts_new_players' => true,
            'is_published' => true,
            ...$attributes,
        ]);
    }
}
