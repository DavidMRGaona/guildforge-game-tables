<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Feature\Http\Controllers;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Modules\GameTables\Application\DTOs\RegisterParticipantDTO;
use Modules\GameTables\Application\Services\RegistrationServiceInterface;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameSystemModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameTableModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\ParticipantModel;
use Symfony\Component\HttpFoundation\Response;
use Tests\Support\Modules\ModuleTestCase;

/**
 * What the public guest form answers: one message per outcome of the confirmation step, each
 * refusal with its own text, and the fingerprint of the web submissions.
 */
final class GuestRegistrationAnswersTest extends ModuleTestCase
{
    protected ?string $moduleName = 'game-tables';

    protected bool $autoEnableModule = true;

    private GameTableModel $table;

    protected function setUp(): void
    {
        parent::setUp();

        // Make the module routes, added after boot, resolvable by name
        app('router')->getRoutes()->refreshNameLookups();

        Notification::fake();
        config()->set('modules.settings.game-tables', []);
        $this->travelTo(CarbonImmutable::parse('2026-11-02 10:00:00'));

        $system = GameSystemModel::create(['id' => (string) Str::uuid(), 'name' => 'Sistema', 'slug' => 'sistema', 'is_active' => true]);
        $this->table = GameTableModel::create([
            'id' => (string) Str::uuid(),
            'game_system_id' => $system->id,
            'created_by' => UserModel::factory()->create()->id,
            'title' => 'La cripta',
            'slug' => 'la-cripta',
            'starts_at' => now()->addWeek(),
            'duration_minutes' => 240,
            'table_type' => 'one_shot',
            'table_format' => 'in_person',
            'status' => 'scheduled',
            'min_players' => 1,
            'max_players' => 5,
            'max_spectators' => 0,
            'language' => 'es',
            'is_published' => true,
            'auto_confirm' => true,
            'registration_type' => 'everyone',
        ]);
    }

    public function test_a_held_place_tells_the_deadline(): void
    {
        $this->submit('john@example.test')->assertSessionHasNoErrors()->assertSessionHas(
            'success',
            'Te hemos enviado un email para confirmar la inscripción. Confírmala antes del 02/11/2026 12:00 o la plaza se liberará.',
        );
    }

    public function test_a_held_place_on_the_waiting_list_tells_the_deadline_and_the_position(): void
    {
        config()->set('modules.settings.game-tables.registration.waiting_list_enabled', true);
        $this->table->update(['max_players' => 1]);
        $this->confirmedGuest('primero@example.test');

        $this->submit('john@example.test')->assertSessionHasNoErrors()->assertSessionHas(
            'success',
            'Te hemos enviado un email para confirmar la inscripción. Confírmala antes del 02/11/2026 12:00 para conservar tu puesto 1 en la lista de espera.',
        );
    }

    public function test_the_third_unconfirmed_guest_is_told_the_place_is_not_guaranteed(): void
    {
        $this->submit('john@example.test')->assertSessionHasNoErrors();
        $this->submit('bea@example.test')->assertSessionHasNoErrors();

        $this->submit('mary@example.test')->assertSessionHasNoErrors()->assertSessionHas(
            'success',
            'Te hemos enviado un email para confirmar la inscripción. Tu plaza no está garantizada hasta que la confirmes.',
        );
    }

    public function test_wait_mode_tells_the_place_is_not_guaranteed(): void
    {
        config()->set('modules.settings.game-tables.guest_confirmation.mode', 'wait');

        $this->submit('john@example.test')->assertSessionHasNoErrors()->assertSessionHas(
            'success',
            'Te hemos enviado un email para confirmar la inscripción. Tu plaza no está garantizada hasta que la confirmes.',
        );
    }

    public function test_sending_the_form_again_while_unconfirmed_points_to_the_inbox(): void
    {
        $this->submit('john@example.test')->assertSessionHasNoErrors();

        $this->submit('JOHN@example.test')->assertSessionHasNoErrors()->assertSessionHas(
            'success',
            'Ya tienes una inscripción pendiente: revisa tu correo.',
        );
        $this->assertSame(1, ParticipantModel::query()->where('email', 'john@example.test')->count());
    }

