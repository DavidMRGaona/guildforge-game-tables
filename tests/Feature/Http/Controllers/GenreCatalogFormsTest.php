<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Feature\Http\Controllers;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Filament\Forms\Components\CheckboxList;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Livewire\Livewire;
use Modules\GameTables\Database\Seeders\GenresSeeder;
use Modules\GameTables\Filament\Resources\CampaignResource;
use Modules\GameTables\Filament\Resources\CampaignResource\Pages\EditCampaign;
use Modules\GameTables\Filament\Resources\GameTableResource;
use Modules\GameTables\Filament\Resources\GameTableResource\Pages\EditGameTable;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\CampaignModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameSystemModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameTableModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GenreModel;
use Tests\Support\Modules\InteractsWithModulePanel;
use Tests\Support\Modules\ModuleTestCase;

/**
 * The forms offer and accept the active genres of the catalog, plus the ones a table or
 * campaign already has, so a genre taken out of the catalog never stops anyone from saving
 * what they had. Pages show the genres with the catalog's names, whatever they store.
 */
final class GenreCatalogFormsTest extends ModuleTestCase
{
    use InteractsWithModulePanel;

    /** The keys of the seeded genres, in their order */
    private const array ALL_KEYS = ['fantasy', 'horror', 'sci_fi', 'post_apocalyptic', 'cyberpunk', 'steampunk', 'historical', 'modern', 'superhero', 'mystery', 'western', 'comedy', 'other'];

    protected ?string $moduleName = 'game-tables';

    protected bool $autoEnableModule = true;

    private UserModel $author;

    private GameSystemModel $gameSystem;

    protected function setUp(): void
    {
        parent::setUp();

        app('router')->getRoutes()->refreshNameLookups();
        config()->set('modules.settings.game-tables', [
            'frontend_creation' => [
                'enabled' => true,
                'access_level' => 'registered',
                'allowed_content' => 'both',
            ],
        ]);

        // database/ is outside the module's autoloaded src/
        require_once dirname(__DIR__, 4).'/database/seeders/GenresSeeder.php';
        $this->seed(GenresSeeder::class);
        GenreModel::query()->where('key', 'western')->update(['is_active' => false]);

        $this->author = UserModel::factory()->create();
        $this->gameSystem = GameSystemModel::create([
            'id' => (string) Str::uuid(),
            'name' => 'Sistema de prueba',
            'slug' => 'sistema-de-prueba',
            'is_active' => true,
        ]);
    }

    public function test_a_new_table_takes_active_genres(): void
    {
        $this->actingAs($this->author)
            ->post('/mesas/crear', [...$this->tableData('Mesa nueva'), 'genres' => ['horror', 'mystery']])
            ->assertSessionHasNoErrors();

        $this->assertSame(['horror', 'mystery'], GameTableModel::query()->where('title', 'Mesa nueva')->sole()->genres);
    }

    public function test_a_new_table_cannot_take_an_inactive_or_unknown_genre(): void
    {
        $this->actingAs($this->author)
            ->post('/mesas/crear', [...$this->tableData('Mesa nueva'), 'genres' => ['horror', 'western', 'space_opera']])
            ->assertSessionHasErrors(['genres.1', 'genres.2'])
            ->assertSessionDoesntHaveErrors('genres.0');

        // The usual message, with the field's name, never the translation key
        $message = session('errors')?->first('genres.1');
        $this->assertIsString($message);
        $this->assertStringContainsString('género', $message);
        $this->assertStringNotContainsString('validation.', $message);

        $this->assertFalse(GameTableModel::query()->where('title', 'Mesa nueva')->exists());
    }

    public function test_editing_a_table_keeps_an_inactive_genre_it_already_had(): void
    {
        $table = $this->tableDraft(['genres' => ['western', 'horror']]);

        $this->actingAs($this->author)
            ->put("/mesas/mis-mesas/{$table->id}", [...$this->tableData('Mesa del oeste'), 'genres' => ['western', 'mystery']])
            ->assertSessionHasNoErrors();

        $this->assertSame(['western', 'mystery'], $table->refresh()->genres);
    }

