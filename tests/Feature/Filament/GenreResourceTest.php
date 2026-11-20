<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Feature\Filament;

use App\Infrastructure\Persistence\Eloquent\Models\PermissionModel;
use App\Infrastructure\Persistence\Eloquent\Models\RoleModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use App\Infrastructure\Search\Persistence\Eloquent\Models\SearchDocumentModel;
use Filament\Actions\Action;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Modules\GameTables\Database\Seeders\GenresSeeder;
use Modules\GameTables\Filament\Resources\GenreResource;
use Modules\GameTables\Filament\Resources\GenreResource\Pages\CreateGenre;
use Modules\GameTables\Filament\Resources\GenreResource\Pages\EditGenre;
use Modules\GameTables\Filament\Resources\GenreResource\Pages\ListGenres;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\CampaignModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameSystemModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameTableModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GenreModel;
use Tests\Support\Modules\InteractsWithModulePanel;
use Tests\Support\Modules\ModuleTestCase;

/**
 * The genres section of the panel: created, renamed, recoloured, ordered and deactivated there.
 * A genre some table or campaign has is never deleted, since they would lose it; the dialog says
 * who uses it and offers to deactivate it instead.
 */
final class GenreResourceTest extends ModuleTestCase
{
    use InteractsWithModulePanel;

    protected ?string $moduleName = 'game-tables';

    protected bool $autoEnableModule = true;

    private GameSystemModel $system;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registerOnAdminPanel([GenreResource::class]);
        $this->actingAsPanelAdmin();
        config()->set('modules.settings.game-tables', []);

        // database/ is outside the module's autoloaded src/
        require_once dirname(__DIR__, 3).'/database/seeders/GenresSeeder.php';
        $this->seed(GenresSeeder::class);

