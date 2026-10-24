<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Feature\Filament;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Modules\GameTables\Application\Services\RegistrationServiceInterface;
use Modules\GameTables\Domain\Exceptions\ParticipantNotPendingException;
use Modules\GameTables\Filament\Resources\GameTableResource;
use Modules\GameTables\Filament\Resources\GameTableResource\Pages\EditGameTable;
use Modules\GameTables\Filament\Resources\GameTableResource\RelationManagers\ParticipantsRelationManager;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameSystemModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameTableModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\ParticipantModel;
use Modules\GameTables\Notifications\ParticipantRejectedNotification;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\Modules\InteractsWithModulePanel;
use Tests\Support\Modules\ModuleTestCase;

/**
 * The participants of a table in the panel: the status only changes through the row actions
 * (which go through the registration service, its lock and its seat rules), never through Edit.
 */
final class ParticipantsRelationManagerTest extends ModuleTestCase
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
        config()->set('modules.settings.game-tables', []);
        $this->gameSystem = GameSystemModel::create([
            'id' => (string) Str::uuid(),
            'name' => 'Sistema de prueba',
            'slug' => 'sistema-de-prueba',
            'is_active' => true,
        ]);
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

    #[DataProvider('waitingListSetting')]
    public function test_adding_a_user_takes_a_free_seat_or_the_waiting_list(bool $waitingList): void
    {
        $this->setWaitingList($waitingList);
        $table = $this->table(['max_players' => 1]);
        $first = UserModel::factory()->create();
        $second = UserModel::factory()->create();

        foreach ([$first, $second] as $user) {
            $this->participants($table)
                ->callTableAction('create', data: ['participant_type' => 'user', 'user_id' => $user->id, 'role' => 'player'])
                ->assertHasNoTableActionErrors();
        }

        $this->assertSame('confirmed', $this->rowOfUser($table, $first)->status->value);
        $this->assertSame('waiting_list', $this->rowOfUser($table, $second)->status->value);
    }

    #[DataProvider('waitingListSetting')]
    public function test_adding_a_guest_stores_the_email_in_lowercase(bool $waitingList): void
    {
        $this->setWaitingList($waitingList);
        $table = $this->table();

        $this->participants($table)
            ->callTableAction('create', data: [
                'participant_type' => 'external',
                'first_name' => 'Invitada',
                'email' => 'Invitada@Example.TEST',
                'role' => 'player',
            ])
            ->assertHasNoTableActionErrors();

        $row = ParticipantModel::query()->where('game_table_id', $table->id)->firstOrFail();
        $this->assertSame('invitada@example.test', $row->email);
        $this->assertSame('confirmed', $row->status->value);
    }

    #[DataProvider('waitingListSetting')]
    public function test_the_row_actions_confirm_reject_and_promote(bool $waitingList): void
    {
        $this->setWaitingList($waitingList);
        $table = $this->table(['max_players' => 5, 'auto_confirm' => false]);
        $toConfirm = $this->participant($table, 'pending');
        $toReject = $this->participant($table, 'pending');
        $toPromote = $this->participant($table, 'waiting_list', ['waiting_list_position' => 1]);

        $this->participants($table)
            ->callTableAction('promote', $toPromote)
            ->callTableAction('confirm', $toConfirm)
            ->callTableAction('reject', $toReject);

        $this->assertSame('confirmed', $toConfirm->refresh()->status->value);
        $this->assertSame('rejected', $toReject->refresh()->status->value);
        $this->assertSame('confirmed', $toPromote->refresh()->status->value);
    }

    public function test_rejecting_with_a_reason_sends_it_in_the_email(): void
    {
        Notification::fake();
        $table = $this->table(['max_players' => 5, 'auto_confirm' => false]);
        $row = $this->participant($table, 'pending');

        $this->participants($table)
            ->callTableAction('reject', $row, data: ['reason' => 'Ya no quedan plazas para principiantes'])
            ->assertHasNoTableActionErrors();

        $this->assertSame('rejected', $row->refresh()->status->value);
        Notification::assertSentOnDemand(
            ParticipantRejectedNotification::class,
            fn (ParticipantRejectedNotification $n): bool => $n->reason === 'Ya no quedan plazas para principiantes',
        );
    }

    public function test_rejecting_without_a_reason_sends_none(): void
    {
        Notification::fake();
        $table = $this->table(['max_players' => 5, 'auto_confirm' => false]);
        $row = $this->participant($table, 'pending');

        $this->participants($table)->callTableAction('reject', $row, data: ['reason' => '   ']);

        $this->assertSame('rejected', $row->refresh()->status->value);
        Notification::assertSentOnDemand(
            ParticipantRejectedNotification::class,
            fn (ParticipantRejectedNotification $n): bool => $n->reason === null,
        );
    }

    public function test_a_reason_over_500_characters_is_refused(): void
    {
        $table = $this->table(['max_players' => 5, 'auto_confirm' => false]);
        $row = $this->participant($table, 'pending');

        $this->participants($table)
            ->callTableAction('reject', $row, data: ['reason' => str_repeat('a', 501)])
            ->assertHasTableActionErrors(['reason']);

        $this->assertSame('pending', $row->refresh()->status->value);
    }

    public function test_the_service_refuses_to_reject_a_confirmed_participant(): void
    {
        $table = $this->table(['max_players' => 5]);
        $row = $this->participant($table, 'confirmed');

        try {
            app(RegistrationServiceInterface::class)->reject($row->id);
            $this->fail('A confirmed participant must not be rejected.');
        } catch (ParticipantNotPendingException $exception) {
            $this->assertSame('not_pending', $exception->reason);
        }

        $this->assertSame('confirmed', $row->refresh()->status->value);
    }

    #[DataProvider('waitingListSetting')]
    public function test_editing_saves_the_other_fields_and_keeps_the_status(bool $waitingList): void
    {
        $this->setWaitingList($waitingList);
        $table = $this->table();
        $participant = $this->participant($table, 'confirmed');

        $this->participants($table)
            ->callTableAction('edit', $participant, data: ['notes' => 'Llega tarde'])
            ->assertHasNoTableActionErrors();

        $participant->refresh();
        $this->assertSame('Llega tarde', $participant->notes);
        $this->assertSame('player', $participant->role->value);
        $this->assertSame('confirmed', $participant->status->value);
    }

    // Who the registration belongs to and its role change only by cancelling and adding again,
    // which goes through the table lock and the seat rules
    public function test_the_edit_form_locks_the_role_the_person_and_the_type(): void
    {
        $table = $this->table();
        $participant = $this->participant($table, 'confirmed');

        $this->participants($table)
            ->mountTableAction('create')
            ->assertFormFieldIsEnabled('role')
            ->assertFormFieldIsEnabled('participant_type')
            ->assertFormFieldIsEnabled('user_id');

        $this->participants($table)
            ->mountTableAction('edit', $participant)
            ->assertFormFieldIsDisabled('role')
            ->assertFormFieldIsDisabled('participant_type')
            ->assertFormFieldIsDisabled('user_id');
    }

    #[DataProvider('waitingListSetting')]
    public function test_a_role_sent_with_the_edit_form_cannot_turn_a_spectator_into_a_player_on_a_full_table(bool $waitingList): void
    {
        $this->setWaitingList($waitingList);
        $table = $this->table(['max_players' => 1]);
        $this->participant($table, 'confirmed');
        $spectator = $this->participant($table, 'confirmed', ['role' => 'spectator']);

        $this->participants($table)->callTableAction('edit', $spectator, data: ['role' => 'player', 'notes' => 'Cambio']);

        $spectator->refresh();
        $this->assertSame('spectator', $spectator->role->value);
        $this->assertSame('Cambio', $spectator->notes);
        $this->assertSame(1, ParticipantModel::query()->where('game_table_id', $table->id)->where('role', 'player')->count());
    }

    #[DataProvider('waitingListSetting')]
    public function test_a_role_sent_with_the_edit_form_cannot_turn_a_player_into_a_spectator_without_spectator_seats(bool $waitingList): void
    {
        $this->setWaitingList($waitingList);
        $table = $this->table(['max_players' => 1, 'max_spectators' => 0]);
        $player = $this->participant($table, 'confirmed');
        $waiting = $this->participant($table, 'waiting_list', ['waiting_list_position' => 1]);

        $this->participants($table)->callTableAction('edit', $player, data: ['role' => 'spectator']);

        $this->assertSame('player', $player->refresh()->role->value);
        $this->assertSame('waiting_list', $waiting->refresh()->status->value);
    }

    #[DataProvider('waitingListSetting')]
    public function test_a_role_sent_with_the_edit_form_cannot_turn_a_waiting_player_into_a_waiting_spectator(bool $waitingList): void
    {
        $this->setWaitingList($waitingList);
        $table = $this->table(['max_players' => 1]);
        $this->participant($table, 'confirmed');
        $waiting = $this->participant($table, 'waiting_list', ['waiting_list_position' => 1]);

        $this->participants($table)->callTableAction('edit', $waiting, data: ['role' => 'spectator']);

        $this->assertSame('player', $waiting->refresh()->role->value);
        $this->assertSame('waiting_list', $waiting->status->value);
    }

    #[DataProvider('waitingListSetting')]
    public function test_a_user_sent_with_the_edit_form_does_not_change_who_the_registration_belongs_to(bool $waitingList): void
    {
        $this->setWaitingList($waitingList);
        $table = $this->table();
        $other = $this->participant($table, 'confirmed');
        $row = $this->participant($table, 'confirmed');
        $owner = $row->user_id;

        $this->participants($table)->callTableAction('edit', $row, data: ['user_id' => $other->user_id]);

        $this->assertSame($owner, $row->refresh()->user_id);
        $this->assertSame(1, ParticipantModel::query()->where('game_table_id', $table->id)->where('user_id', $other->user_id)->count());
    }

    #[DataProvider('waitingListSetting')]
    public function test_a_type_sent_with_the_edit_form_does_not_turn_a_user_into_a_guest_or_back(bool $waitingList): void
    {
        $this->setWaitingList($waitingList);
        $table = $this->table();
        $userRow = $this->participant($table, 'confirmed');
        $owner = $userRow->user_id;
        $guest = $this->participant($table, 'confirmed', ['user_id' => null, 'first_name' => 'Invitada', 'email' => 'invitada@example.test']);
        $someone = UserModel::factory()->create();

        $this->participants($table)
            ->callTableAction('edit', $userRow, data: ['participant_type' => 'external', 'first_name' => 'Falsa', 'email' => 'falsa@example.test'])
            ->callTableAction('edit', $guest, data: ['participant_type' => 'user', 'user_id' => $someone->id]);

        $userRow->refresh();
        $this->assertSame($owner, $userRow->user_id);
        $this->assertNull($userRow->email);
        $guest->refresh();
        $this->assertNull($guest->user_id);
        $this->assertSame('invitada@example.test', $guest->email);
    }

    #[DataProvider('waitingListSetting')]
    public function test_editing_a_guest_to_the_email_of_another_active_guest_of_the_table_is_refused(bool $waitingList): void
    {
        $this->setWaitingList($waitingList);
        $table = $this->table();
        $this->participant($table, 'waiting_list', ['user_id' => null, 'first_name' => 'Ana', 'email' => 'ana@example.test', 'waiting_list_position' => 1]);
        $guest = $this->participant($table, 'confirmed', ['user_id' => null, 'first_name' => 'Bea', 'email' => 'bea@example.test']);

        $this->participants($table)
            ->callTableAction('edit', $guest, data: ['email' => 'Ana@Example.TEST'])
            ->assertHasTableActionErrors(['email' => __('game-tables::messages.errors.guest_already_registered')]);

        $this->assertSame('bea@example.test', $guest->refresh()->email);
    }

    #[DataProvider('waitingListSetting')]
    public function test_editing_a_guest_keeps_or_reuses_an_email_no_active_guest_of_the_table_has(bool $waitingList): void
    {
        $this->setWaitingList($waitingList);
        $table = $this->table();
        $other = $this->table();
        $this->participant($table, 'cancelled', ['user_id' => null, 'first_name' => 'Ana', 'email' => 'ana@example.test']);
        $this->participant($other, 'confirmed', ['user_id' => null, 'first_name' => 'Carla', 'email' => 'carla@example.test']);
        $guest = $this->participant($table, 'confirmed', ['user_id' => null, 'first_name' => 'Bea', 'email' => 'bea@example.test']);

        $this->participants($table)
            ->callTableAction('edit', $guest, data: ['email' => 'Bea@Example.TEST', 'notes' => 'Mismo email'])
            ->assertHasNoTableActionErrors()
            ->callTableAction('edit', $guest, data: ['email' => 'ana@example.test'])
            ->assertHasNoTableActionErrors()
            ->callTableAction('edit', $guest, data: ['email' => 'carla@example.test'])
            ->assertHasNoTableActionErrors();

        $this->assertSame('carla@example.test', $guest->refresh()->email);
    }

    public function test_the_add_and_edit_forms_have_no_status_field(): void
    {
        $table = $this->table();
        $participant = $this->participant($table, 'pending');

        $this->participants($table)
            ->mountTableAction('create')
            ->assertFormFieldExists('role')
            ->assertFormFieldDoesNotExist('status');

        $this->participants($table)
            ->mountTableAction('edit', $participant)
            ->assertFormFieldExists('notes')
            ->assertFormFieldDoesNotExist('status');
    }

    // A status sent with the edit form (the field is gone, the request can still carry it) is ignored
    #[DataProvider('waitingListSetting')]
    public function test_a_status_sent_with_the_edit_form_changes_nothing(bool $waitingList): void
    {
        $this->setWaitingList($waitingList);
        $table = $this->table(['max_players' => 1, 'auto_confirm' => false]);
        $this->participant($table, 'confirmed');
        $pending = $this->participant($table, 'pending');

        $this->participants($table)
            ->callTableAction('edit', $pending, data: ['status' => 'confirmed', 'notes' => 'Sin plaza']);

        $pending->refresh();
        $this->assertSame('pending', $pending->status->value);
        $this->assertSame('Sin plaza', $pending->notes);
        $this->assertSame(1, ParticipantModel::query()->where('game_table_id', $table->id)->where('status', 'confirmed')->count());
    }

    #[DataProvider('waitingListSetting')]
    public function test_editing_a_guest_stores_the_email_in_lowercase(bool $waitingList): void
    {
        $this->setWaitingList($waitingList);
        $table = $this->table();
        $guest = $this->participant($table, 'confirmed', [
            'user_id' => null,
            'first_name' => 'Invitada',
            'email' => 'invitada@example.test',
        ]);

        $this->participants($table)
            ->callTableAction('edit', $guest, data: ['email' => 'Nueva.Invitada@Example.TEST'])
            ->assertHasNoTableActionErrors();

        $guest->refresh();
        $this->assertSame('nueva.invitada@example.test', $guest->email);
        $this->assertSame('confirmed', $guest->status->value);
    }

    // An unconfirmed guest's email is stored as registration stores it
    public function test_editing_an_unconfirmed_guest_stores_the_email_in_lowercase(): void
    {
        $table = $this->table();
        $guest = $this->unconfirmedGuest($table);

        $this->participants($table)
            ->callTableAction('edit', $guest, data: ['email' => 'Nueva.Bea@Example.TEST'])
            ->assertHasNoTableActionErrors();

        $this->assertSame('nueva.bea@example.test', $guest->refresh()->email);
    }

    // Another guest takes the email between the check and the save: a form error, never a 500 (whose
    // SQL would carry the email into the log)
    public function test_an_email_taken_while_editing_an_unconfirmed_guest_is_a_form_error(): void
    {
        Exceptions::fake();
        $table = $this->table();
        $guest = $this->unconfirmedGuest($table);
        ParticipantModel::updating(function (ParticipantModel $model) use ($table): void {
            if ($model->isDirty('email')) {
                DB::table('gametables_participants')->insert([
                    'id' => (string) Str::uuid(),
                    'game_table_id' => $table->id,
                    'role' => 'player',
                    'status' => 'confirmed',
                    'first_name' => 'Ana',
                    'email' => 'ana@example.test',
                    'email_confirmed_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });

        $this->participants($table)
            ->callTableAction('edit', $guest, data: ['email' => 'Ana@Example.TEST'])
            ->assertHasTableActionErrors(['email' => 'Ya existe una inscripción con este email']);

        $this->assertSame('bea@example.test', $guest->refresh()->email);
        Exceptions::assertNothingReported();
    }

    private function unconfirmedGuest(GameTableModel $table): ParticipantModel
    {
        return $this->participant($table, 'confirmed', [
            'user_id' => null,
            'first_name' => 'Bea',
            'email' => 'bea@example.test',
            'email_confirmed_at' => null,
            'confirmation_mode' => 'hold',
            'confirmation_expires_at' => now()->addHour(),
            'confirmation_token' => str_repeat('c', 64),
        ]);
    }

    /**
     * @return Testable<ParticipantsRelationManager>
     */
    private function participants(GameTableModel $table): Testable
    {
        return Livewire::test(ParticipantsRelationManager::class, ['ownerRecord' => $table, 'pageClass' => EditGameTable::class]);
    }

    private function setWaitingList(bool $enabled): void
    {
        config()->set('modules.settings.game-tables.registration.waiting_list_enabled', $enabled);
    }

    private function rowOfUser(GameTableModel $table, UserModel $user): ParticipantModel
    {
        return ParticipantModel::query()->where('game_table_id', $table->id)->where('user_id', $user->id)->firstOrFail();
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
