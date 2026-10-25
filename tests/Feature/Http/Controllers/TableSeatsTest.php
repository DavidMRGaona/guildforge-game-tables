<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Feature\Http\Controllers;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\GameTables\Application\DTOs\RegisterParticipantDTO;
use Modules\GameTables\Application\Services\RegistrationServiceInterface;
use Modules\GameTables\Domain\Enums\ParticipantRole;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameSystemModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameTableModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\ParticipantModel;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Response;
use Tests\Support\Modules\ModuleTestCase;

/**
 * Free seats, for players and spectators, have one source: the server counts them with the rule
 * it applies on registration and the table page shows them as they come. Pending registrations
 * hold seats, for both roles, only with the waiting list on and on tables with manual confirmation.
 */
final class TableSeatsTest extends ModuleTestCase
{
    protected ?string $moduleName = 'game-tables';

    protected bool $autoEnableModule = true;

    private UserModel $user;

    private GameSystemModel $gameSystem;

    protected function setUp(): void
    {
        parent::setUp();

        app('router')->getRoutes()->refreshNameLookups();
        config()->set('modules.settings.game-tables', []);

        $this->user = UserModel::factory()->create();
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

    // Characterization: confirmed registrations always hold seats

    #[DataProvider('waitingListSetting')]
    public function test_confirmed_players_and_spectators_take_the_seats_shown_on_the_page(bool $waitingList): void
    {
        $this->setWaitingList($waitingList);
        $table = $this->table(['max_players' => 4, 'max_spectators' => 3]);
        $this->participants($table, 'player', 'confirmed', 3);
        $this->participants($table, 'spectator', 'confirmed', 1);

        $this->get(route('gametables.show', $table->slug))
            ->assertInertia(fn (Assert $page) => $page
                ->where('table.currentPlayers', 3)
                ->where('table.spotsAvailable', 1)
                ->where('table.currentSpectators', 1)
                ->where('table.spectatorSpotsAvailable', 2));
    }

    #[DataProvider('waitingListSetting')]
    public function test_confirmed_spectators_fill_the_spectator_seats_on_the_page_and_the_server(bool $waitingList): void
    {
        $this->setWaitingList($waitingList);
        $table = $this->table(['max_spectators' => 2]);
        $this->participants($table, 'spectator', 'confirmed', 2);

        $this->actingAs($this->user)->get(route('gametables.show', $table->slug))
            ->assertInertia(fn (Assert $page) => $page
                ->where('table.currentSpectators', 2)
                ->where('table.spectatorSpotsAvailable', 0)
                ->where('eligibility.spectator.eligible', false)
                ->where('eligibility.spectator.reason', 'spectators_full'));

        $this->register($this->user, $table, 'spectator')
            ->assertStatus(422)
            ->assertJsonPath('errors.registration.0', __('game-tables::messages.errors.spectators_full'));
        $this->registerGuest($table, 'invitada@example.test', 'spectator')
            ->assertStatus(422)
            ->assertJsonPath('errors.registration.0', __('game-tables::messages.errors.spectators_full'));
    }

    #[DataProvider('waitingListSetting')]
    public function test_a_spectator_on_a_table_with_manual_confirmation_waits_for_the_director(bool $waitingList): void
    {
        $this->setWaitingList($waitingList);
        $table = $this->table(['auto_confirm' => false, 'max_spectators' => 2]);

        $this->register($this->user, $table, 'spectator')->assertRedirect();

        $this->assertSame('pending', $this->rowOf($table, $this->user->id)->status->value);
    }

    public function test_with_the_waiting_list_on_pending_players_hold_seats_on_manual_tables(): void
    {
        $this->setWaitingList(true);
        $table = $this->table(['auto_confirm' => false, 'max_players' => 3]);
        $this->participants($table, 'player', 'confirmed', 1);
        $this->participants($table, 'player', 'pending', 2);

        $this->actingAs($this->user)->get(route('gametables.show', $table->slug))
            ->assertInertia(fn (Assert $page) => $page
                ->where('table.currentPlayers', 3)
                ->where('table.spotsAvailable', 0)
                ->where('eligibility.eligible', true)
                ->where('eligibility.waitingList', true));

        $this->register($this->user, $table)->assertRedirect();

        $this->assertSame('waiting_list', $this->rowOf($table, $this->user->id)->status->value);
    }

    // The page shows the seats the server counts

    public function test_with_the_waiting_list_off_pending_players_leave_their_seats_free_on_the_page_as_on_the_server(): void
    {
        $table = $this->table(['auto_confirm' => false, 'max_players' => 2]);
        $this->participants($table, 'player', 'pending', 2);

        $this->actingAs($this->user)->get(route('gametables.show', $table->slug))
            ->assertInertia(fn (Assert $page) => $page
                ->where('table.currentPlayers', 0)
                ->where('table.spotsAvailable', 2)
                ->where('eligibility.eligible', true)
                ->where('eligibility.waitingList', false));

        $this->register($this->user, $table)->assertRedirect();

        $this->assertSame('pending', $this->rowOf($table, $this->user->id)->status->value);
    }

    public function test_on_a_table_that_confirms_automatically_pending_players_leave_their_seats_free(): void
    {
        $this->setWaitingList(true);
        $table = $this->table(['auto_confirm' => true, 'max_players' => 2]);
        $this->participants($table, 'player', 'confirmed', 1);
        $this->participants($table, 'player', 'pending', 1);

        $this->get(route('gametables.show', $table->slug))
            ->assertInertia(fn (Assert $page) => $page
                ->where('table.currentPlayers', 1)
                ->where('table.spotsAvailable', 1)
                ->where('guestEligibility.eligible', true)
                ->where('guestEligibility.waitingList', false));
    }

    public function test_with_the_waiting_list_off_pending_spectators_leave_their_seats_free_on_the_page_as_on_the_server(): void
    {
        $table = $this->table(['auto_confirm' => false, 'max_spectators' => 2]);
        $this->participants($table, 'spectator', 'pending', 2);

        $this->actingAs($this->user)->get(route('gametables.show', $table->slug))
            ->assertInertia(fn (Assert $page) => $page
                ->where('table.currentSpectators', 0)
                ->where('table.spectatorSpotsAvailable', 2)
                ->where('eligibility.spectator.eligible', true));

        $this->register($this->user, $table, 'spectator')->assertRedirect();

        $this->assertSame('pending', $this->rowOf($table, $this->user->id)->status->value);
    }

    // With the waiting list on, pending spectators hold seats on manual tables, as pending players do

    public function test_with_the_waiting_list_on_pending_spectators_hold_seats_on_manual_tables(): void
    {
        $this->setWaitingList(true);
        $table = $this->table(['auto_confirm' => false, 'max_spectators' => 2]);
        $this->participants($table, 'spectator', 'confirmed', 1);
        $this->participants($table, 'spectator', 'pending', 1);

        $this->actingAs($this->user)->get(route('gametables.show', $table->slug))
            ->assertInertia(fn (Assert $page) => $page
                ->where('table.currentSpectators', 2)
                ->where('table.spectatorSpotsAvailable', 0)
                ->where('eligibility.spectator.eligible', false)
                ->where('eligibility.spectator.reason', 'spectators_full'));

        $this->register($this->user, $table, 'spectator')
            ->assertStatus(422)
            ->assertJsonPath('errors.registration.0', __('game-tables::messages.errors.spectators_full'));
        $this->registerGuest($table, 'invitada@example.test', 'spectator')
            ->assertStatus(422)
            ->assertJsonPath('errors.registration.0', __('game-tables::messages.errors.spectators_full'));

        $this->assertSame(1, $this->countRows($table, 'spectator', 'pending'));
    }

    public function test_with_the_waiting_list_on_the_guest_page_counts_pending_spectators_on_manual_tables(): void
    {
        $this->setWaitingList(true);
        $table = $this->table(['auto_confirm' => false, 'max_spectators' => 1]);
        $this->participants($table, 'spectator', 'pending', 1);

        $this->get(route('gametables.show', $table->slug))
            ->assertInertia(fn (Assert $page) => $page
                ->where('table.spectatorSpotsAvailable', 0)
                ->where('guestEligibility.spectator.eligible', false)
                ->where('guestEligibility.spectator.reason', 'spectators_full'));
    }

    public function test_with_the_waiting_list_on_an_admin_adding_a_spectator_to_a_manual_table_held_by_pending_spectators_queues_them(): void
    {
        $this->setWaitingList(true);
        $table = $this->table(['auto_confirm' => false, 'max_spectators' => 1]);
        $this->participants($table, 'spectator', 'pending', 1);

        app(RegistrationServiceInterface::class)->registerByAdmin(new RegisterParticipantDTO(
            gameTableId: $table->id,
            userId: $this->user->id,
            role: ParticipantRole::Spectator,
        ));

        $this->assertSame('waiting_list', $this->rowOf($table, $this->user->id)->status->value);
    }

    public function test_with_the_waiting_list_off_an_admin_adding_a_spectator_to_a_manual_table_seats_them_as_pending(): void
    {
        $table = $this->table(['auto_confirm' => false, 'max_spectators' => 1]);
        $this->participants($table, 'spectator', 'pending', 1);

        app(RegistrationServiceInterface::class)->registerByAdmin(new RegisterParticipantDTO(
            gameTableId: $table->id,
            userId: $this->user->id,
            role: ParticipantRole::Spectator,
        ));

        $this->assertSame('pending', $this->rowOf($table, $this->user->id)->status->value);
    }

    public function test_the_refusal_for_full_spectator_seats_is_translated_in_english_too(): void
    {
        app()->setLocale('en');

        $this->assertNotSame('game-tables::messages.errors.spectators_full', __('game-tables::messages.errors.spectators_full'));
    }

    // The waiting list count on the page

    #[DataProvider('waitingListSetting')]
    public function test_the_page_counts_the_players_still_waiting(bool $waitingList): void
    {
        $this->setWaitingList($waitingList);
        $table = $this->table(['max_players' => 1]);
        $this->participants($table, 'player', 'confirmed', 1);
        $this->participants($table, 'player', 'waiting_list', 1, ['waiting_list_position' => 1]);
        $this->participants($table, 'player', 'waiting_list', 1, ['waiting_list_position' => 2]);
        // Left the list: a cancelled or rejected row keeps its old position
        $this->participants($table, 'player', 'cancelled', 1, ['waiting_list_position' => 3]);
        $this->participants($table, 'player', 'rejected', 1, ['waiting_list_position' => 4]);
        // Spectators never take a player seat from the list
        $this->participants($table, 'spectator', 'waiting_list', 1, ['waiting_list_position' => 5]);

        $this->get(route('gametables.show', $table->slug))
            ->assertInertia(fn (Assert $page) => $page->where('table.waitingListCount', 2));
    }

    /**
     * @return TestResponse<Response>
     */
    private function register(UserModel $user, GameTableModel $table, string $role = 'player'): TestResponse
    {
        $this->actingAs($user);
        $response = $this->postJson(route('gametables.register', $table->slug), ['role' => $role]);
        $this->app['auth']->forgetGuards();

        return $response;
    }

    /**
     * @return TestResponse<Response>
     */
    private function registerGuest(GameTableModel $table, string $email, string $role = 'player'): TestResponse
    {
        $this->app['auth']->forgetGuards();

        return $this->postJson(route('gametables.register-guest', $table->slug), [
            'first_name' => 'Invitada',
            'email' => $email,
            'role' => $role,
            'gdpr_consent' => true,
        ]);
    }

    private function setWaitingList(bool $enabled): void
    {
        config()->set('modules.settings.game-tables.registration.waiting_list_enabled', $enabled);
    }

    private function rowOf(GameTableModel $table, string $userId): ParticipantModel
    {
        return ParticipantModel::query()->where('game_table_id', $table->id)->where('user_id', $userId)->firstOrFail();
    }

    private function countRows(GameTableModel $table, string $role, string $status): int
    {
        return ParticipantModel::query()
            ->where('game_table_id', $table->id)
            ->where('role', $role)
            ->where('status', $status)
            ->count();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function participants(GameTableModel $table, string $role, string $status, int $count, array $attributes = []): void
    {
        for ($i = 0; $i < $count; $i++) {
            ParticipantModel::create([
                'id' => (string) Str::uuid(),
                'game_table_id' => $table->id,
                'user_id' => UserModel::factory()->create()->id,
                'role' => $role,
                'status' => $status,
                'confirmed_at' => $status === 'confirmed' ? now() : null,
                ...$attributes,
            ]);
        }
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
            'max_spectators' => 0,
            'language' => 'es',
            'registration_type' => 'everyone',
            'auto_confirm' => true,
            'is_published' => true,
            ...$attributes,
        ]);
    }
}