        $this->system = GameSystemModel::create([
            'id' => (string) Str::uuid(),
            'name' => 'Sistema de prueba',
            'slug' => 'sistema-de-prueba',
            'is_active' => true,
        ]);
    }

    public function test_the_list_shows_the_genres_in_their_order_with_their_uses(): void
    {
        $horror = $this->genre('horror');
        $this->table(['genres' => ['horror', 'mystery']]);
        $this->campaign(['genres' => ['horror']]);
        // A key that only starts like it is another genre
        $this->table(['genres' => ['horror_comedy']]);

        // The thirteen genres on one page
        Livewire::test(ListGenres::class)
            ->set('tableRecordsPerPage', 25)
            ->assertCanSeeTableRecords(GenreModel::query()->orderBy('sort_order')->get(), inOrder: true)
            ->assertTableColumnStateSet('usage', 2, $horror)
            ->assertTableColumnStateSet('usage', 0, $this->genre('western'));
    }

    public function test_the_genres_are_ordered_by_dragging(): void
    {
        $page = Livewire::test(ListGenres::class)->instance();
        $this->assertInstanceOf(ListGenres::class, $page);
        $table = $page->getTable();

        $this->assertTrue($table->isReorderable());
        $this->assertSame('sort_order', $table->getReorderColumn());
    }

    public function test_dragging_stores_the_new_order(): void
    {
        $keys = GenreModel::query()->orderBy('sort_order')->pluck('id', 'key')->all();
        $order = [$keys['horror'], $keys['fantasy'], ...array_values(array_diff($keys, [$keys['horror'], $keys['fantasy']]))];

        Livewire::test(ListGenres::class)
            ->call('toggleTableReordering')
            ->call('reorderTable', $order);

        $this->assertSame(1, $this->genre('horror')->sort_order);
        $this->assertSame(2, $this->genre('fantasy')->sort_order);
    }

    public function test_a_filtered_or_searched_list_cannot_be_reordered(): void
    {
        $searched = Livewire::test(ListGenres::class)->set('tableSearch', 'terror')->instance();
        $this->assertInstanceOf(ListGenres::class, $searched);
        $this->assertFalse($searched->getTable()->isReorderable());

        $filtered = Livewire::test(ListGenres::class)->set('tableFilters.is_active.value', '1')->instance();
        $this->assertInstanceOf(ListGenres::class, $filtered);
        $this->assertFalse($filtered->getTable()->isReorderable());
    }

    public function test_someone_who_only_sees_the_genres_cannot_reorder_them(): void
    {
        $this->actingAs($this->userWith('gametables:genres.view_any'));
        $keys = GenreModel::query()->orderBy('sort_order')->pluck('id', 'key')->all();

        $list = Livewire::test(ListGenres::class);
        $page = $list->instance();
        $this->assertInstanceOf(ListGenres::class, $page);
        $this->assertFalse($page->getTable()->isReorderable());

        try {
            $list->call('reorderTable', array_reverse(array_values($keys)));
        } catch (\Throwable) {
            // Refused either way
        }

        $this->assertSame(1, $this->genre('fantasy')->sort_order);
    }

    public function test_a_new_genre_takes_the_key_proposed_from_its_name_and_goes_last(): void
    {
        Livewire::test(CreateGenre::class)
            ->fillForm(['name' => 'Ciencia ficción dura'])
            ->assertSchemaStateSet(['key' => 'ciencia_ficcion_dura'])
            ->fillForm(['color' => 'info'])
            ->call('create')
            ->assertHasNoFormErrors();

        $genre = GenreModel::query()->where('key', 'ciencia_ficcion_dura')->sole();
        $this->assertSame('Ciencia ficción dura', $genre->name);
        $this->assertSame('info', $genre->color);
        $this->assertTrue($genre->is_active);
        $this->assertSame(14, $genre->sort_order);
    }

    public function test_a_key_typed_by_hand_is_kept_when_the_name_changes(): void
    {
        Livewire::test(CreateGenre::class)
            ->fillForm(['key' => 'grimdark'])
            ->fillForm(['name' => 'Fantasía oscura'])
            ->assertSchemaStateSet(['key' => 'grimdark']);
    }

    public function test_the_key_must_be_well_formed_and_unused(): void
    {
        Livewire::test(CreateGenre::class)
            ->fillForm(['name' => 'Ciencia ficción dura', 'key' => 'Sci-Fi', 'color' => 'info'])
            ->call('create')
            ->assertHasFormErrors(['key']);

        Livewire::test(CreateGenre::class)
            ->fillForm(['name' => 'Horror cósmico', 'key' => 'horror', 'color' => 'info'])
            ->call('create')
            ->assertHasFormErrors(['key' => 'unique']);

        $this->assertSame(13, GenreModel::query()->count());
    }

    public function test_the_name_must_be_unused_whatever_its_capitals(): void
    {
        Livewire::test(CreateGenre::class)
            ->fillForm(['name' => 'TERROR', 'key' => 'terror_2', 'color' => 'danger'])
            ->call('create')
            ->assertHasFormErrors(['name']);

        // Its own name is no clash when it is saved again
        Livewire::test(EditGenre::class, ['record' => $this->genre('horror')->getRouteKey()])
            ->fillForm(['name' => 'terror'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('terror', $this->genre('horror')->name);
    }

    public function test_the_colour_is_one_of_the_states_of_the_theme(): void
    {
        Livewire::test(CreateGenre::class)
            ->fillForm(['name' => 'Ciencia ficción dura', 'key' => 'ciencia_ficcion_dura', 'color' => 'purple'])
            ->call('create')
            ->assertHasFormErrors(['color']);
    }

    public function test_the_key_never_changes_once_the_genre_exists(): void
    {
        $horror = $this->genre('horror');

        Livewire::test(EditGenre::class, ['record' => $horror->getRouteKey()])
            ->assertFormFieldIsDisabled('key')
            ->fillForm(['key' => 'cosmic_horror', 'name' => 'Horror cósmico', 'color' => 'info'])
            ->call('save')
            ->assertHasNoFormErrors();

        $horror->refresh();
        $this->assertSame('horror', $horror->key);
        $this->assertSame('Horror cósmico', $horror->name);
        $this->assertSame('info', $horror->color);
    }

    public function test_renaming_a_genre_refreshes_the_search_documents_of_its_tables(): void
    {
        $table = $this->table(['genres' => ['horror']]);
        $this->assertStringContainsString('Terror', (string) $this->document($table)?->keywords);

        Livewire::test(EditGenre::class, ['record' => $this->genre('horror')->getRouteKey()])
            ->fillForm(['name' => 'Horror cósmico'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertStringContainsString('Horror cósmico', (string) $this->document($table)?->keywords);
    }

    public function test_an_unused_genre_is_deleted_only_with_both_confirmations(): void
    {
        $western = $this->genre('western');

        Livewire::test(EditGenre::class, ['record' => $western->getRouteKey()])
            ->callAction('delete')
            ->assertHasActionErrors(['confirmation']);
        $this->assertModelExists($western);

        Livewire::test(EditGenre::class, ['record' => $western->getRouteKey()])
            ->callAction('delete', data: ['confirmation' => true])
            ->assertHasNoActionErrors();
        $this->assertModelMissing($western);
    }

    public function test_a_genre_in_use_is_not_deleted_and_the_dialog_says_who_uses_it(): void
    {
        $horror = $this->genre('horror');
        $table = $this->table(['genres' => ['horror']]);
        $this->campaign(['genres' => ['horror']]);

        Livewire::test(EditGenre::class, ['record' => $horror->getRouteKey()])
            ->assertActionExists('delete', fn (Action $action): bool => $action->getModalDescription() === GenreResource::usageDescription($horror)
                && str_contains(GenreResource::usageDescription($horror) ?? '', '1 mesa y 1 campaña'))
            ->callAction('delete')
            ->assertNotified(__('game-tables::messages.genre_deletion.not_deleted'));

        $this->assertModelExists($horror);
        $this->assertSame(['horror'], $table->refresh()->genres);
    }

    public function test_a_genre_in_use_can_be_deactivated_from_the_delete_dialog(): void
    {
        $horror = $this->genre('horror');
        $this->table(['genres' => ['horror']]);

        Livewire::test(EditGenre::class, ['record' => $horror->getRouteKey()])
            ->callAction(TestAction::make('delete')->arguments(['deactivate' => true]))
            ->assertNotified(__('game-tables::messages.genre_deletion.deactivated'));

        $this->assertModelExists($horror);
        $this->assertFalse($horror->refresh()->is_active);
    }

    public function test_bulk_deletion_asks_for_both_confirmations_and_skips_the_genres_in_use(): void
    {
        $used = $this->genre('horror');
        $unused = $this->genre('western');
        $this->table(['genres' => ['horror']]);

        Livewire::test(ListGenres::class)
            ->callTableBulkAction('delete', [$used, $unused])
            ->assertHasTableBulkActionErrors(['confirmation']);
        $this->assertModelExists($unused);

        Livewire::test(ListGenres::class)
            ->callTableBulkAction('delete', [$used, $unused], data: ['confirmation' => true]);

        $this->assertModelExists($used);
        $this->assertModelMissing($unused);
    }

    public function test_a_genre_in_use_survives_a_delete_outside_the_panel(): void
    {
        $horror = $this->genre('horror');
        $this->campaign(['genres' => ['horror']]);

        $this->assertFalse($horror->delete());
        $this->assertModelExists($horror);
    }

    public function test_seeing_the_genres_and_managing_them_are_separate_permissions(): void
    {
        $horror = $this->genre('horror');

        $viewer = $this->userWith('gametables:genres.view_any');
        $this->assertTrue($viewer->can('viewAny', GenreModel::class));
        $this->assertFalse($viewer->can('create', GenreModel::class));
        $this->assertFalse($viewer->can('update', $horror));
        $this->assertFalse($viewer->can('delete', $horror));
        $this->assertFalse($viewer->can('reorder', GenreModel::class));

        $manager = $this->userWith('gametables:genres.view_any', 'gametables:genres.manage');
        $this->assertTrue($manager->can('create', GenreModel::class));
        $this->assertTrue($manager->can('update', $horror));
        $this->assertTrue($manager->can('delete', $horror));
        $this->assertTrue($manager->can('reorder', GenreModel::class));

        $this->assertFalse(UserModel::factory()->editor()->create()->can('viewAny', GenreModel::class));
    }

    private function genre(string $key): GenreModel
    {
        return GenreModel::query()->where('key', $key)->sole();
    }

    private function document(GameTableModel $table): ?SearchDocumentModel
    {
        return SearchDocumentModel::query()->where('type', 'gametables.tables')->where('source_id', $table->id)->first();
    }

    private function userWith(string ...$permissionKeys): UserModel
    {
        $role = RoleModel::query()->create(['name' => 'tester_'.uniqid(), 'display_name' => 'Permissions tester']);

        foreach ($permissionKeys as $key) {
            $permission = PermissionModel::query()->firstOrCreate(
                ['key' => $key],
                ['label' => $key, 'resource' => 'gametables', 'action' => 'test', 'module' => 'gametables'],
            );
            $role->permissions()->attach($permission->id);
        }

        $user = UserModel::factory()->editor()->create();
        $user->roles()->attach($role->id);

        return $user;
    }

    /**
     * A table of the public listing, so it has a search document
     *
     * @param  array<string, mixed>  $attributes
     */
    private function table(array $attributes = []): GameTableModel
    {
        return GameTableModel::create([
            'id' => (string) Str::uuid(),
            'game_system_id' => $this->system->id,
            'created_by' => UserModel::factory()->create()->id,
            'title' => 'Mesa de prueba',
            'slug' => Str::random(10),
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
     * @param  array<string, mixed>  $attributes
     */
    private function campaign(array $attributes = []): CampaignModel
    {
        return CampaignModel::create([
            'id' => (string) Str::uuid(),
            'game_system_id' => $this->system->id,
            'created_by' => UserModel::factory()->create()->id,
            'title' => 'Campaña de prueba',
            'slug' => Str::random(10),
            'frequency' => 'weekly',
            'status' => 'recruiting',
            'max_players' => 5,
            'is_published' => true,
            ...$attributes,
        ]);
    }
}
