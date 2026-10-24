<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Feature\Filament;

use App\Infrastructure\Persistence\Eloquent\Models\PermissionModel;
use App\Infrastructure\Persistence\Eloquent\Models\RoleModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Modules\GameTables\Application\DTOs\RegisterParticipantDTO;
use Modules\GameTables\Application\Services\RegistrationServiceInterface;
use Modules\GameTables\Domain\Enums\ParticipantRole;
use Modules\GameTables\Filament\Resources\GameTableResource;
use Modules\GameTables\Filament\Resources\GameTableResource\Pages\EditGameTable;
use Modules\GameTables\Filament\Resources\GameTableResource\RelationManagers\ParticipantsRelationManager;
use Modules\GameTables\Filament\Resources\GameTableResource\RelationManagers\StatusLogRelationManager;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameSystemModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameTableModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\ParticipantModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\ParticipantStatusLogModel;
use Tests\Support\Modules\InteractsWithModulePanel;
use Tests\Support\Modules\ModuleTestCase;

/**
 * The registration history of a table in the panel: for staff only, read-only, newest first, with
 * the names read live from the accounts.
 */
final class StatusLogRelationManagerTest extends ModuleTestCase
{
    use InteractsWithModulePanel;

    protected ?string $moduleName = 'game-tables';

    protected bool $autoEnableModule = true;

    private UserModel $admin;

