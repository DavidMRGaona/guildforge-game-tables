<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Feature\Http\Controllers;

use App\Application\Authorization\Services\AssociationMembershipServiceInterface;
use App\Application\Authorization\Services\AuthorizationServiceInterface;
use App\Application\Services\SettingsServiceInterface;
use App\Infrastructure\Persistence\Eloquent\Models\RoleModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Modules\GameTables\Application\Services\MembershipCheckerInterface;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameSystemModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameTableModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\ParticipantModel;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Response;
use Tests\Support\Modules\ModuleTestCase;

/**
 * The server refuses every registration the table page would not offer, and creates nothing.
 */
final class RegistrationEligibilityTest extends ModuleTestCase
{
    protected ?string $moduleName = 'game-tables';

    protected bool $autoEnableModule = true;

    private UserModel $user;

    private GameSystemModel $gameSystem;

    protected function setUp(): void
    {
        parent::setUp();

        // Far from a clock change: start times are stored as wall-clock times, ambiguous in the hour
        // repeated in October (GuildForge#107), and these tests store times minutes or hours from now
        $this->travelTo(CarbonImmutable::parse('2026-11-04 18:00:00'));

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

    public function test_a_user_registers_to_an_open_table(): void
    {
        $table = $this->table();

        $this->register($table)->assertRedirect();

        $this->assertSame(1, $this->registrations($table));
    }

    public function test_an_unpublished_table_refuses_registrations(): void
    {
        $this->assertRefused($this->table(['is_published' => false]), 'registration_closed');
    }

    public function test_a_cancelled_table_refuses_registrations(): void
    {
        $this->assertRefused($this->table(['status' => 'cancelled']), 'registration_closed');
    }

    public function test_a_full_status_table_with_free_seats_accepts_registrations(): void
    {
        $table = $this->table(['status' => 'full']);

        $this->register($table)->assertRedirect();

        $this->assertSame(1, $this->registrations($table));
    }

    public function test_registration_before_the_window_opens_is_refused(): void
    {
        $this->assertRefused($this->table(['registration_opens_at' => now()->addDay()]), 'registration_not_open');
    }

    public function test_registration_after_the_window_closes_is_refused(): void
    {
        $this->assertRefused($this->table(['registration_closes_at' => now()->subHour()]), 'registration_closed');
    }

    public function test_registration_inside_the_window_is_accepted(): void
    {
        $table = $this->table(['registration_opens_at' => now()->subDay(), 'registration_closes_at' => now()->addDay()]);

        $this->register($table)->assertRedirect();

        $this->assertSame(1, $this->registrations($table));
    }

    // The association member is the core role chosen in the site settings ("socio" by default)
    public function test_a_user_with_the_association_member_role_registers_during_the_early_access(): void
    {
        $this->grant($this->user, 'socio');
        $table = $this->tableInEarlyAccess();

        $this->register($table)->assertRedirect();

        $this->assertSame(1, $this->registrations($table));
    }

    public function test_a_user_with_only_the_member_role_waits_for_the_opening(): void
    {
        $this->grant($this->user, 'member');

        $this->assertRefused($this->tableInEarlyAccess(), 'registration_not_open');
    }

    public function test_the_configured_association_member_role_is_respected(): void
    {
        $this->configureAssociationRole('junta');
        $this->grant($this->user, 'junta');
        $table = $this->tableInEarlyAccess();

        $this->register($table)->assertRedirect();

        $this->assertSame(1, $this->registrations($table));
    }

    public function test_the_default_role_no_longer_counts_once_another_role_is_configured(): void
    {
        $this->configureAssociationRole('junta');
        $this->grant($this->user, 'socio');

        $this->assertRefused($this->tableInEarlyAccess(), 'registration_not_open');
    }

    public function test_a_user_with_the_association_member_role_registers_to_a_members_only_table(): void
    {
        $this->grant($this->user, 'socio');
        $table = $this->table(['registration_type' => 'members_only']);

        $this->register($table)->assertRedirect();

        $this->assertSame(1, $this->registrations($table));
    }

    // A base role in the setting means nobody is an association member: the general rules apply
    public function test_without_an_association_member_role_nobody_gets_early_access(): void
    {
        $this->configureAssociationRole('member');
        $this->grant($this->user, 'member');

        $this->assertFalse(app(MembershipCheckerInterface::class)->isActiveMember($this->user->id));
        $this->assertRefused($this->tableInEarlyAccess(), 'registration_not_open');
        $this->assertRefused($this->table(['registration_type' => 'members_only']), 'members_only');
    }

    public function test_without_an_association_member_role_open_tables_work_as_before(): void
    {
        $this->configureAssociationRole('member');
        $table = $this->table(['registration_opens_at' => now()->subDay()]);

        $this->register($table)->assertRedirect();

        $this->assertSame(1, $this->registrations($table));
    }

    public function test_an_unknown_user_is_not_an_association_member(): void
    {
        $this->assertFalse(app(MembershipCheckerInterface::class)->isActiveMember((string) Str::uuid()));
    }

    public function test_a_members_only_table_refuses_a_non_member(): void
    {
        $this->actAsMember(false);

        $this->assertRefused($this->table(['registration_type' => 'members_only']), 'members_only');
    }

    public function test_a_members_only_table_accepts_a_member(): void
    {
        $this->actAsMember(true);
        $table = $this->table(['registration_type' => 'members_only']);

        $this->register($table)->assertRedirect();

        $this->assertSame(1, $this->registrations($table));
    }

    public function test_a_member_registers_during_the_early_access(): void
    {
        $this->actAsMember(true);
        $table = $this->tableInEarlyAccess();

        $this->register($table)->assertRedirect();

        $this->assertSame(1, $this->registrations($table));
    }

    public function test_a_non_member_cannot_register_during_the_early_access(): void
    {
        $this->actAsMember(false);

        $this->assertRefused($this->tableInEarlyAccess(), 'registration_not_open');
    }

    public function test_without_the_membership_integration_a_member_waits_for_the_opening(): void
    {
        config()->set('modules.settings.game-tables.membership_integration.enabled', false);
        $this->actAsMember(true);

        $this->assertRefused($this->tableInEarlyAccess(), 'registration_not_open');
    }

    public function test_a_table_without_free_seats_refuses_players(): void
    {
        $table = $this->table(['max_players' => 2]);
        $this->confirmed($table, 'player', 2);

        $this->assertRefused($table, 'table_full');
    }

    // Spectator seats do not depend on player seats
    public function test_a_table_without_free_player_seats_still_accepts_spectators(): void
    {
        $table = $this->table(['max_players' => 2, 'max_spectators' => 3]);
        $this->confirmed($table, 'player', 2);

        $this->register($table, 'spectator')->assertRedirect();

        $this->assertSame(3, $this->registrations($table));
    }

    public function test_a_table_without_spectator_seats_refuses_spectators(): void
    {
        $this->assertRefused($this->table(['max_spectators' => 0]), 'spectators_full', 'spectator');
    }

    public function test_a_table_with_its_spectator_seats_taken_refuses_spectators(): void
    {
        $table = $this->table(['max_spectators' => 1]);
        $this->confirmed($table, 'spectator', 1);

        $this->assertRefused($table, 'spectators_full', 'spectator');
    }

    public function test_a_table_with_free_spectator_seats_accepts_spectators(): void
    {
        $table = $this->table(['max_spectators' => 1]);

        $this->register($table, 'spectator')->assertRedirect();

        $this->assertSame(1, $this->registrations($table));
    }

    public function test_a_second_registration_of_the_same_user_is_refused(): void
    {
        $table = $this->table();
        $this->register($table)->assertRedirect();

        $this->register($table, 'player', json: true)
            ->assertStatus(422)
            ->assertJsonPath('errors.registration.0', __('game-tables::messages.errors.already_registered'));

        $this->assertSame(1, $this->registrations($table));
    }

    public function test_a_refusal_from_the_table_page_comes_back_as_a_form_error(): void
    {
        $table = $this->table(['is_published' => false]);

        $this->register($table)
            ->assertRedirect()
            ->assertSessionHasErrors(['registration' => __('game-tables::messages.errors.registration_closed')]);

        $this->assertSame(0, $this->registrations($table));
    }

    public function test_a_guest_cannot_register_to_an_unpublished_table(): void
    {
        $table = $this->table(['is_published' => false]);

        $this->registerGuest($table)
            ->assertStatus(422)
            ->assertJsonPath('errors.registration.0', __('game-tables::messages.errors.registration_closed'));

        $this->assertSame(0, $this->registrations($table));
    }

    public function test_a_guest_cannot_register_to_a_full_table(): void
    {
        $table = $this->table(['max_players' => 1]);
        $this->confirmed($table, 'player', 1);

        $this->registerGuest($table)
            ->assertStatus(422)
            ->assertJsonPath('errors.registration.0', __('game-tables::messages.errors.table_full'));

        $this->assertSame(1, $this->registrations($table));
    }

    public function test_a_guest_spectator_needs_a_free_spectator_seat(): void
    {
        $table = $this->table(['max_spectators' => 0]);

        $this->registerGuest($table, 'spectator')
            ->assertStatus(422)
            ->assertJsonPath('errors.registration.0', __('game-tables::messages.errors.spectators_full'));

        $this->assertSame(0, $this->registrations($table));
    }

    public function test_a_guest_spectator_registers_with_a_free_spectator_seat(): void
    {
        $table = $this->table(['max_spectators' => 1]);

        $this->registerGuest($table, 'spectator')->assertRedirect();

        $this->assertSame(1, $this->registrations($table));
    }

    #[DataProvider('directorRoles')]
    public function test_a_user_cannot_register_as_a_director(string $role): void
    {
        $table = $this->table();

        $this->register($table, $role, json: true)
            ->assertStatus(422)
            ->assertJsonPath('errors.role.0', __('game-tables::messages.validation.invalid_role'));

        $this->assertSame(0, $this->registrations($table));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function directorRoles(): array
    {
        return ['game master' => ['game_master'], 'co-director' => ['co_gm']];
    }

    public function test_a_table_in_progress_that_accepts_late_registrations_admits_users_and_guests(): void
    {
        $table = $this->table(['status' => 'in_progress', 'accepts_registrations_in_progress' => true]);

        $this->register($table)->assertRedirect();
        $this->registerGuest($table)->assertRedirect();

        $this->assertSame(2, $this->registrations($table));
    }

    // The status says why, as the time does once the start has passed: never a bare «closed»
    public function test_a_table_in_progress_refuses_users_and_guests_by_default_because_it_has_started(): void
    {
        $table = $this->table(['status' => 'in_progress']);

        $this->assertRefused($table, 'table_started', message: 'La partida ya ha comenzado');
        $this->registerGuest($table)
            ->assertStatus(422)
            ->assertJsonPath('errors.registration.0', 'La partida ya ha comenzado');

        $this->assertSame(0, $this->registrations($table));
    }

    public function test_a_completed_table_refuses_users_and_guests_because_it_has_ended(): void
    {
        $table = $this->table(['status' => 'completed']);

        $this->assertRefused($table, 'table_ended', message: 'La partida ya ha terminado');
        $this->registerGuest($table)
            ->assertStatus(422)
            ->assertJsonPath('errors.registration.0', 'La partida ya ha terminado');

        $this->assertSame(0, $this->registrations($table));
    }

    public function test_a_guest_registers_to_a_full_status_table_with_free_seats(): void
    {
        $table = $this->table(['status' => 'full']);

        $this->registerGuest($table)->assertRedirect();

        $this->assertSame(1, $this->registrations($table));
    }

    public function test_a_table_that_has_started_refuses_users_and_guests(): void
    {
        $table = $this->table(['starts_at' => now()->subMinute()]);

        $this->assertRefused($table, 'table_started', message: 'La partida ya ha comenzado');
        $this->registerGuest($table)
            ->assertStatus(422)
            ->assertJsonPath('errors.registration.0', 'La partida ya ha comenzado');

        $this->assertSame(0, $this->registrations($table));
    }

    public function test_a_closing_date_after_the_start_does_not_keep_the_table_open(): void
    {
        $this->assertRefused(
            $this->table(['starts_at' => now()->subMinute(), 'registration_closes_at' => now()->addDay()]),
            'table_started',
            message: 'La partida ya ha comenzado',
        );
    }

    // The time opens the session, not the director pressing «Iniciar»: the table is still scheduled
    public function test_a_table_admitting_registrations_in_play_admits_users_and_guests_during_the_session(): void
    {
        $table = $this->table(['starts_at' => now()->subHour(), 'accepts_registrations_in_progress' => true]);

        $this->register($table)->assertRedirect();
        $this->registerGuest($table)->assertRedirect();

        $this->assertSame(2, $this->registrations($table));
    }

    public function test_a_table_admitting_registrations_in_play_refuses_them_once_it_has_ended(): void
    {
        $table = $this->table(['starts_at' => now()->subHours(5), 'accepts_registrations_in_progress' => true]);

        $this->assertRefused($table, 'table_ended', message: 'La partida ya ha terminado');
        $this->registerGuest($table)
            ->assertStatus(422)
            ->assertJsonPath('errors.registration.0', 'La partida ya ha terminado');
    }

    public function test_during_the_session_a_full_table_offers_no_waiting_list(): void
    {
        config()->set('modules.settings.game-tables.registration.waiting_list_enabled', true);
        $table = $this->table(['starts_at' => now()->subHour(), 'accepts_registrations_in_progress' => true, 'max_players' => 1]);
        $this->confirmed($table, 'player', 1);

        $this->assertRefused($table, 'table_full');
        $this->registerGuest($table)
            ->assertStatus(422)
            ->assertJsonPath('errors.registration.0', __('game-tables::messages.errors.table_full'));

        $this->assertSame(1, $this->registrations($table));
    }

    public function test_the_time_messages_exist_in_both_languages(): void
    {
        $texts = [
            'table_started' => ['es' => 'La partida ya ha comenzado', 'en' => 'The session has already started'],
            'table_ended' => ['es' => 'La partida ya ha terminado', 'en' => 'The session has already ended'],
        ];

        foreach ($texts as $key => $byLocale) {
            foreach ($byLocale as $locale => $text) {
                $this->assertSame($text, trans('game-tables::messages.errors.'.$key, [], $locale));
            }
        }
    }

    private function assertRefused(GameTableModel $table, string $reason, string $role = 'player', ?string $message = null): void
    {
        $before = $this->registrations($table);

        $this->register($table, $role, json: true)
            ->assertStatus(422)
            ->assertJsonPath('errors.registration.0', $message ?? __('game-tables::messages.errors.'.$reason));
        $this->assertSame($before, $this->registrations($table));
    }

    /**
     * @return TestResponse<Response>
     */
    private function register(GameTableModel $table, string $role = 'player', bool $json = false): TestResponse
    {
        $this->actingAs($this->user);
        $url = route('gametables.register', $table->slug);

        return $json ? $this->postJson($url, ['role' => $role]) : $this->post($url, ['role' => $role]);
    }

    /**
     * @return TestResponse<Response>
     */
    private function registerGuest(GameTableModel $table, string $role = 'player'): TestResponse
    {
        return $this->postJson(route('gametables.register-guest', $table->slug), [
            'first_name' => 'Invitada',
            'email' => 'invitada@example.test',
            'role' => $role,
            'gdpr_consent' => true,
        ]);
    }

    private function registrations(GameTableModel $table): int
    {
        return ParticipantModel::query()->where('game_table_id', $table->id)->count();
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

    private function actAsMember(bool $isMember): void
    {
        $memberId = $isMember ? $this->user->id : null;

        $this->app->instance(MembershipCheckerInterface::class, new readonly class($memberId) implements MembershipCheckerInterface
        {
            public function __construct(private ?string $memberId) {}

            public function isActiveMember(string $userId): bool
            {
                return $userId === $this->memberId;
            }
        });
    }

    private function grant(UserModel $user, string $roleName): void
    {
        $role = RoleModel::query()->firstOrCreate(['name' => $roleName], ['display_name' => ucfirst($roleName)]);
        $user->roles()->syncWithoutDetaching([$role->id]);
        app(AuthorizationServiceInterface::class)->invalidateCache();
    }

    private function configureAssociationRole(string $roleName): void
    {
        app(SettingsServiceInterface::class)->set(AssociationMembershipServiceInterface::SETTING_KEY, $roleName);
    }

    private function tableInEarlyAccess(): GameTableModel
    {
        return $this->table(['registration_opens_at' => now()->addDay(), 'members_early_access_days' => 3]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function table(array $attributes = []): GameTableModel
    {
        return GameTableModel::create([
            'id' => (string) Str::uuid(),
            'game_system_id' => $this->gameSystem->id,
            'created_by' => $this->user->id,
            'title' => 'Mesa de prueba',
            'slug' => 'mesa-de-prueba-'.Str::random(6),
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
