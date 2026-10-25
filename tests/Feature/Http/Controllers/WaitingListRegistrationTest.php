<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Feature\Http\Controllers;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\GameTables\Application\Services\RegistrationServiceInterface;
use Modules\GameTables\Domain\Exceptions\TableFullException;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameSystemModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameTableModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\ParticipantModel;
use Symfony\Component\HttpFoundation\Response;
use Tests\Support\Modules\ModuleTestCase;

/**
 * The waiting list: offered by the table page and accepted by the server with the same
 * eligibility, only for players and only while the setting is on and registrations are open.
 */
final class WaitingListRegistrationTest extends ModuleTestCase
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

    public function test_with_the_setting_off_a_full_table_refuses_players_on_the_page_and_the_server(): void
    {
        $table = $this->fullTable();

        $this->actingAs($this->user)->get(route('gametables.show', $table->slug))
            ->assertInertia(fn (Assert $page) => $page
                ->where('eligibility.eligible', false)
                ->where('eligibility.reason', 'table_full')
                ->where('eligibility.waitingList', false));

        $this->register($this->user, $table)
            ->assertStatus(422)
            ->assertJsonPath('errors.registration.0', __('game-tables::messages.errors.table_full'));
        $this->registerGuest($table, 'invitada@example.test')
            ->assertStatus(422)
            ->assertJsonPath('errors.registration.0', __('game-tables::messages.errors.table_full'));

        $this->assertSame(0, $this->waiting($table));
    }

    public function test_with_the_setting_on_a_full_table_offers_the_waiting_list_on_the_page(): void
    {
        $this->enableWaitingList();
        $table = $this->fullTable();

        $this->actingAs($this->user)->get(route('gametables.show', $table->slug))
            ->assertInertia(fn (Assert $page) => $page
                ->where('eligibility.eligible', true)
                ->where('eligibility.reason', null)
                ->where('eligibility.waitingList', true));
    }

    public function test_with_the_setting_on_a_table_with_free_seats_is_not_a_waiting_list(): void
    {
        $this->enableWaitingList();
        $table = $this->table();

        $this->actingAs($this->user)->get(route('gametables.show', $table->slug))
            ->assertInertia(fn (Assert $page) => $page
                ->where('eligibility.eligible', true)
                ->where('eligibility.waitingList', false));
    }

    public function test_with_the_setting_on_users_join_the_waiting_list_in_order(): void
    {
        $this->enableWaitingList();
        $table = $this->fullTable();
        $second = UserModel::factory()->create();

        $this->register($this->user, $table)->assertRedirect();
        $this->register($second, $table)->assertRedirect();

        $this->assertSame(1, $this->rowOf($table, $this->user->id)->waiting_list_position);
        $this->assertSame('waiting_list', $this->rowOf($table, $this->user->id)->status->value);
        $this->assertSame(2, $this->rowOf($table, $second->id)->waiting_list_position);
        $this->assertSame(2, $this->confirmedPlayers($table));
    }

    public function test_with_the_setting_on_a_guest_joins_the_waiting_list(): void
    {
        $this->enableWaitingList();
        $table = $this->fullTable();

        $this->get(route('gametables.show', $table->slug))
            ->assertInertia(fn (Assert $page) => $page
                ->where('guestEligibility.eligible', true)
                ->where('guestEligibility.waitingList', true));

        $this->registerGuest($table, 'primera@example.test')->assertRedirect();
        $this->registerGuest($table, 'segunda@example.test')->assertRedirect();

        $this->assertSame(1, $this->guestRowOf($table, 'primera@example.test')->waiting_list_position);
        $this->assertSame(2, $this->guestRowOf($table, 'segunda@example.test')->waiting_list_position);
    }

    public function test_with_the_setting_off_the_guest_page_shows_a_full_table(): void
    {
        $table = $this->fullTable();

        $this->get(route('gametables.show', $table->slug))
            ->assertInertia(fn (Assert $page) => $page
                ->where('guestEligibility.eligible', false)
                ->where('guestEligibility.reason', 'table_full')
                ->where('guestEligibility.waitingList', false));
    }

    public function test_the_guest_page_applies_the_registration_dates(): void
    {
        $table = $this->table(['registration_opens_at' => now()->addDay()]);

        $this->get(route('gametables.show', $table->slug))
            ->assertInertia(fn (Assert $page) => $page
                ->where('guestEligibility.eligible', false)
                ->where('guestEligibility.reason', 'registration_not_open'));
    }

    // Spectators do not depend on player seats, with or without the waiting list
    public function test_a_spectator_is_accepted_on_a_table_full_of_players(): void
    {
        $table = $this->fullTable(['max_spectators' => 2]);

        $this->actingAs($this->user)->get(route('gametables.show', $table->slug))
            ->assertInertia(fn (Assert $page) => $page
                ->where('eligibility.spectator.eligible', true)
                ->where('eligibility.spectator.reason', null));

        $this->register($this->user, $table, 'spectator')->assertRedirect();

        $this->assertSame('confirmed', $this->rowOf($table, $this->user->id)->status->value);
        $this->assertSame('spectator', $this->rowOf($table, $this->user->id)->role->value);
    }

    public function test_a_guest_spectator_is_accepted_on_a_table_full_of_players(): void
    {
        $table = $this->fullTable(['max_spectators' => 2]);

        $this->get(route('gametables.show', $table->slug))
            ->assertInertia(fn (Assert $page) => $page->where('guestEligibility.spectator.eligible', true));

        $this->registerGuest($table, 'invitada@example.test', 'spectator')->assertRedirect();

        $row = $this->guestRowOf($table, 'invitada@example.test');
        $this->assertSame('confirmed', $row->status->value);
        $this->assertSame('spectator', $row->role->value);
    }

    public function test_spectators_have_no_waiting_list(): void
    {
        $this->enableWaitingList();
        $table = $this->fullTable(['max_spectators' => 1]);
        $this->confirmed($table, 'spectator', 1);

        $this->actingAs($this->user)->get(route('gametables.show', $table->slug))
            ->assertInertia(fn (Assert $page) => $page
                ->where('eligibility.spectator.eligible', false)
                ->where('eligibility.spectator.reason', 'spectators_full'));

        $this->register($this->user, $table, 'spectator')
            ->assertStatus(422)
            ->assertJsonPath('errors.registration.0', __('game-tables::messages.errors.spectators_full'));
        $this->registerGuest($table, 'invitada@example.test', 'spectator')
            ->assertStatus(422)
            ->assertJsonPath('errors.registration.0', __('game-tables::messages.errors.spectators_full'));

        $this->assertSame(0, $this->waiting($table));
    }

    public function test_the_waiting_list_closes_with_the_registrations(): void
    {
        $this->enableWaitingList();
        $table = $this->fullTable(['registration_closes_at' => now()->subHour()]);

        $this->actingAs($this->user)->get(route('gametables.show', $table->slug))
            ->assertInertia(fn (Assert $page) => $page
                ->where('eligibility.eligible', false)
                ->where('eligibility.reason', 'registration_closed')
                ->where('eligibility.waitingList', false));

        $this->register($this->user, $table)
            ->assertStatus(422)
            ->assertJsonPath('errors.registration.0', __('game-tables::messages.errors.registration_closed'));
        $this->registerGuest($table, 'invitada@example.test')->assertStatus(422);

        $this->assertSame(0, $this->waiting($table));
    }

    public function test_a_user_cannot_join_the_waiting_list_twice(): void
    {
        $this->enableWaitingList();
        $table = $this->fullTable();

        $this->register($this->user, $table)->assertRedirect();
        $this->register($this->user, $table)
            ->assertStatus(422)
            ->assertJsonPath('errors.registration.0', __('game-tables::messages.errors.already_registered'));

        $this->assertSame(1, ParticipantModel::query()->where('game_table_id', $table->id)->where('user_id', $this->user->id)->count());
        $this->assertSame(1, $this->waiting($table));
    }

    public function test_a_guest_email_cannot_join_the_waiting_list_twice(): void
    {
        $this->enableWaitingList();
        $table = $this->fullTable();

        $this->registerGuest($table, 'invitada@example.test')->assertRedirect();
        // Confirmed: an unconfirmed guest sending the form again is sent the confirmation email again
        $this->confirmGuestEmails($table);
        $this->registerGuest($table, 'invitada@example.test')
            ->assertStatus(422)
            ->assertJsonPath('errors.registration.0', __('game-tables::messages.errors.guest_already_registered'));

        $this->assertSame(1, ParticipantModel::query()->where('game_table_id', $table->id)->where('email', 'invitada@example.test')->count());
    }

    public function test_a_registered_user_cannot_join_the_waiting_list(): void
    {
        $this->enableWaitingList();
        $table = $this->table(['max_players' => 1]);
        $this->register($this->user, $table)->assertRedirect();

        $this->actingAs($this->user)->get(route('gametables.show', $table->slug))
            ->assertInertia(fn (Assert $page) => $page
                ->where('eligibility.eligible', false)
                ->where('eligibility.reason', 'already_registered'));

        $this->register($this->user, $table)
            ->assertStatus(422)
            ->assertJsonPath('errors.registration.0', __('game-tables::messages.errors.already_registered'));

        $this->assertSame(0, $this->waiting($table));
        $this->assertSame(1, ParticipantModel::query()->where('game_table_id', $table->id)->count());
    }

    // With the list on, pending players of a manual table hold seats: page and server agree
    public function test_with_the_list_on_a_manual_table_full_of_pending_players_offers_the_waiting_list(): void
    {
        $this->enableWaitingList();
        $table = $this->table(['max_players' => 2, 'auto_confirm' => false]);
        $this->confirmed($table, 'player', 1);
        $this->pending($table, 1);

        $this->actingAs($this->user)->get(route('gametables.show', $table->slug))
            ->assertInertia(fn (Assert $page) => $page
                ->where('table.spotsAvailable', 0)
                ->where('eligibility.eligible', true)
                ->where('eligibility.waitingList', true));

        $this->register($this->user, $table)->assertRedirect();

        $this->assertSame('waiting_list', $this->rowOf($table, $this->user->id)->status->value);
        $this->assertSame(1, $this->rowOf($table, $this->user->id)->waiting_list_position);
    }

    public function test_with_the_list_off_pending_players_of_a_manual_table_do_not_hold_seats(): void
    {
        $table = $this->table(['max_players' => 2, 'auto_confirm' => false]);
        $this->confirmed($table, 'player', 1);
        $this->pending($table, 1);

        $this->register($this->user, $table)->assertRedirect();

        $this->assertSame('pending', $this->rowOf($table, $this->user->id)->status->value);
    }

    public function test_a_waiting_player_learns_the_promotion_waits_for_the_director_on_a_manual_table(): void
    {
        $this->enableWaitingList();
        $table = $this->fullTable(['auto_confirm' => false]);

        $this->actingAs($this->user)->post(route('gametables.register', $table->slug), ['role' => 'player'])
            ->assertSessionHas('success', __('game-tables::messages.success.waiting_list_manual', ['position' => 1]));
    }

    public function test_a_waiting_player_learns_the_promotion_is_automatic(): void
    {
        $this->enableWaitingList();
        $table = $this->fullTable();

        $this->actingAs($this->user)->post(route('gametables.register', $table->slug), ['role' => 'player'])
            ->assertSessionHas('success', __('game-tables::messages.success.waiting_list', ['position' => 1]));
    }

    public function test_a_guest_email_is_matched_whatever_its_case(): void
    {
        $this->enableWaitingList();
        $table = $this->fullTable();

        $this->registerGuest($table, 'Invitada@Example.TEST')->assertRedirect();
        $this->confirmGuestEmails($table);
        $this->registerGuest($table, 'invitada@example.test')
            ->assertStatus(422)
            ->assertJsonPath('errors.registration.0', __('game-tables::messages.errors.guest_already_registered'));

        $this->assertSame('invitada@example.test', $this->guestRowOf($table, 'invitada@example.test')->email);
        $this->assertSame(1, ParticipantModel::query()->where('game_table_id', $table->id)->whereNull('user_id')->count());
    }

    public function test_an_older_guest_row_with_capitals_is_still_found(): void
    {
        $table = $this->table();
        ParticipantModel::create([
            'id' => (string) Str::uuid(),
            'game_table_id' => $table->id,
            'first_name' => 'Invitada',
            'email' => 'Antigua@Example.test',
            'role' => 'player',
            'status' => 'confirmed',
            'confirmed_at' => now(),
            // Rows from before the email confirmation were filled in as confirmed
            'email_confirmed_at' => now(),
        ]);

        $this->registerGuest($table, 'antigua@example.test')
            ->assertStatus(422)
            ->assertJsonPath('errors.registration.0', __('game-tables::messages.errors.guest_already_registered'));
    }

    public function test_a_guest_who_can_only_be_a_spectator_is_told_so(): void
    {
        $this->freezeTime();
        $table = $this->fullTable(['max_spectators' => 2]);

        $this->get(route('gametables.show', $table->slug))
            ->assertInertia(fn (Assert $page) => $page
                ->where('guestEligibility.eligible', false)
                ->where('guestEligibility.spectator.eligible', true));

        $this->app['auth']->forgetGuards();
        $this->post(route('gametables.register-guest', $table->slug), [
            'first_name' => 'Invitada',
            'email' => 'invitada@example.test',
            'role' => 'spectator',
            'gdpr_consent' => true,
        ])->assertSessionHas(
            'success',
            'Te hemos enviado un email para confirmar la inscripción. Confírmala antes del '.now()->addHours(2)->format('d/m/Y H:i').' o la plaza se liberará.',
        );
    }

    // Turning the list on over a manual table already past its seats (confirmed + pending)
    public function test_turning_the_list_on_over_an_overbooked_manual_table(): void
    {
        $table = $this->table(['max_players' => 2, 'auto_confirm' => false]);
        $this->confirmed($table, 'player', 2);
        $this->pending($table, 1);
        $this->enableWaitingList();

        $this->register($this->user, $table)->assertRedirect();
        $this->assertSame('waiting_list', $this->rowOf($table, $this->user->id)->status->value);

        $pending = ParticipantModel::query()->where('game_table_id', $table->id)->where('status', 'pending')->firstOrFail();
        try {
            app(RegistrationServiceInterface::class)->confirm($pending->id);
            $this->fail('Confirming cannot exceed the player seats');
        } catch (TableFullException) {
            // Expected
        }
        $this->assertSame('pending', $pending->refresh()->status->value);
        $this->assertSame(2, $this->confirmedPlayers($table));
    }

    // Turning the list off keeps the rows already waiting: they are promoted with the common rules
    public function test_turning_the_list_off_with_players_waiting(): void
    {
        $table = $this->fullTable();
        $waiting = ParticipantModel::create([
            'id' => (string) Str::uuid(),
            'game_table_id' => $table->id,
            'user_id' => UserModel::factory()->create()->id,
            'role' => 'player',
            'status' => 'waiting_list',
            'waiting_list_position' => 1,
        ]);

        $this->register($this->user, $table)
            ->assertStatus(422)
            ->assertJsonPath('errors.registration.0', __('game-tables::messages.errors.table_full'));

        $leaving = ParticipantModel::query()->where('game_table_id', $table->id)->where('status', 'confirmed')->firstOrFail();
        app(RegistrationServiceInterface::class)->cancel($leaving->id);

        $this->assertSame('confirmed', $waiting->refresh()->status->value);
        $this->assertSame(2, $this->confirmedPlayers($table));
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
    private function confirmGuestEmails(GameTableModel $table): void
    {
        ParticipantModel::query()->where('game_table_id', $table->id)->whereNull('user_id')->update(['email_confirmed_at' => now()]);
    }

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

    private function enableWaitingList(): void
    {
        config()->set('modules.settings.game-tables.registration.waiting_list_enabled', true);
    }

    private function rowOf(GameTableModel $table, string $userId): ParticipantModel
    {
        return ParticipantModel::query()->where('game_table_id', $table->id)->where('user_id', $userId)->firstOrFail();
    }

    private function guestRowOf(GameTableModel $table, string $email): ParticipantModel
    {
        return ParticipantModel::query()->where('game_table_id', $table->id)->where('email', $email)->firstOrFail();
    }

    private function waiting(GameTableModel $table): int
    {
        return ParticipantModel::query()->where('game_table_id', $table->id)->where('status', 'waiting_list')->count();
    }

    private function confirmedPlayers(GameTableModel $table): int
    {
        return ParticipantModel::query()->where('game_table_id', $table->id)->where('role', 'player')->where('status', 'confirmed')->count();
    }

    private function confirmed(GameTableModel $table, string $role, int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            ParticipantModel::create([
                'id' => (string) Str::uuid(),
                'game_table_id' => $table->id,
                'user_id' => UserModel::factory()->create()->id,
                'role' => $role,
                'status' => 'confirmed',
                'confirmed_at' => now(),
            ]);
        }
    }

    private function pending(GameTableModel $table, int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            ParticipantModel::create([
                'id' => (string) Str::uuid(),
                'game_table_id' => $table->id,
                'user_id' => UserModel::factory()->create()->id,
                'role' => 'player',
                'status' => 'pending',
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function fullTable(array $attributes = []): GameTableModel
    {
        $table = $this->table(['max_players' => 2, ...$attributes]);
        $this->confirmed($table, 'player', 2);

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
            'max_spectators' => 0,
            'language' => 'es',
            'registration_type' => 'everyone',
            'auto_confirm' => true,
            'is_published' => true,
            ...$attributes,
        ]);
    }
}
