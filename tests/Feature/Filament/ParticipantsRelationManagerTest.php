<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Feature\Filament;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
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

    #[DataProvider('waitingListSetting')]
    public function test_editing_saves_the_other_fields_and_keeps_the_status(bool $waitingList): void
    {
        $this->setWaitingList($waitingList);
        $table = $this->table();
        $participant = $this->participant($table, 'confirmed');

        $this->participants($table)
            ->callTableAction('edit', $participant, data: ['role' => 'spectator', 'notes' => 'Llega tarde'])
            ->assertHasNoTableActionErrors();

        $participant->refresh();
        $this->assertSame('Llega tarde', $participant->notes);
        $this->assertSame('spectator', $participant->role->value);
        $this->assertSame('confirmed', $participant->status->value);
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