    public function test_editing_a_table_cannot_add_an_inactive_genre_it_did_not_have(): void
    {
        $table = $this->tableDraft(['genres' => ['horror']]);

        $this->actingAs($this->author)
            ->put("/mesas/mis-mesas/{$table->id}", [...$this->tableData('Mesa de terror'), 'genres' => ['horror', 'western']])
            ->assertSessionHasErrors('genres.1');

        $this->assertSame(['horror'], $table->refresh()->genres);
    }

    public function test_someone_who_may_not_edit_a_table_gets_no_kept_genre(): void
    {
        $table = $this->tableDraft(['genres' => ['western']]);

        // Answered like an unknown genre, so the answer never tells which genres the table has
        $this->actingAs(UserModel::factory()->create())
            ->put("/mesas/mis-mesas/{$table->id}", [...$this->tableData('Mesa ajena'), 'genres' => ['western']])
            ->assertSessionHasErrors('genres.0');

        $this->assertSame(['western'], $table->refresh()->genres);
    }

    public function test_someone_else_editing_a_campaign_gets_no_kept_genre(): void
    {
        $campaign = $this->campaignDraft(['genres' => ['western']]);

        $this->actingAs(UserModel::factory()->create())
            ->put("/campanas/mis-campanas/{$campaign->id}", [...$this->campaignData('Campaña ajena'), 'genres' => ['western']])
            ->assertSessionHasErrors('genres.0');

        $this->assertSame(['western'], $campaign->refresh()->genres);
    }

