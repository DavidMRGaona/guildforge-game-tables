<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Feature\Filament;

use App\Infrastructure\Persistence\Eloquent\Models\PermissionModel;
use App\Infrastructure\Persistence\Eloquent\Models\RoleModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Filament\Actions\Action;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Modules\GameTables\Domain\Enums\GameMasterRole;
use Modules\GameTables\Filament\Resources\GameTableResource;
use Modules\GameTables\Filament\Resources\GameTableResource\Pages\EditGameTable;
use Modules\GameTables\Filament\Resources\GameTableResource\RelationManagers\ParticipantsRelationManager;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameMasterModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameSystemModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameTableModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\ParticipantModel;
use Modules\GameTables\Notifications\CancellationConfirmation;
use Modules\GameTables\Notifications\GuestCancellationConfirmation;
use Modules\GameTables\Notifications\OrganizationCancellationNotification;
use Modules\GameTables\Notifications\ParticipantCancelledNotification;
use Modules\GameTables\Notifications\WaitingListPromotionNotification;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\Modules\InteractsWithModulePanel;
use Tests\Support\Modules\ModuleTestCase;

/**
 * The panel's «Cancelar inscripción» and «No presentado» row actions: when they show, who may
 * use them, what they change and which emails they send.
 */
