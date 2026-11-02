<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Feature\Http\Controllers;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Modules\GameTables\Filament\Resources\GameTableResource;
use Modules\GameTables\Filament\Resources\GameTableResource\Pages\EditGameTable;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameSystemModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameTableModel;
use Tests\Support\Modules\InteractsWithModulePanel;
use Tests\Support\Modules\ModuleTestCase;

/**
 * The web, the panel and the database accept the same limits: 500 characters for the location and
 * the online link (as campaigns, whose location a session may take), and a minimum age of 1 to 99.
 * Past them, the field says so instead of the save failing.
 */
final class TableFieldLimitsTest extends ModuleTestCase
{
    use InteractsWithModulePanel;

    protected ?string $moduleName = 'game-tables';

    protected bool $autoEnableModule = true;

    private UserModel $author;

    private GameSystemModel $gameSystem;

    protected function setUp(): void
    {
        parent::setUp();

        app('router')->getRoutes()->refreshNameLookups();
        app()->setLocale('es');
        config()->set('modules.settings.game-tables', [
            'frontend_creation' => [
                'enabled' => true,
                'access_level' => 'registered',
                'allowed_content' => 'both',
                'publication' => ['mode' => 'approval'],
            ],
        ]);

        $this->author = UserModel::factory()->create();
        $this->gameSystem = GameSystemModel::create([
            'id' => (string) Str::uuid(),
            'name' => 'Sistema de prueba',
            'slug' => 'sistema-de-prueba',
            'is_active' => true,
        ]);
    }

    public function test_the_web_saves_a_location_and_a_link_of_500_characters(): void
    {
        $location = str_repeat('a', 500);
        $link = 'https://example.com/'.str_repeat('b', 480);

        $this->actingAs($this->author)
            ->post('/mesas/crear', [...$this->tableData(), 'table_format' => 'hybrid', 'location' => $location, 'online_url' => $link])
            ->assertSessionHasNoErrors();

        $table = GameTableModel::query()->sole();
        $this->assertSame($location, $table->location);
        $this->assertSame($link, $table->online_url);
    }

    public function test_the_web_refuses_a_longer_location_on_its_field(): void
    {
        $this->actingAs($this->author)
            ->post('/mesas/crear', [...$this->tableData(), 'location' => str_repeat('a', 501)])
            ->assertSessionHasErrors('location');

        $this->assertSame(0, GameTableModel::query()->count());
    }

    public function test_the_web_takes_a_minimum_age_from_1_to_99(): void
    {
        foreach ([0 => true, 1 => false, 99 => false, 100 => true] as $age => $refused) {
            $response = $this->actingAs($this->author)
                ->post('/mesas/crear', [...$this->tableData('Mesa de '.$age), 'minimum_age' => $age]);

            $refused
                ? $response->assertSessionHasErrors('minimum_age')
                : $response->assertSessionHasNoErrors();
        }
    }

    public function test_the_panel_saves_a_location_of_500_characters(): void
    {
        $this->registerOnAdminPanel([GameTableResource::class], []);
        $this->actingAsPanelAdmin();
        $location = str_repeat('a', 500);

        $table = $this->storedTable(18);

        Livewire::test(EditGameTable::class, ['record' => $table->getRouteKey()])
            ->fillForm(['location' => $location])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame($location, GameTableModel::query()->sole()->location);
    }

    // A 0 saved from the web meant no requirement: it becomes empty, so the panel accepts the table
    public function test_the_migration_empties_a_minimum_age_of_zero(): void
    {
        $zero = $this->storedTable(0);
        $adult = $this->storedTable(18);

        $migration = require __DIR__.'/../../../../database/migrations/2026_11_02_000003_align_location_link_and_minimum_age_of_gametables_tables.php';
        if (! $migration instanceof Migration || ! method_exists($migration, 'up')) {
            $this->fail('The migration has no up()');
        }

        $migration->up();

        $this->assertNull($zero->refresh()->minimum_age);
        $this->assertSame(18, $adult->refresh()->minimum_age);
    }

    /**
     * @return array<string, mixed>
     */
    private function tableData(string $title = 'Mesa nueva'): array
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

    private function storedTable(int $minimumAge): GameTableModel
    {
        return GameTableModel::create([
            'id' => (string) Str::uuid(),
            'game_system_id' => $this->gameSystem->id,
            'created_by' => $this->author->id,
            'title' => 'Mesa',
            'slug' => 'mesa-'.Str::lower(Str::random(6)),
            'starts_at' => now()->addWeek(),
            'duration_minutes' => 240,
            'table_type' => 'one_shot',
            'table_format' => 'in_person',
            'status' => 'scheduled',
            'min_players' => 2,
            'max_players' => 5,
            'language' => 'es',
            'experience_level' => 'none',
            'character_creation' => 'pre_generated',
            'minimum_age' => $minimumAge,
        ]);
    }
}