    public function test_a_confirmed_guest_email_is_refused_as_a_duplicate(): void
    {
        $this->confirmedGuest('john@example.test');

        $this->submit('john@example.test')
            ->assertSessionHasErrors(['registration' => 'Ya existe una inscripción con este email']);
    }

    public function test_a_full_table_keeps_its_own_message(): void
    {
        $this->table->update(['max_players' => 1]);
        $this->confirmedGuest('primero@example.test');

        $this->submit('john@example.test')
            ->assertSessionHasErrors(['registration' => 'La mesa está completa']);
    }

    public function test_an_account_email_asks_to_sign_in(): void
    {
        UserModel::factory()->create(['email' => 'cuenta@example.test']);

        $this->submit('cuenta@example.test')
            ->assertSessionHasErrors(['account' => 'Este email pertenece a una cuenta. Inicia sesión para inscribirte.']);
        $this->assertSame(0, ParticipantModel::query()->count());
    }

    public function test_an_account_email_gives_json_clients_the_login_link(): void
    {
        UserModel::factory()->create(['email' => 'cuenta@example.test']);

        $this->postJson(route('gametables.register-guest', 'la-cripta'), $this->form('cuenta@example.test'))
            ->assertStatus(422)
            ->assertJsonPath('message', 'Este email pertenece a una cuenta. Inicia sesión para inscribirte.')
            ->assertJsonPath('errors.account.0', 'Este email pertenece a una cuenta. Inicia sesión para inscribirte.')
            ->assertJsonPath('login_url', route('login'));
    }

    public function test_a_guest_submission_stores_the_fingerprint_of_the_connection(): void
    {
        $this->submit('john@example.test')->assertSessionHasNoErrors();

        $this->assertSame(
            hash_hmac('sha256', '127.0.0.1|Symfony', (string) config('app.key')),
            ParticipantModel::query()->where('email', 'john@example.test')->value('fingerprint'),
        );
    }

    public function test_a_signed_in_web_registration_stores_the_fingerprint_too(): void
    {
        $user = UserModel::factory()->create();

        $this->actingAs($user)
            ->post(route('gametables.register', 'la-cripta'), ['role' => 'player'])
            ->assertSessionHasNoErrors();

        $this->assertSame(
            hash_hmac('sha256', '127.0.0.1|Symfony', (string) config('app.key')),
            ParticipantModel::query()->where('user_id', $user->id)->value('fingerprint'),
        );
    }

    public function test_a_registration_from_the_panel_stores_no_fingerprint(): void
    {
        $user = UserModel::factory()->create();

        app(RegistrationServiceInterface::class)->registerByAdmin(new RegisterParticipantDTO(
            gameTableId: $this->table->id,
            userId: (string) $user->id,
            fingerprint: str_repeat('f', 64),
        ));

        $this->assertNull(ParticipantModel::query()->where('user_id', $user->id)->value('fingerprint'));
    }

    /**
     * @return TestResponse<Response>
     */
    private function submit(string $email): TestResponse
    {
        return $this->from(route('gametables.show', 'la-cripta'))
            ->post(route('gametables.register-guest', 'la-cripta'), $this->form($email));
    }

    /**
     * @return array<string, mixed>
     */
    private function form(string $email): array
    {
        return ['first_name' => 'John', 'email' => $email, 'gdpr_consent' => true];
    }

    private function confirmedGuest(string $email): void
    {
        ParticipantModel::create([
            'id' => (string) Str::uuid(),
            'game_table_id' => $this->table->id,
            'first_name' => 'Otra',
            'email' => $email,
            'role' => 'player',
            'status' => 'confirmed',
            'confirmed_at' => now(),
            'email_confirmed_at' => now(),
            'cancellation_token' => bin2hex(random_bytes(32)),
        ]);
    }
}