final class ParticipantsCancelAndNoShowTest extends ModuleTestCase
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
        Notification::fake();
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

    /**
     * @return array<string, array{bool, string, bool}>
     */
    public static function cancelVisibilityByStatus(): array
    {
        $cases = [];
        foreach (['off' => false, 'on' => true] as $label => $waitingList) {
            foreach (['pending' => true, 'confirmed' => true, 'waiting_list' => true, 'cancelled' => false, 'rejected' => false, 'no_show' => false] as $status => $visible) {
                $cases["{$status}, waiting list {$label}"] = [$waitingList, $status, $visible];
            }
        }

        return $cases;
    }

    // Cancelling makes sense while the registration is active (ParticipantStatus::canBeCancelled)
    #[DataProvider('cancelVisibilityByStatus')]
    public function test_cancel_shows_only_for_active_registrations(bool $waitingList, string $status, bool $visible): void
    {
        $this->setWaitingList($waitingList);
        $table = $this->table();
        $row = $this->participant($table, $status, ['waiting_list_position' => $status === 'waiting_list' ? 1 : null]);

        $visible
            ? $this->participants($table)->assertTableActionVisible('cancelRegistration', $row)
            : $this->participants($table)->assertTableActionHidden('cancelRegistration', $row);
    }

    public function test_cancel_asks_for_confirmation(): void
    {
        $table = $this->table();
        $row = $this->participant($table, 'confirmed');

        $this->participants($table)->assertTableActionExists(
            'cancelRegistration',
            fn (Action $action): bool => $action->isConfirmationRequired()
                && $action->getLabel() === 'Cancelar inscripción',
            $row,
        );
    }

    #[DataProvider('waitingListSetting')]
    public function test_cancelling_a_user_sends_the_organisation_notice_and_nothing_to_the_directors(bool $waitingList): void
    {
        $this->setWaitingList($waitingList);
        $table = $this->tableWithDirector();
        $user = UserModel::factory()->create(['email' => 'jugadora@example.test']);
        $row = $this->participant($table, 'confirmed', ['user_id' => $user->id]);

        $this->participants($table)->callTableAction('cancelRegistration', $row)->assertHasNoTableActionErrors();

        $this->assertSame('cancelled', $row->refresh()->status->value);
        $this->assertNotNull($row->cancelled_at);
        $this->assertMailedTo(OrganizationCancellationNotification::class, 'jugadora@example.test');
        Notification::assertSentTimes(OrganizationCancellationNotification::class, 1);
        Notification::assertSentTimes(CancellationConfirmation::class, 0);
        Notification::assertSentTimes(ParticipantCancelledNotification::class, 0);
    }

    #[DataProvider('waitingListSetting')]
    public function test_cancelling_a_guest_sends_the_organisation_notice_to_their_email(bool $waitingList): void
    {
        $this->setWaitingList($waitingList);
        $table = $this->tableWithDirector();
        $row = $this->participant($table, 'pending', [
            'user_id' => null,
            'first_name' => 'Invitada',
            'email' => 'invitada@example.test',
            'cancellation_token' => 'token-de-prueba',
        ]);

        $this->participants($table)->callTableAction('cancelRegistration', $row);

        $this->assertSame('cancelled', $row->refresh()->status->value);
        $this->assertMailedTo(OrganizationCancellationNotification::class, 'invitada@example.test');
        Notification::assertSentTimes(GuestCancellationConfirmation::class, 0);
        Notification::assertSentTimes(ParticipantCancelledNotification::class, 0);
    }

    #[DataProvider('waitingListSetting')]
    public function test_cancelling_a_seat_promotes_the_first_waiting_player_with_their_usual_notice(bool $waitingList): void
    {
        $this->setWaitingList($waitingList);
        $table = $this->tableWithDirector(['max_players' => 1]);
        $row = $this->participant($table, 'confirmed');
        $nextUser = UserModel::factory()->create(['email' => 'siguiente@example.test']);
        $first = $this->participant($table, 'waiting_list', ['user_id' => $nextUser->id, 'waiting_list_position' => 1]);
        $second = $this->participant($table, 'waiting_list', ['waiting_list_position' => 2]);

        $this->participants($table)->callTableAction('cancelRegistration', $row);

        $this->assertSame('cancelled', $row->refresh()->status->value);
        $this->assertSame('confirmed', $first->refresh()->status->value);
        $this->assertSame('waiting_list', $second->refresh()->status->value);
        $this->assertMailedTo(WaitingListPromotionNotification::class, 'siguiente@example.test');
        Notification::assertSentTimes(WaitingListPromotionNotification::class, 1);
    }

    // With the list on, a manual table counts pending players as taken: cancelling one frees a seat
    public function test_cancelling_a_pending_player_on_a_manual_table_promotes_with_the_list_on(): void
    {
        $this->setWaitingList(true);
        $table = $this->table(['max_players' => 1, 'auto_confirm' => false]);
        $row = $this->participant($table, 'pending');
        $waiting = $this->participant($table, 'waiting_list', ['waiting_list_position' => 1]);

        $this->participants($table)->callTableAction('cancelRegistration', $row);

        $this->assertSame('cancelled', $row->refresh()->status->value);
        $this->assertSame('pending', $waiting->refresh()->status->value);
    }

    public function test_an_editor_without_the_update_permission_does_not_get_the_actions(): void
    {
        $table = $this->table(['starts_at' => now()->subHour()]);
        $confirmed = $this->participant($table, 'confirmed');
        $pending = $this->participant($table, 'pending');
        $waiting = $this->participant($table, 'waiting_list', ['waiting_list_position' => 1]);
        $this->actingAs($this->editorWith(['gametables:gametables.view_any', 'gametables:gametables.view']));

        // The same check as the other status actions
        $this->participants($table)
            ->assertTableActionHidden('cancelRegistration', $confirmed)
            ->assertTableActionHidden('markNoShow', $confirmed)
            ->assertTableActionHidden('confirm', $pending)
            ->assertTableActionHidden('reject', $pending)
            ->assertTableActionHidden('promote', $waiting);

        $this->assertSame('confirmed', $confirmed->refresh()->status->value);
    }

    public function test_an_editor_with_the_update_permission_gets_the_actions(): void
    {
        $table = $this->table(['starts_at' => now()->subHour()]);
        $confirmed = $this->participant($table, 'confirmed');
        $this->actingAs($this->editorWith(['gametables:gametables.view_any', 'gametables:gametables.update']));

        $this->participants($table)
            ->assertTableActionVisible('cancelRegistration', $confirmed)
            ->assertTableActionVisible('markNoShow', $confirmed)
            ->callTableAction('markNoShow', $confirmed);

        $pending = $this->participant($table, 'pending');
        $this->participants($table)->callTableAction('cancelRegistration', $pending);
        $this->assertSame('cancelled', $pending->refresh()->status->value);

        $this->assertSame('no_show', $confirmed->refresh()->status->value);
    }

    /**
     * @return array<string, array{bool, string, bool, bool}>
     */
    public static function noShowVisibility(): array
    {
        $cases = [];
        foreach (['off' => false, 'on' => true] as $label => $waitingList) {
            $cases["confirmed after the start, waiting list {$label}"] = [$waitingList, 'confirmed', true, true];
            $cases["confirmed before the start, waiting list {$label}"] = [$waitingList, 'confirmed', false, false];
            foreach (['pending', 'waiting_list', 'cancelled', 'rejected', 'no_show'] as $status) {
                $cases["{$status} after the start, waiting list {$label}"] = [$waitingList, $status, true, false];
            }
        }

        return $cases;
    }

    // Only someone who held a confirmed seat can fail to show up, once the session has started
    #[DataProvider('noShowVisibility')]
    public function test_no_show_shows_only_for_confirmed_participants_once_the_table_starts(bool $waitingList, string $status, bool $started, bool $visible): void
    {
        $this->setWaitingList($waitingList);
        $table = $this->table(['starts_at' => $started ? now()->subHour() : now()->addHour()]);
        $row = $this->participant($table, $status, ['waiting_list_position' => $status === 'waiting_list' ? 1 : null]);

        $visible
            ? $this->participants($table)->assertTableActionVisible('markNoShow', $row)
            : $this->participants($table)->assertTableActionHidden('markNoShow', $row);
    }

    #[DataProvider('waitingListSetting')]
    public function test_no_show_marks_the_participant_without_emails_or_promotion(bool $waitingList): void
    {
        $this->setWaitingList($waitingList);
        $table = $this->tableWithDirector(['max_players' => 1, 'starts_at' => now()->subHour(), 'status' => 'in_progress']);
        $row = $this->participant($table, 'confirmed');
        $waiting = $this->participant($table, 'waiting_list', ['waiting_list_position' => 1]);

        $this->participants($table)->callTableAction('markNoShow', $row)->assertHasNoTableActionErrors();

        $this->assertSame('no_show', $row->refresh()->status->value);
        $this->assertSame('waiting_list', $waiting->refresh()->status->value);
        Notification::assertNothingSent();
    }

    /**
     * @return Testable<ParticipantsRelationManager>
     */
    private function participants(GameTableModel $table): Testable
    {
        return Livewire::test(ParticipantsRelationManager::class, ['ownerRecord' => $table, 'pageClass' => EditGameTable::class]);
    }

    /**
     * @param  class-string  $notification
     */
    private function assertMailedTo(string $notification, string $email): void
    {
        Notification::assertSentOnDemand(
            $notification,
            fn (object $sent, array $channels, AnonymousNotifiable $notifiable): bool => $notifiable->routes['mail'] === $email,
        );
    }

    private function setWaitingList(bool $enabled): void
    {
        config()->set('modules.settings.game-tables.registration.waiting_list_enabled', $enabled);
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
    private function tableWithDirector(array $attributes = []): GameTableModel
    {
        $table = $this->table($attributes);
        $director = GameMasterModel::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Directora',
            'email' => 'director@example.test',
            'role' => GameMasterRole::Main,
            'notify_by_email' => true,
            'is_name_public' => true,
        ]);
        $table->gameMasters()->attach($director->id, ['source' => 'local', 'excluded' => false, 'sort_order' => 1]);
        $table->update(['notification_email' => 'mesa@example.test']);

        return $table;
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
