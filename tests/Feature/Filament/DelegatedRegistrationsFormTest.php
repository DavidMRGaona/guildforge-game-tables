<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Feature\Filament;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Modules\GameTables\Filament\Resources\GameTableResource;
use Modules\GameTables\Filament\Resources\GameTableResource\Pages\CreateGameTable;
use Modules\GameTables\Filament\Resources\GameTableResource\Pages\EditGameTable;
use Modules\GameTables\Filament\Support\DelegatedDirectors;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\CampaignModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameMasterModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameSystemModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameTableModel;
use Tests\Support\Modules\InteractsWithModulePanel;
use Tests\Support\Modules\ModuleTestCase;

/**
 * Turning on «El director gestiona las inscripciones» needs every director linked to an account:
 * the panel refuses the save (nothing written) and names the first director without one.
 */
final class DelegatedRegistrationsFormTest extends ModuleTestCase
{
    use InteractsWithModulePanel;

    protected ?string $moduleName = 'game-tables';

    protected bool $autoEnableModule = true;

    private UserModel $admin;

    private GameSystemModel $gameSystem;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registerOnAdminPanel([GameTableResource::class]);
        $this->admin = $this->actingAsPanelAdmin();
        $this->gameSystem = GameSystemModel::create([
            'id' => (string) Str::uuid(),
            'name' => 'Sistema de prueba',
            'slug' => 'sistema-de-prueba',
            'is_active' => true,
        ]);
    }

    public function test_the_toggle_is_off_by_default_and_hidden_for_campaign_sessions(): void
    {
        $table = $this->gameTable();

        Livewire::test(EditGameTable::class, ['record' => $table->getRouteKey()])
            ->assertFormSet(['delegates_registrations' => false])
            ->assertFormFieldIsVisible('delegates_registrations');

        $campaign = CampaignModel::create([
            'id' => (string) Str::uuid(),
            'game_system_id' => $this->gameSystem->id,
            'created_by' => $this->admin->id,
            'title' => 'La llamada',
            'slug' => 'la-llamada',
            'status' => 'recruiting',
        ]);
        $session = $this->gameTable(['campaign_id' => $campaign->id, 'slug' => 'sesion']);

        Livewire::test(EditGameTable::class, ['record' => $session->getRouteKey()])
            ->assertFormFieldIsHidden('delegates_registrations');
    }

    public function test_saving_with_the_flag_on_and_an_external_director_is_refused(): void
    {
        $table = $this->gameTable();
        $this->director($table, ['first_name' => 'Jane', 'last_name' => 'Roe', 'email' => 'jane@example.com']);

        Livewire::test(EditGameTable::class, ['record' => $table->getRouteKey()])
            ->fillForm(['delegates_registrations' => true, 'title' => 'Otro título'])
            ->call('save')
            ->assertHasFormErrors(['gameMasters']);

        $table->refresh();
        $this->assertFalse($table->delegates_registrations);
        $this->assertSame('Mesa de prueba', $table->title);
    }

    public function test_saving_with_the_flag_on_and_only_linked_directors_works(): void
    {
        $table = $this->gameTable();
        $user = UserModel::factory()->create(['name' => 'John Doe', 'email' => 'john@example.com']);
        $this->director($table, ['user_id' => $user->id, 'first_name' => 'John', 'last_name' => 'Doe', 'email' => 'john@example.com']);

        Livewire::test(EditGameTable::class, ['record' => $table->getRouteKey()])
            ->fillForm(['delegates_registrations' => true])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertTrue($table->refresh()->delegates_registrations);
    }

    public function test_joining_a_campaign_drops_the_delegation(): void
    {
        $campaign = CampaignModel::create([
            'id' => (string) Str::uuid(),
            'game_system_id' => $this->gameSystem->id,
            'created_by' => $this->admin->id,
            'title' => 'La llamada',
            'slug' => 'la-llamada',
            'status' => 'recruiting',
        ]);
        $table = $this->gameTable(['delegates_registrations' => true]);
        $user = UserModel::factory()->create(['name' => 'John Doe', 'email' => 'john@example.com']);
        $this->director($table, ['user_id' => $user->id, 'first_name' => 'John', 'last_name' => 'Doe', 'email' => 'john@example.com']);

        Livewire::test(EditGameTable::class, ['record' => $table->getRouteKey()])
            ->fillForm(['campaign_id' => $campaign->id])
            ->call('save')
            ->assertHasNoFormErrors();

        $table->refresh();
        $this->assertSame($campaign->id, $table->campaign_id);
        $this->assertFalse($table->delegates_registrations);
    }

    public function test_the_flag_off_keeps_accepting_external_directors(): void
    {
        $table = $this->gameTable();
        $this->director($table, ['first_name' => 'Jane', 'last_name' => 'Roe', 'email' => 'jane@example.com']);

        Livewire::test(EditGameTable::class, ['record' => $table->getRouteKey()])
            ->fillForm(['title' => 'Otro título'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('Otro título', $table->refresh()->title);
    }

    public function test_creating_with_the_flag_on_and_an_external_director_writes_nothing(): void
    {
        Livewire::test(CreateGameTable::class)
            ->fillForm([
                'title' => 'Mesa nueva',
                'game_system_id' => $this->gameSystem->id,
                'starts_at' => now()->addWeek()->format('Y-m-d H:i:s'),
                'duration_minutes' => 120,
                'table_type' => 'one_shot',
                'table_format' => 'in_person',
                'min_players' => 1,
                'max_players' => 4,
                'language' => 'es',
                'experience_level' => 'none',
                'character_creation' => 'any',
                'delegates_registrations' => true,
                'gameMasters' => [[
                    'gm_type' => 'external',
                    'role' => 'main',
                    'first_name' => 'Jane',
                    'last_name' => 'Roe',
                    'email' => 'jane@example.com',
                ]],
            ])
            ->call('create')
            ->assertHasFormErrors(['gameMasters']);

        $this->assertSame(0, GameTableModel::query()->where('title', 'Mesa nueva')->count());
        $this->assertSame(0, GameMasterModel::query()->count());
    }

    public function test_excluded_and_inherited_rows_are_not_checked(): void
    {
        $rows = [
            ['user_id' => null, 'first_name' => 'Ana', 'last_name' => 'Roe', 'excluded' => true],
            ['user_id' => null, 'first_name' => 'Bob', 'last_name' => 'Roe', 'is_inherited' => true],
            ['user_id' => (string) Str::uuid(), 'first_name' => 'Cy'],
        ];

        $this->assertNull(DelegatedDirectors::firstWithoutAccount($rows));

        $rows[] = ['user_id' => '', 'first_name' => 'Dan', 'last_name' => 'Roe', 'email' => 'dan@example.com'];
        $this->assertSame('Dan Roe', DelegatedDirectors::firstWithoutAccount($rows));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function gameTable(array $attributes = []): GameTableModel
    {
        return GameTableModel::create([
            'id' => (string) Str::uuid(),
            'game_system_id' => $this->gameSystem->id,
            'created_by' => $this->admin->id,
            'title' => 'Mesa de prueba',
            'slug' => 'mesa-de-prueba',
            'starts_at' => now()->addWeek(),
            'duration_minutes' => 240,
            'table_type' => 'one_shot',
            'table_format' => 'in_person',
            'status' => 'scheduled',
            'min_players' => 2,
            'max_players' => 5,
            'language' => 'es',
            'experience_level' => 'none',
            'character_creation' => 'any',
            ...$attributes,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function director(GameTableModel $table, array $attributes): GameMasterModel
    {
        $gm = GameMasterModel::create([
            'id' => (string) Str::uuid(),
            'role' => 'main',
            'notify_by_email' => true,
            'is_name_public' => true,
            ...$attributes,
        ]);
        $table->gameMasters()->attach($gm->id, ['source' => 'local', 'excluded' => false, 'sort_order' => 1]);

        return $gm;
    }
}
