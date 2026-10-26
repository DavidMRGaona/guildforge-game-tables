<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Feature\Filament;

use App\Infrastructure\Persistence\Eloquent\Models\PermissionModel;
use App\Infrastructure\Persistence\Eloquent\Models\RoleModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Modules\GameTables\Filament\Resources\GameTableResource;
use Modules\GameTables\Filament\Resources\GameTableResource\Pages\EditGameTable;
use Modules\GameTables\Filament\Resources\GameTableResource\RelationManagers\ParticipantsRelationManager;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameSystemModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameTableModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\ParticipantModel;
use Modules\GameTables\Notifications\GuestRegistrationConfirmation;
use Tests\Support\Modules\InteractsWithModulePanel;
use Tests\Support\Modules\ModuleTestCase;

/**
 * The participants of a table in the panel: the «Email sin confirmar» and «Posible duplicado»
 * badges, the filter, and «Marcar email como confirmado».
 */
final class ParticipantsGuestConfirmationTest extends ModuleTestCase
{
    use InteractsWithModulePanel;

    protected ?string $moduleName = 'game-tables';

    protected bool $autoEnableModule = true;

    private const string FINGERPRINT = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    private GameSystemModel $gameSystem;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registerOnAdminPanel([GameTableResource::class]);
        $this->actingAsPanelAdmin();
        config()->set('modules.settings.game-tables', []);
        Notification::fake();
        $this->travelTo(CarbonImmutable::parse('2026-11-02 10:00:00', 'Europe/Madrid'));
        $this->gameSystem = GameSystemModel::create([
            'id' => (string) Str::uuid(),
            'name' => 'Sistema de prueba',
            'slug' => 'sistema-de-prueba',
            'is_active' => true,
        ]);
    }

    public function test_the_action_shows_only_on_active_guests_awaiting_confirmation(): void
    {
        $table = $this->table();
        $awaiting = $this->awaitingGuest($table);
        $confirmedGuest = $this->guest($table, ['email_confirmed_at' => now()]);
        $expired = $this->awaitingGuest($table, ['status' => 'expired', 'confirmation_token' => null]);
        // Rows of users never confirm an email, whatever the column holds
        $user = $this->participant($table, ['user_id' => UserModel::factory()->create()->id]);

        $this->participants($table)
            ->assertTableActionVisible('confirmEmail', $awaiting)
            ->assertTableActionHidden('confirmEmail', $confirmedGuest)
            ->assertTableActionHidden('confirmEmail', $expired)
            ->assertTableActionHidden('confirmEmail', $user);
    }

    public function test_the_action_asks_for_confirmation(): void
    {
        $table = $this->table();
        $awaiting = $this->awaitingGuest($table);

        $this->participants($table)->assertTableActionExists(
            'confirmEmail',
            fn (Action $action): bool => $action->isConfirmationRequired()
                && $action->getLabel() === 'Marcar email como confirmado'
                && $action->getModalDescription() === 'Se dará por confirmado el email del invitado, como si hubiera pulsado el enlace, y recibirá el email de inscripción.',
            $awaiting,
        );
    }

    public function test_the_action_confirms_the_email_and_sends_the_registration_email(): void
    {
        $table = $this->table();
        $awaiting = $this->awaitingGuest($table);

        $this->participants($table)->callTableAction('confirmEmail', $awaiting)->assertHasNoTableActionErrors();

        $awaiting->refresh();
        $this->assertSame('2026-11-02 10:00:00', $awaiting->email_confirmed_at?->setTimezone('Europe/Madrid')->format('Y-m-d H:i:s'));
        $this->assertNull($awaiting->confirmation_expires_at);
        Notification::assertSentOnDemandTimes(GuestRegistrationConfirmation::class, 1);
    }

    public function test_the_action_without_room_warns_and_changes_nothing(): void
    {
        $table = $this->table(['max_players' => 1]);
        $this->participant($table, ['user_id' => UserModel::factory()->create()->id, 'status' => 'confirmed', 'confirmed_at' => now()]);
        $awaiting = $this->awaitingGuest($table, ['status' => 'pending', 'confirmed_at' => null, 'confirmation_mode' => 'wait', 'confirmation_expires_at' => null]);

        $this->participants($table)
            ->callTableAction('confirmEmail', $awaiting)
            ->assertNotified('La mesa está completa');

        $awaiting->refresh();
        $this->assertSame('pending', $awaiting->status->value);
        $this->assertNull($awaiting->email_confirmed_at);
        Notification::assertNothingSent();
    }

    public function test_confirm_on_an_awaiting_guest_confirms_the_email_and_sends_the_registration_email(): void
    {
        $table = $this->table(['auto_confirm' => false]);
        $awaiting = $this->awaitingGuest($table, ['status' => 'pending', 'confirmed_at' => null]);

        $this->participants($table)->callTableAction('confirm', $awaiting)->assertHasNoTableActionErrors();

        $awaiting->refresh();
        $this->assertSame('confirmed', $awaiting->status->value);
        $this->assertSame('2026-11-02 10:00:00', $awaiting->email_confirmed_at?->setTimezone('Europe/Madrid')->format('Y-m-d H:i:s'));
        Notification::assertSentOnDemandTimes(GuestRegistrationConfirmation::class, 1);
    }

    public function test_confirm_on_a_wait_guest_without_a_seat_warns_and_changes_nothing(): void
    {
        $table = $this->table(['auto_confirm' => false, 'max_players' => 1]);
        $this->participant($table, ['user_id' => UserModel::factory()->create()->id, 'status' => 'confirmed', 'confirmed_at' => now()]);
        $awaiting = $this->awaitingGuest($table, ['status' => 'pending', 'confirmed_at' => null, 'confirmation_mode' => 'wait', 'confirmation_expires_at' => null]);

        $this->participants($table)
            ->callTableAction('confirm', $awaiting)
            ->assertNotified('La mesa está completa');

        $awaiting->refresh();
        $this->assertSame('pending', $awaiting->status->value);
        $this->assertNull($awaiting->email_confirmed_at);
        Notification::assertNothingSent();
    }

    // Hiding the button is not enough: the server must refuse the call as well
    public function test_an_editor_without_the_update_permission_cannot_confirm_the_email(): void
    {
        $table = $this->table();
        $awaiting = $this->awaitingGuest($table);
        $this->actingAs($this->editorWith(['gametables:gametables.view_any', 'gametables:gametables.view']));
        $context = ['table' => true, 'recordKey' => $awaiting->getKey()];

        $this->participants($table)
            ->assertTableActionHidden('confirmEmail', $awaiting)
            ->call('mountAction', 'confirmEmail', [], $context)
            ->assertSet('mountedActions', [])
            ->set('mountedActions', [['name' => 'confirmEmail', 'arguments' => [], 'context' => $context, 'data' => []]])
            ->call('callMountedAction');

        $this->assertNull($awaiting->refresh()->email_confirmed_at);
        Notification::assertNothingSent();
    }

    public function test_the_filter_lists_only_guests_awaiting_confirmation(): void
    {
        $table = $this->table();
        $awaiting = $this->awaitingGuest($table);
        $confirmedGuest = $this->guest($table, ['email_confirmed_at' => now()]);
        $expired = $this->awaitingGuest($table, ['status' => 'expired', 'confirmation_token' => null]);
        $user = $this->participant($table, ['user_id' => UserModel::factory()->create()->id]);

        $this->participants($table)
            ->filterTable('email_unconfirmed')
            ->assertCanSeeTableRecords([$awaiting])
            ->assertCanNotSeeTableRecords([$confirmedGuest, $expired, $user]);
    }

    public function test_an_awaiting_guest_shows_the_unconfirmed_badge_with_its_deadline(): void
    {
        $table = $this->table();
        $awaiting = $this->awaitingGuest($table, ['confirmation_expires_at' => CarbonImmutable::parse('2026-11-02 12:00:00', 'Europe/Madrid')]);
        $confirmedGuest = $this->guest($table, ['email_confirmed_at' => now()]);
        $user = $this->participant($table, ['user_id' => UserModel::factory()->create()->id]);

        $component = $this->participants($table);

        $this->assertSame([['Email sin confirmar', 'warning', 'Caduca el 02/11/2026 12:00']], $this->badges($component, $awaiting));
        $this->assertSame([], $this->badges($component, $confirmedGuest));
        $this->assertSame([], $this->badges($component, $user));
    }

    // Wait mode has no deadline to show
    public function test_a_wait_guest_shows_the_unconfirmed_badge_without_a_deadline(): void
    {
        $table = $this->table();
        $awaiting = $this->awaitingGuest($table, ['status' => 'pending', 'confirmed_at' => null, 'confirmation_mode' => 'wait', 'confirmation_expires_at' => null]);

        $this->assertSame([['Email sin confirmar', 'warning', null]], $this->badges($this->participants($table), $awaiting));
    }

    public function test_guests_from_the_same_connection_show_the_possible_duplicate_badge(): void
    {
        $table = $this->table();
        $first = $this->guest($table, ['email_confirmed_at' => now(), 'fingerprint' => self::FINGERPRINT]);
        $this->travelTo(CarbonImmutable::parse('2026-11-02 11:00:00', 'Europe/Madrid'));
        $second = $this->awaitingGuest($table, ['fingerprint' => self::FINGERPRINT]);
        $other = $this->guest($table, ['email_confirmed_at' => now(), 'fingerprint' => str_repeat('b', 64)]);

        $component = $this->participants($table);
        $duplicate = ['Posible duplicado', 'danger', 'Otra inscripción de esta mesa se hizo desde la misma conexión y navegador en 24 h o menos'];

        $this->assertSame([$duplicate], $this->badges($component, $first));
        $this->assertSame([['Email sin confirmar', 'warning', 'Caduca el 02/11/2026 13:00'], $duplicate], $this->badges($component, $second));
        $this->assertSame([], $this->badges($component, $other));
        $component->assertSee('Posible duplicado');
    }

    /**
     * Each badge of the row as the table renders it: label, colour and tooltip.
     *
     * @param  Testable<ParticipantsRelationManager>  $component
     * @return list<array{mixed, mixed, mixed}>
     */
    private function badges(Testable $component, ParticipantModel $record): array
    {
        $component->assertTableColumnExists('guest_flags');
        $column = $component->instance()->getTable()->getColumn('guest_flags');
        $this->assertInstanceOf(TextColumn::class, $column);
        $row = $component->instance()->getTableRecord((string) $record->getKey());
        $this->assertNotNull($row);
        $column->record($row);
        $column->clearCachedState();
        // A row without badges has a blank state, which Filament turns into the default (null)
        $state = $column->getState() ?? [];
        $this->assertIsArray($state);

        return array_values(array_map(
            fn (mixed $flag): array => [$column->formatState($flag), $column->getColor($flag), $column->getTooltip($flag)],
            $state,
        ));
    }

    /**
     * @return Testable<ParticipantsRelationManager>
     */
    private function participants(GameTableModel $table): Testable
    {
        return Livewire::test(ParticipantsRelationManager::class, ['ownerRecord' => $table, 'pageClass' => EditGameTable::class]);
    }

    /**
     * A web guest in hold mode who has not followed the link yet.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function awaitingGuest(GameTableModel $table, array $attributes = []): ParticipantModel
    {
        return $this->guest($table, [
            'email_confirmed_at' => null,
            'confirmation_mode' => 'hold',
            'confirmation_token' => bin2hex(random_bytes(32)),
            'confirmation_sent_at' => now(),
            'confirmation_expires_at' => now()->addHours(2),
            ...$attributes,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function guest(GameTableModel $table, array $attributes = []): ParticipantModel
    {
        return $this->participant($table, [
            'user_id' => null,
            'first_name' => 'Invitada',
            'email' => Str::lower(Str::random(8)).'@example.test',
            'cancellation_token' => bin2hex(random_bytes(32)),
            'status' => 'confirmed',
            'confirmed_at' => now(),
            ...$attributes,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function participant(GameTableModel $table, array $attributes = []): ParticipantModel
    {
        return ParticipantModel::create([
            'id' => (string) Str::uuid(),
            'game_table_id' => $table->id,
            'role' => 'player',
            'status' => 'confirmed',
            'confirmed_at' => now(),
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
    private function table(array $attributes = []): GameTableModel
    {
        return GameTableModel::create([
            'id' => (string) Str::uuid(),
            'game_system_id' => $this->gameSystem->id,
            'created_by' => UserModel::factory()->create()->id,
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
