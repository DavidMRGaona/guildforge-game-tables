<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Feature\Filament;

use App\Infrastructure\Persistence\Eloquent\Models\PermissionModel;
use App\Infrastructure\Persistence\Eloquent\Models\RoleModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Filament\Actions\Testing\TestAction;
use Filament\Notifications\Notification;
use Illuminate\Support\Str;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Modules\GameTables\Filament\Resources\GameTableResource;
use Modules\GameTables\Filament\Resources\GameTableResource\Pages\EditGameTable;
use Modules\GameTables\Filament\Resources\GameTableResource\RelationManagers\ParticipantsRelationManager;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameSystemModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameTableModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\ParticipantModel;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\Modules\InteractsWithModulePanel;
use Tests\Support\Modules\ModuleTestCase;

/**
 * The panel's «Borrar» (row and bulk) on a table's participants: only cancelled, rejected or expired
 * registrations can be deleted; the others have to be cancelled first, which frees the seat
 * under the table lock and moves the waiting list.
 */
final class ParticipantsDeleteTest extends ModuleTestCase
{
    use InteractsWithModulePanel;

    protected ?string $moduleName = 'game-tables';

    protected bool $autoEnableModule = true;

    private GameSystemModel $gameSystem;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registerOnAdminPanel([GameTableResource::class]);
        $this->actingAsPanelAdmin();
        config()->set('modules.settings.game-tables', []);
        $this->gameSystem = GameSystemModel::create([
            'id' => (string) Str::uuid(),
            'name' => 'Sistema de prueba',
            'slug' => 'sistema-de-prueba',
            'is_active' => true,
        ]);
    }

    /**
     * @return array<string, array{bool, string}>
     */
    public static function deletableStatuses(): array
    {
        return self::byWaitingList(['cancelled', 'rejected', 'expired']);
    }

    /**
     * @return array<string, array{bool, string}>
     */
    public static function protectedStatuses(): array
    {
        return self::byWaitingList(['pending', 'confirmed', 'waiting_list', 'no_show']);
    }

    #[DataProvider('deletableStatuses')]
    public function test_a_cancelled_or_rejected_registration_can_be_deleted_from_its_row(bool $waitingList, string $status): void
    {
        $this->setWaitingList($waitingList);
        $table = $this->table();
        $row = $this->participant($table, $status);

        $this->participants($table)
            ->assertTableActionVisible('delete', $row)
            ->callTableAction('delete', $row)
            ->assertHasNoTableActionErrors();

        $this->assertModelMissing($row);
    }

    #[DataProvider('protectedStatuses')]
    public function test_other_registrations_do_not_get_the_row_delete(bool $waitingList, string $status): void
    {
        $this->setWaitingList($waitingList);
        $table = $this->table();
        $row = $this->participant($table, $status);

        $this->participants($table)->assertTableActionHidden('delete', $row);
    }

    // Hiding the button is not enough: the server must refuse the call as well
    #[DataProvider('protectedStatuses')]
    public function test_a_direct_delete_call_on_other_registrations_changes_nothing(bool $waitingList, string $status): void
    {
        $this->setWaitingList($waitingList);
        $table = $this->table();
        $row = $this->participant($table, $status);
        $context = ['table' => true, 'recordKey' => $row->getKey()];

        // Mounting it the normal way, then running it as if the client had mounted it anyway
        $this->participants($table)
            ->call('mountAction', 'delete', [], $context)
            ->assertSet('mountedActions', [])
            ->set('mountedActions', [['name' => 'delete', 'arguments' => [], 'context' => $context, 'data' => []]])
            ->call('callMountedAction');

        $this->assertModelExists($row);
        $this->assertSame($status, $row->refresh()->status->value);
    }

    #[DataProvider('waitingListSetting')]
    public function test_bulk_delete_removes_cancelled_and_rejected_registrations(bool $waitingList): void
    {
        $this->setWaitingList($waitingList);
        $table = $this->table();
        $cancelled = $this->participant($table, 'cancelled');
        $rejected = $this->participant($table, 'rejected');

        $this->participants($table)
            ->callTableBulkAction('delete', [$cancelled, $rejected])
            ->assertNotified(Notification::make()->success()->title('Se han borrado 2 inscripciones'));

        $this->assertModelMissing($cancelled);
        $this->assertModelMissing($rejected);
    }

    #[DataProvider('waitingListSetting')]
    public function test_bulk_delete_leaves_the_registrations_that_must_be_cancelled_first(bool $waitingList): void
    {
        $this->setWaitingList($waitingList);
        $table = $this->table();
        $cancelled = $this->participant($table, 'cancelled');
        $kept = [
            $this->participant($table, 'pending'),
            $this->participant($table, 'confirmed'),
            $this->participant($table, 'waiting_list', ['waiting_list_position' => 1]),
            $this->participant($table, 'no_show'),
        ];

        $this->participants($table)
            ->callTableBulkAction('delete', [$cancelled, ...$kept])
            ->assertNotified(
                Notification::make()
                    ->warning()
                    ->title('Se ha borrado 1 inscripción')
                    ->body('4 inscripciones no se han borrado: solo se pueden borrar las canceladas, rechazadas o caducadas; antes hay que cancelarlas con «Cancelar inscripción».'),
            );

        $this->assertModelMissing($cancelled);
        foreach ($kept as $row) {
            $this->assertModelExists($row);
        }
    }

    #[DataProvider('waitingListSetting')]
    public function test_bulk_delete_with_nothing_deletable_deletes_nothing_and_says_so(bool $waitingList): void
    {
        $this->setWaitingList($waitingList);
        $table = $this->table();
        $confirmed = $this->participant($table, 'confirmed');

        $this->participants($table)
            ->callTableBulkAction('delete', [$confirmed])
            ->assertNotified(
                Notification::make()
                    ->danger()
                    ->title('No se ha borrado ninguna inscripción')
                    ->body('1 inscripción no se ha borrado: solo se pueden borrar las canceladas, rechazadas o caducadas; antes hay que cancelarla con «Cancelar inscripción».'),
            );

        $this->assertModelExists($confirmed);
        $this->assertSame('confirmed', $confirmed->refresh()->status->value);
    }

    // A re-registration reactivates the same row (placeUser/placeGuest): if it lands after the
    // action read the row as cancelled, the delete must not remove the now active registration
    public function test_a_row_reactivated_after_it_was_read_is_not_deleted(): void
    {
        $table = $this->table();
        $row = $this->participant($table, 'cancelled');
        $component = $this->participants($table)->mountTableAction('delete', $row);
        $this->reactivateWhenRead($row);

        $component->callMountedAction()
            ->assertNotified('No se ha borrado: la inscripción ya no está cancelada, rechazada ni caducada');

        $this->assertModelExists($row);
        $this->assertSame('confirmed', $row->refresh()->status->value);
    }

    public function test_bulk_delete_does_not_remove_a_row_reactivated_after_it_was_read(): void
    {
        $table = $this->table();
        $row = $this->participant($table, 'cancelled');
        $this->reactivateWhenRead($row);

        $this->participants($table)
            ->callTableBulkAction('delete', [$row])
            ->assertNotified(
                Notification::make()
                    ->danger()
                    ->title('No se ha borrado ninguna inscripción')
                    ->body('1 inscripción no se ha borrado: solo se pueden borrar las canceladas, rechazadas o caducadas; antes hay que cancelarla con «Cancelar inscripción».'),
            );

        $this->assertModelExists($row);
        $this->assertSame('confirmed', $row->refresh()->status->value);
    }

    // Deleting is managing the participants: the same permission as the status actions
    public function test_an_editor_who_may_update_the_table_can_delete_cancelled_and_rejected_rows(): void
    {
        $table = $this->table();
        $cancelled = $this->participant($table, 'cancelled');
        $rejected = $this->participant($table, 'rejected');
        $bulkCancelled = $this->participant($table, 'cancelled');
        $this->actingAs($this->editorWith(['gametables:gametables.view_any', 'gametables:gametables.update']));

        $this->participants($table)
            ->assertTableActionVisible('delete', $cancelled)
            ->callTableAction('delete', $cancelled)
            ->callTableAction('delete', $rejected);
        $this->participants($table)->callTableBulkAction('delete', [$bulkCancelled]);

        $this->assertModelMissing($cancelled);
        $this->assertModelMissing($rejected);
        $this->assertModelMissing($bulkCancelled);
    }

    public function test_an_editor_without_the_update_permission_does_not_get_the_delete_actions(): void
    {
        $table = $this->table();
        $cancelled = $this->participant($table, 'cancelled');
        $this->actingAs($this->editorWith(['gametables:gametables.view_any', 'gametables:gametables.view']));

        $this->participants($table)
            ->assertTableActionHidden('delete', $cancelled)
            ->assertTableBulkActionHidden('delete');
    }

    // Hiding the buttons is not enough: the server must refuse the calls as well
    public function test_an_editor_without_the_update_permission_cannot_delete_by_calling_the_actions(): void
    {
        $table = $this->table();
        $cancelled = $this->participant($table, 'cancelled');
        $rejected = $this->participant($table, 'rejected');
        $this->actingAs($this->editorWith(['gametables:gametables.view_any', 'gametables:gametables.view']));
        $rowContext = ['table' => true, 'recordKey' => $cancelled->getKey()];
        $bulkContext = ['table' => true, 'bulk' => true];

        $this->participants($table)
            ->call('mountAction', 'delete', [], $rowContext)
            ->assertSet('mountedActions', [])
            ->set('mountedActions', [['name' => 'delete', 'arguments' => [], 'context' => $rowContext, 'data' => []]])
            ->call('callMountedAction');

        $this->participants($table)
            ->selectTableRecords([$cancelled->getKey(), $rejected->getKey()])
            ->mountAction(TestAction::make('delete')->table()->bulk())
            ->assertSet('mountedActions', [])
            ->set('mountedActions', [['name' => 'delete', 'arguments' => [], 'context' => $bulkContext, 'data' => []]])
            ->call('callMountedAction');

        $this->assertModelExists($cancelled);
        $this->assertModelExists($rejected);
    }

    /**
     * @return array<string, array{bool}>
     */
    public static function waitingListSetting(): array
    {
        return [
            'waiting list off' => [false],
            'waiting list on' => [true],
        ];
    }

    /**
     * @param  list<string>  $statuses
     * @return array<string, array{bool, string}>
     */
    private static function byWaitingList(array $statuses): array
    {
        $cases = [];
        foreach (['off' => false, 'on' => true] as $label => $waitingList) {
            foreach ($statuses as $status) {
                $cases["{$status}, waiting list {$label}"] = [$waitingList, $status];
            }
        }

        return $cases;
    }

    /**
     * @return Testable<ParticipantsRelationManager>
     */
    private function participants(GameTableModel $table): Testable
    {
        return Livewire::test(ParticipantsRelationManager::class, ['ownerRecord' => $table, 'pageClass' => EditGameTable::class]);
    }

    /**
     * @param  array<string>  $permissionKeys
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
     * Every time the row is loaded, its owner registers again in the database, behind the back of
     * the model just loaded (which still says cancelled).
     */
    private function reactivateWhenRead(ParticipantModel $row): void
    {
        ParticipantModel::retrieved(function (ParticipantModel $loaded) use ($row): void {
            if ($loaded->getKey() === $row->getKey()) {
                ParticipantModel::query()->whereKey($row->getKey())->update(['status' => 'confirmed', 'confirmed_at' => now()]);
            }
        });
    }

    private function setWaitingList(bool $enabled): void
    {
        config()->set('modules.settings.game-tables.registration.waiting_list_enabled', $enabled);
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
            'waiting_list_position' => $status === 'waiting_list' ? 1 : null,
            ...$attributes,
        ]);
    }

    private function table(): GameTableModel
    {
        return GameTableModel::create([
            'id' => (string) Str::uuid(),
            'game_system_id' => $this->gameSystem->id,
            'created_by' => UserModel::factory()->create()->id,
            'title' => 'Mesa de prueba',
            'slug' => 'mesa-de-prueba-'.Str::lower(Str::random(6)),
            'starts_at' => now()->subHour(),
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
        ]);
    }
}