    public function test_the_create_form_offers_only_the_active_genres_in_their_order(): void
    {
        $this->actingAs($this->author)
            ->get('/mesas/crear')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('formData.genres', fn ($genres): bool => array_keys($genres->all()) === $this->activeKeys())
                ->where('formData.genres.horror', 'Terror'));
    }

    public function test_the_edit_form_also_offers_the_inactive_genre_the_table_has(): void
    {
        $table = $this->tableDraft(['genres' => ['western']]);

        $this->actingAs($this->author)
            ->get("/mesas/mis-mesas/{$table->id}/editar")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('formData.genres.western', 'Western')
                ->where('formData.genres', fn ($genres): bool => count($genres) === count($this->activeKeys()) + 1));
    }

    public function test_a_new_campaign_cannot_take_an_inactive_genre(): void
    {
        $this->actingAs($this->author)
            ->post('/campanas/crear', [...$this->campaignData('Campaña nueva'), 'genres' => ['western']])
            ->assertSessionHasErrors('genres.0');

        $this->assertFalse(CampaignModel::query()->where('title', 'Campaña nueva')->exists());
    }

    public function test_editing_a_campaign_keeps_an_inactive_genre_it_already_had(): void
    {
        $campaign = $this->campaignDraft(['genres' => ['western']]);

        $this->actingAs($this->author)
            ->get("/campanas/mis-campanas/{$campaign->id}/editar")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('formData.genres.western', 'Western'));

        $this->actingAs($this->author)
            ->put("/campanas/mis-campanas/{$campaign->id}", [...$this->campaignData('Campaña del oeste'), 'genres' => ['western', 'horror']])
            ->assertSessionHasNoErrors();

        $this->assertSame(['western', 'horror'], $campaign->refresh()->genres);
    }

    public function test_the_table_page_shows_the_catalog_names_and_leaves_out_unknown_keys(): void
    {
        GenreModel::query()->where('key', 'horror')->update(['name' => 'Horror cósmico']);
        $table = $this->publishedTable(['genres' => ['western', 'deleted_genre', 'horror']]);

        $this->get('/mesas/'.$table->slug)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('GameTables/Show', shouldExist: false)
                ->where('table.genres', ['Horror cósmico', 'Western']));
    }

    public function test_the_campaign_page_shows_the_catalog_names_and_leaves_out_unknown_keys(): void
    {
        $campaign = $this->campaignDraft([
            'status' => 'active',
            'frontend_creation_status' => 'approved',
            'is_published' => true,
            'accepts_new_players' => true,
            'roster_access' => 'anyone',
            'genres' => ['mystery', 'deleted_genre', 'western'],
        ]);

        $this->get('/campanas/'.$campaign->slug)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Campaigns/Show', shouldExist: false)
                ->where('campaign.genres', ['Misterio', 'Western']));
    }

    public function test_the_panel_table_form_offers_the_active_genres_and_those_the_table_has(): void
    {
        $this->registerOnAdminPanel([GameTableResource::class]);
        $this->actingAsPanelAdmin();
        $table = $this->publishedTable(['genres' => ['western']]);

        Livewire::test(EditGameTable::class, ['record' => $table->getRouteKey()])
            ->assertFormFieldExists('genres', fn (CheckboxList $field): bool => array_keys($field->getOptions()) === self::ALL_KEYS);

        $other = $this->publishedTable(['slug' => 'otra-mesa', 'genres' => ['horror']]);

        Livewire::test(EditGameTable::class, ['record' => $other->getRouteKey()])
            ->assertFormFieldExists('genres', fn (CheckboxList $field): bool => array_keys($field->getOptions()) === $this->activeKeys());
    }

    public function test_the_panel_campaign_form_keeps_an_inactive_genre_and_refuses_a_new_one(): void
    {
        $this->registerOnAdminPanel([CampaignResource::class]);
        $this->actingAsPanelAdmin();
        $campaign = $this->campaignDraft(['genres' => ['western'], 'is_published' => true]);
        $other = $this->campaignDraft(['slug' => 'otra-campana', 'genres' => ['horror'], 'is_published' => true]);

        Livewire::test(EditCampaign::class, ['record' => $campaign->getRouteKey()])
            ->fillForm(['genres' => ['western', 'horror']])
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertSame(['western', 'horror'], $campaign->refresh()->genres);

        Livewire::test(EditCampaign::class, ['record' => $other->getRouteKey()])
            ->fillForm(['genres' => ['horror', 'western']])
            ->call('save')
            ->assertHasFormErrors(['genres.1']);
        $this->assertSame(['horror'], $other->refresh()->genres);
    }

    /**
     * The keys of the active seeded genres, in their order
     *
     * @return list<string>
     */
    private function activeKeys(): array
    {
        return array_values(array_diff(self::ALL_KEYS, ['western']));
    }

    /**
     * @return array<string, mixed>
     */
    private function tableData(string $title): array
    {
        return [
            'game_system_id' => $this->gameSystem->id,
            'title' => $title,
            'starts_at' => now()->addWeeks(2)->toIso8601String(),
            'duration_minutes' => 240,
            'table_type' => 'one_shot',
            'table_format' => 'in_person',
            'location' => 'Local de la asociación',
            'min_players' => 2,
            'max_players' => 5,
            'language' => 'es',
            'experience_level' => 'none',
            'character_creation' => 'pre_generated',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function campaignData(string $title): array
    {
        return [
            'game_system_id' => $this->gameSystem->id,
            'title' => $title,
            'frequency' => 'weekly',
            'min_players' => 2,
            'max_players' => 5,
            'table_format' => 'in_person',
            'location' => 'Local de la asociación',
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function campaignDraft(array $attributes = []): CampaignModel
    {
        return CampaignModel::create([
            'id' => (string) Str::uuid(),
            'game_system_id' => $this->gameSystem->id,
            'created_by' => $this->author->id,
            'title' => 'Campaña en borrador',
            'slug' => 'campana-en-borrador',
            'frequency' => 'weekly',
            'status' => 'recruiting',
            'max_players' => 5,
            'location' => 'Local de la asociación',
            'is_published' => false,
            'frontend_creation_status' => 'draft',
            ...$attributes,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function tableDraft(array $attributes = []): GameTableModel
    {
        return GameTableModel::create([
            'id' => (string) Str::uuid(),
            'game_system_id' => $this->gameSystem->id,
            'created_by' => $this->author->id,
            'title' => 'Mesa en borrador',
            'slug' => 'mesa-en-borrador',
            'starts_at' => now()->addWeek(),
            'duration_minutes' => 240,
            'table_type' => 'one_shot',
            'table_format' => 'in_person',
            'location' => 'Local de la asociación',
            'status' => 'draft',
            'min_players' => 1,
            'max_players' => 5,
            'language' => 'es',
            'registration_type' => 'everyone',
            'auto_confirm' => true,
            'is_published' => false,
            'frontend_creation_status' => 'draft',
            ...$attributes,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function publishedTable(array $attributes = []): GameTableModel
    {
        return $this->tableDraft([
            'slug' => 'mesa-publicada',
            'status' => 'scheduled',
            'is_published' => true,
            'published_at' => now(),
            'frontend_creation_status' => null,
            'experience_level' => 'none',
            'character_creation' => 'pre_generated',
            ...$attributes,
        ]);
    }
}
