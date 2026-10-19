<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Feature\Http\Controllers;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameSystemModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameTableModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\ParticipantModel;
use Tests\Support\Modules\ModuleTestCase;

/**
 * Guest registrations per IP, with the limits of the settings: a form error on the table page,
 * a 429 for JSON clients. Signed-in registrations are not limited.
 */
final class GuestRateLimitTest extends ModuleTestCase
{
    protected ?string $moduleName = 'game-tables';

    protected bool $autoEnableModule = true;

    private const MESSAGE = 'Has hecho demasiadas inscripciones seguidas. Espera unos minutos y vuelve a intentarlo.';

    protected function setUp(): void
    {
        parent::setUp();

        // Make the module routes, added after boot, resolvable by name
        app('router')->getRoutes()->refreshNameLookups();

        Notification::fake();
        config()->set('modules.settings.game-tables', []);
        $this->travelTo(CarbonImmutable::parse('2026-11-02 10:00:00'));

        $system = GameSystemModel::create(['id' => (string) Str::uuid(), 'name' => 'Sistema', 'slug' => 'sistema', 'is_active' => true]);
        GameTableModel::create([
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
            'max_players' => 20,
            'max_spectators' => 0,
            'language' => 'es',
            'is_published' => true,
            'auto_confirm' => true,
            'registration_type' => 'everyone',
        ]);
    }

    public function test_the_sixth_guest_submission_in_a_minute_gets_the_form_error(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $this->submit("invitado{$i}@example.test")->assertSessionHasNoErrors();
        }

        $this->submit('invitado6@example.test')
            ->assertStatus(302)
            ->assertRedirect(route('gametables.show', 'la-cripta'))
            ->assertSessionHasErrors(['registration' => self::MESSAGE]);
        $this->assertSame(0, ParticipantModel::query()->where('email', 'invitado6@example.test')->count());
    }

    public function test_another_ip_is_counted_apart(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $this->submit("invitado{$i}@example.test")->assertSessionHasNoErrors();
        }

        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.2'])
            ->submit('invitado6@example.test')
            ->assertSessionHasNoErrors();
        $this->assertSame(1, ParticipantModel::query()->where('email', 'invitado6@example.test')->count());
    }

    public function test_json_clients_get_a_429_with_the_same_message(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $this->postJson(route('gametables.register-guest', 'la-cripta'), $this->form("invitado{$i}@example.test"))->assertRedirect();
        }

        $this->postJson(route('gametables.register-guest', 'la-cripta'), $this->form('invitado6@example.test'))
            ->assertStatus(429)
            ->assertJson(['message' => self::MESSAGE]);
    }

    public function test_the_limits_come_from_the_settings(): void
    {
        config()->set('modules.settings.game-tables.guest_rate_limit.per_minute', 2);

        $this->submit('invitado1@example.test')->assertSessionHasNoErrors();
        $this->submit('invitado2@example.test')->assertSessionHasNoErrors();

        $this->submit('invitado3@example.test')->assertSessionHasErrors(['registration' => self::MESSAGE]);
    }

    public function test_the_hourly_limit_is_counted_apart_from_the_minute(): void
    {
        config()->set('modules.settings.game-tables.guest_rate_limit.per_minute', 100);
        config()->set('modules.settings.game-tables.guest_rate_limit.per_hour', 3);

        for ($i = 1; $i <= 3; $i++) {
            $this->submit("invitado{$i}@example.test")->assertSessionHasNoErrors();
            $this->travel(2)->minutes();
        }

        $this->submit('invitado4@example.test')->assertSessionHasErrors(['registration' => self::MESSAGE]);
    }

    public function test_a_signed_in_registration_is_not_limited(): void
    {
        for ($i = 1; $i <= 6; $i++) {
            $this->submit("invitado{$i}@example.test");
        }

        $user = UserModel::factory()->create();
        $this->actingAs($user)
            ->from(route('gametables.show', 'la-cripta'))
            ->post(route('gametables.register', 'la-cripta'), ['role' => 'player'])
            ->assertSessionHasNoErrors();
        $this->assertSame(1, ParticipantModel::query()->where('user_id', $user->id)->count());

        $middleware = app('router')->getRoutes()->getByName('gametables.register')?->gatherMiddleware() ?? [];
        $this->assertNotContains('throttle:game-tables-guest-registration', $middleware);
    }

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
}
