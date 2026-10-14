<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Feature\Http\Controllers;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Modules\GameTables\Application\Services\EligibilityServiceInterface;
use Modules\GameTables\Domain\Repositories\GameTableRepositoryInterface;
use Modules\GameTables\Domain\Repositories\ParticipantRepositoryInterface;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameSystemModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameTableModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\ParticipantModel;
use Modules\GameTables\Infrastructure\Services\EligibilityService;
use Modules\Memberships\Application\Services\MemberServiceInterface;
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

    public function test_a_completed_table_refuses_registrations(): void
    {
        $this->assertRefused($this->table(['status' => 'completed']), 'registration_closed');
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

    public function test_a_table_without_free_seats_refuses_spectators_too(): void
    {
        $table = $this->table(['max_players' => 2, 'max_spectators' => 3]);
        $this->confirmed($table, 'player', 2);

        $this->assertRefused($table, 'table_full', 'spectator');
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

    #[\PHPUnit\Framework\Attributes\DataProvider('directorRoles')]
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

    private function assertRefused(GameTableModel $table, string $reason, string $role = 'player'): void
    {
        $before = $this->registrations($table);

        $this->register($table, $role, json: true)
            ->assertStatus(422)
            ->assertJsonPath('errors.registration.0', __('game-tables::messages.errors.'.$reason));

        $this->assertSame($before, $this->registrations($table));
    }

    /**
     * @return TestResponse<\Symfony\Component\HttpFoundation\Response>
     */
    private function register(GameTableModel $table, string $role = 'player', bool $json = false): TestResponse
    {
        $this->actingAs($this->user);
        $url = route('gametables.register', $table->slug);

        return $json ? $this->postJson($url, ['role' => $role]) : $this->post($url, ['role' => $role]);
    }

    /**
     * @return TestResponse<\Symfony\Component\HttpFoundation\Response>
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
        $members = $this->createStub(MemberServiceInterface::class);
        $members->method('getActiveMembers')->willReturn($isMember ? [(object) ['userId' => $this->user->id]] : []);

        $this->app->bind(EligibilityServiceInterface::class, fn (): EligibilityService => new EligibilityService(
            app(ParticipantRepositoryInterface::class),
            app(GameTableRepositoryInterface::class),
            $members,
        ));
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
