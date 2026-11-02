<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Feature\Filament;

use Filament\Actions\Action;
use Filament\Actions\Testing\TestAction;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Modules\GameTables\Filament\Resources\GameSystemResource;
use Modules\GameTables\Filament\Resources\GameSystemResource\Pages\EditGameSystem;
use Modules\GameTables\Filament\Resources\GameSystemResource\Pages\ListGameSystems;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\CampaignModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameSystemModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameTableModel;
use Tests\Support\Modules\InteractsWithModulePanel;
use Tests\Support\Modules\ModuleTestCase;

/**
 * Deleting a game system used to take its tables and campaigns with it (cascade). A system in use
 * is never deleted: the panel explains why and offers to deactivate it instead.
 */
final class GameSystemDeletionTest extends ModuleTestCase
{
    use InteractsWithModulePanel;

    protected ?string $moduleName = 'game-tables';

    protected bool $autoEnableModule = true;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registerOnAdminPanel([GameSystemResource::class], []);
        $this->actingAsPanelAdmin();
    }

    public function test_an_unused_system_is_deleted(): void
    {
        $system = $this->system('sin-uso');

        Livewire::test(EditGameSystem::class, ['record' => $system->getRouteKey()])
            ->callAction('delete');

        $this->assertModelMissing($system);
    }

    public function test_a_system_in_use_is_not_deleted_and_its_tables_and_campaigns_stay(): void
    {
        $system = $this->system('en-uso');
        $table = $this->table($system);
        $campaign = $this->campaign($system);

        Livewire::test(EditGameSystem::class, ['record' => $system->getRouteKey()])
            ->assertActionExists('delete', fn (Action $action): bool => str_contains(GameSystemResource::usageDescription($system) ?? '', '1 mesa y 1 campaña')
                && $action->getModalDescription() === GameSystemResource::usageDescription($system))
            ->callAction('delete')
            ->assertNotified(__('game-tables::messages.game_system_deletion.not_deleted'));

        $this->assertModelExists($system);
        $this->assertModelExists($table);
        $this->assertModelExists($campaign);
    }

    public function test_a_system_in_use_can_be_deactivated_from_the_delete_dialog(): void
    {
        $system = $this->system('en-uso');
        $this->table($system);

        Livewire::test(EditGameSystem::class, ['record' => $system->getRouteKey()])
            ->callAction(TestAction::make('delete')->arguments(['deactivate' => true]))
            ->assertNotified(__('game-tables::messages.game_system_deletion.deactivated'));

        $this->assertModelExists($system);
        $this->assertFalse($system->refresh()->is_active);
    }

    public function test_bulk_deletion_skips_the_systems_in_use(): void
    {
        $used = $this->system('en-uso');
        $this->campaign($used);
        $unused = $this->system('sin-uso');

        Livewire::test(ListGameSystems::class)
            ->callTableBulkAction('delete', [$used, $unused]);

        $this->assertModelExists($used);
        $this->assertModelMissing($unused);
    }

    // Outside the panel too: the model refuses, and the database refuses a raw delete
    public function test_a_system_in_use_survives_any_delete(): void
    {
        $system = $this->system('en-uso');
        $table = $this->table($system);

        $this->assertFalse($system->delete());
        $this->assertModelExists($system);

        try {
            // In a savepoint: PostgreSQL aborts the test's transaction on the refused delete
            DB::transaction(fn (): mixed => GameSystemModel::query()->whereKey($system->id)->delete());
            $this->fail('The database deleted a game system in use');
        } catch (QueryException) {
        }

        $this->assertModelExists($system);
        $this->assertModelExists($table);
    }

    private function system(string $slug): GameSystemModel
    {
        return GameSystemModel::create([
            'id' => (string) Str::uuid(),
            'name' => 'Sistema '.$slug,
            'slug' => $slug,
            'is_active' => true,
        ]);
    }

    private function table(GameSystemModel $system): GameTableModel
    {
        return GameTableModel::create([
            'id' => (string) Str::uuid(),
            'game_system_id' => $system->id,
            'created_by' => auth()->id(),
            'title' => 'Mesa de prueba',
            'slug' => 'mesa-de-prueba-'.Str::lower(Str::random(6)),
            'starts_at' => now()->addWeek(),
            'duration_minutes' => 240,
            'table_type' => 'one_shot',
            'table_format' => 'in_person',
            'status' => 'scheduled',
            'min_players' => 2,
            'max_players' => 5,
            'language' => 'es',
        ]);
    }

    private function campaign(GameSystemModel $system): CampaignModel
    {
        return CampaignModel::create([
            'id' => (string) Str::uuid(),
            'game_system_id' => $system->id,
            'created_by' => auth()->id(),
            'title' => 'Campaña de prueba',
            'slug' => 'campana-de-prueba-'.Str::lower(Str::random(6)),
            'status' => 'recruiting',
        ]);
    }
}