    private GameSystemModel $gameSystem;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        $this->registerOnAdminPanel([GameTableResource::class]);
        $this->admin = $this->actingAsPanelAdmin();
        config()->set('modules.settings.game-tables', []);
        $this->travelTo(CarbonImmutable::parse('2026-11-02 10:00:00'));
        $this->gameSystem = GameSystemModel::create([
            'id' => (string) Str::uuid(),
            'name' => 'Sistema de prueba',
            'slug' => 'sistema-de-prueba',
            'is_active' => true,
        ]);
    }

    public function test_the_tab_is_registered_on_the_table(): void
    {
        $this->assertContains(StatusLogRelationManager::class, GameTableResource::getRelations());
    }

    public function test_a_panel_rejection_logs_the_admin_and_the_reason(): void
    {
        $table = $this->table(['auto_confirm' => false]);
        $pending = $this->participant($table, 'pending');

        Livewire::test(ParticipantsRelationManager::class, ['ownerRecord' => $table, 'pageClass' => EditGameTable::class])
            ->callTableAction('reject', $pending, data: ['reason' => 'Mesa completa'])
            ->assertHasNoTableActionErrors();

        $row = ParticipantStatusLogModel::query()->where('to_status', 'rejected')->sole();
        $this->assertSame('pending', $row->from_status);
        $this->assertSame($this->admin->id, $row->actor_user_id);
        $this->assertFalse($row->actor_is_system);
        $this->assertSame('Mesa completa', $row->reason);
    }

    public function test_an_editor_who_may_update_the_table_sees_the_tab(): void
    {
        $table = $this->table();
        $this->actingAs($this->editorWith(['gametables:gametables.view_any', 'gametables:gametables.update']));

        $this->assertTrue(StatusLogRelationManager::canViewForRecord($table, EditGameTable::class));
        $this->history($table)->assertSuccessful();
    }

    public function test_an_editor_who_may_only_view_the_table_does_not_see_the_tab(): void
    {
        $table = $this->table();
        $this->actingAs($this->editorWith(['gametables:gametables.view_any', 'gametables:gametables.view']));

        $this->assertFalse(StatusLogRelationManager::canViewForRecord($table, EditGameTable::class));
    }

    public function test_the_history_is_read_only_and_newest_first(): void
    {
        $table = $this->table();
        $participant = $this->participant($table, 'pending');
        $this->travel(1)->hours();
        $participant->update(['status' => 'confirmed', 'confirmed_at' => now()]);
        [$newest, $oldest] = [
            ParticipantStatusLogModel::query()->where('to_status', 'confirmed')->sole(),
            ParticipantStatusLogModel::query()->where('to_status', 'pending')->sole(),
        ];

        $this->history($table)
            ->assertCanSeeTableRecords([$newest, $oldest], inOrder: true)
            ->assertTableActionDoesNotExist('create')
            ->assertTableActionDoesNotExist('edit')
            ->assertTableActionDoesNotExist('delete')
            ->assertTableBulkActionDoesNotExist('delete')
            ->assertTableColumnStateSet('change', 'Inscripción → Pendiente', $oldest)
            ->assertTableColumnStateSet('change', 'Pendiente → Confirmado', $newest);
    }

    public function test_an_anonymized_actor_reads_as_the_anonymized_name(): void
    {
        $table = $this->table();
        $actor = UserModel::factory()->create(['name' => 'John Doe']);
        $this->actingAs($actor);
        $this->participant($table, 'confirmed', ['user_id' => $actor->id]);
        $row = ParticipantStatusLogModel::query()->sole();

        $actor->update(['name' => 'Anónimo', 'anonymized_at' => now()]);
        $this->actingAs($this->admin);

        $this->history($table)
            ->assertTableColumnStateSet('actor', 'Anónimo', $row)
            ->assertTableColumnStateSet('participant', 'Anónimo', $row)
            ->assertDontSee('John Doe');
    }

    public function test_a_taken_over_guest_reads_as_the_anonymized_account(): void
    {
        $table = $this->table();
        app(RegistrationServiceInterface::class)->registerGuestByAdmin(new RegisterParticipantDTO(
            gameTableId: $table->id,
            userId: null,
            role: ParticipantRole::Player,
            firstName: 'Jane',
            lastName: 'Roe',
            email: 'john@example.test',
        ));
        $john = UserModel::factory()->create(['name' => 'John Doe', 'email' => 'john@example.test']);
        app(RegistrationServiceInterface::class)->register(new RegisterParticipantDTO(
            gameTableId: $table->id,
            userId: $john->id,
            role: ParticipantRole::Player,
        ));

        $john->update(['name' => 'Anónimo', 'anonymized_at' => now()]);

        $history = $this->history($table);
        foreach (ParticipantStatusLogModel::query()->get() as $row) {
            $history->assertTableColumnStateSet('participant', 'Anónimo', $row);
        }
        $history->assertDontSee('Jane Roe')->assertDontSee('John Doe');
    }

    public function test_system_guest_and_deleted_account_rows(): void
    {
        $table = $this->table();
        $system = $this->logRow($table, ['actor_is_system' => true, 'guest_name' => 'Jane Roe']);
        $deleted = $this->logRow($table, ['actor_is_system' => false]);

        $this->history($table)
            ->assertTableColumnStateSet('actor', 'Sistema', $system)
            ->assertTableColumnStateSet('participant', 'Jane Roe', $system)
            ->assertTableColumnStateSet('actor', 'Cuenta eliminada', $deleted)
            ->assertTableColumnStateSet('participant', 'Cuenta eliminada', $deleted);
    }

    /**
     * @return Testable<StatusLogRelationManager>
     */
    private function history(GameTableModel $table): Testable
    {
        return Livewire::test(StatusLogRelationManager::class, ['ownerRecord' => $table, 'pageClass' => EditGameTable::class]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function logRow(GameTableModel $table, array $attributes): ParticipantStatusLogModel
    {
        return ParticipantStatusLogModel::query()->create([
            'game_table_id' => $table->id,
            'from_status' => null,
            'to_status' => 'confirmed',
            ...$attributes,
        ]);
    }

    /**
     * @param  list<string>  $permissionKeys
     */
    private function editorWith(array $permissionKeys): UserModel
    {
        $role = RoleModel::create(['name' => 'tester_'.uniqid(), 'display_name' => 'Permissions tester']);

        foreach ($permissionKeys as $key) {
            $permission = PermissionModel::firstOrCreate(
                ['key' => $key],
                ['label' => $key, 'resource' => 'gametables', 'action' => Str::afterLast($key, '.'), 'module' => 'gametables'],
            );
            $role->permissions()->attach($permission->id);
        }

        $user = UserModel::factory()->editor()->create();
        $user->roles()->attach($role->id);

        return $user;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function participant(GameTableModel $table, string $status, array $attributes = []): ParticipantModel
    {
        return ParticipantModel::create([
            'id' => (string) Str::uuid(),
            'game_table_id' => $table->id,
            'user_id' => UserModel::factory()->create()->id,
            'role' => 'player',
            'status' => $status,
            'confirmed_at' => $status === 'confirmed' ? now() : null,
            ...$attributes,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function table(array $attributes = []): GameTableModel
    {
        return GameTableModel::create([
            'id' => (string) Str::uuid(),
            'game_system_id' => $this->gameSystem->id,
            'created_by' => $this->admin->id,
            'title' => 'Mesa de prueba',
            'slug' => 'mesa-de-prueba-'.Str::lower(Str::random(6)),
            'starts_at' => now()->addWeek(),
            'duration_minutes' => 240,
            'table_type' => 'one_shot',
            'table_format' => 'in_person',
            'status' => 'scheduled',
            'min_players' => 1,
            'max_players' => 5,
            'max_spectators' => 2,
            'language' => 'es',
            'registration_type' => 'everyone',
            'auto_confirm' => true,
            'is_published' => true,
            ...$attributes,
        ]);
    }
}
