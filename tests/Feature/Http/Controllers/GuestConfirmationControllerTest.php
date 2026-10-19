<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Feature\Http\Controllers;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Carbon\CarbonImmutable;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Modules\GameTables\Application\DTOs\RegisterParticipantDTO;
use Modules\GameTables\Application\Services\RegistrationServiceInterface;
use Modules\GameTables\Domain\Enums\ParticipantRole;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameSystemModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameTableModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\ParticipantModel;
use Modules\GameTables\Notifications\GuestEmailConfirmationRequest;
use Modules\GameTables\Notifications\GuestRegistrationConfirmation;
use Tests\Support\Modules\ModuleTestCase;

/**
 * The link of the confirmation email: opening it shows a page, only its button confirms.
 */
final class GuestConfirmationControllerTest extends ModuleTestCase
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

    public function test_show_never_confirms(): void
    {
        $row = $this->submitGuest();

        $response = $this->get(route('gametables.confirm-guest', $row->confirmation_token));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('GameTables/ConfirmRegistration', shouldExist: false)
            ->where('participant.first_name', 'Ana')
            ->where('participant.role_label', 'Jugador')
            ->where('gameTable.title', 'La cripta')
            ->where('gameTable.slug', 'la-cripta')
            ->where('token', $row->confirmation_token)
            ->where('alreadyConfirmed', false)
            ->missing('participant.email'));
        $this->assertNull($row->refresh()->email_confirmed_at);
    }

    public function test_show_tells_when_it_was_already_confirmed(): void
    {
        $row = $this->submitGuest();
        $this->post(route('gametables.confirm-guest.store', $row->confirmation_token));

        $this->get(route('gametables.confirm-guest', $row->confirmation_token))
            ->assertInertia(fn ($page) => $page
                ->component('GameTables/ConfirmRegistration', shouldExist: false)
                ->where('alreadyConfirmed', true));
    }

    public function test_store_confirms_and_redirects_to_the_table(): void
    {
        $row = $this->submitGuest();

        $response = $this->post(route('gametables.confirm-guest.store', $row->confirmation_token));

        $response->assertRedirect(route('gametables.show', 'la-cripta'));
        $response->assertSessionHas('success', 'Tu inscripción está confirmada. Te hemos enviado un email con los detalles.');
        $this->assertNotNull($row->refresh()->email_confirmed_at);
        Notification::assertSentOnDemand(
            GuestRegistrationConfirmation::class,
            fn (GuestRegistrationConfirmation $notification, array $channels, AnonymousNotifiable $notifiable): bool => $notifiable->routes['mail'] === 'ana@example.test',
        );
    }

    public function test_a_second_post_says_it_was_already_confirmed(): void
    {
        $row = $this->submitGuest();
        $this->post(route('gametables.confirm-guest.store', $row->confirmation_token));

        $response = $this->post(route('gametables.confirm-guest.store', $row->confirmation_token));

        $response->assertRedirect(route('gametables.show', 'la-cripta'));
        $response->assertSessionHas('success', 'Tu inscripción ya estaba confirmada.');
    }

    public function test_a_closed_table_answers_with_the_closing_message(): void
    {
        $row = $this->submitGuest();
        $this->table->update(['registration_closes_at' => now()]);

        $response = $this->post(route('gametables.confirm-guest.store', $row->confirmation_token));

        $response->assertRedirect(route('gametables.show', 'la-cripta'));
        $response->assertSessionHas('error', 'Las inscripciones ya se han cerrado.');
    }

    public function test_a_table_filled_meanwhile_answers_with_the_filled_up_message(): void
    {
        config()->set('modules.settings.game-tables.guest_confirmation.mode', 'wait');
        $this->table->update(['max_players' => 1]);
        $row = $this->submitGuest();
        app(RegistrationServiceInterface::class)->registerByAdmin(new RegisterParticipantDTO(
            gameTableId: $this->table->id,
            userId: UserModel::factory()->create()->id,
            role: ParticipantRole::Player,
        ));

        $response = $this->post(route('gametables.confirm-guest.store', $row->confirmation_token));

        $response->assertRedirect(route('gametables.show', 'la-cripta'));
        $response->assertSessionHas('error', 'Se ha llenado mientras confirmabas.');
    }

    public function test_unknown_token_redirects_with_the_invalid_message(): void
    {
        $message = 'Este enlace ya no es válido. Si quedan plazas, puedes volver a apuntarte.';

        $this->get(route('gametables.confirm-guest', str_repeat('a', 64)))
            ->assertRedirect(route('gametables.index'))
            ->assertSessionHas('error', $message);

        $this->post(route('gametables.confirm-guest.store', str_repeat('a', 64)))
            ->assertRedirect(route('gametables.index'))
            ->assertSessionHas('error', $message);
    }

    public function test_an_expired_registration_link_is_invalid_on_the_page(): void
    {
        $row = $this->submitGuest();
        $token = (string) $row->confirmation_token;

        $this->travelTo(CarbonImmutable::parse('2026-11-02 12:01:00'));

        $this->post(route('gametables.confirm-guest.store', $token))
            ->assertRedirect(route('gametables.index'))
            ->assertSessionHas('error', 'Este enlace ya no es válido. Si quedan plazas, puedes volver a apuntarte.');
        $this->assertSame('expired', $row->refresh()->status->value);
        $this->get(route('gametables.confirm-guest', $token))
            ->assertRedirect(route('gametables.index'));
    }

    public function test_the_link_is_rate_limited(): void
    {
        for ($i = 0; $i < 20; $i++) {
            $this->get(route('gametables.confirm-guest', str_repeat('b', 64)))->assertRedirect();
        }

        $response = $this->get(route('gametables.confirm-guest', str_repeat('b', 64)));

        $response->assertStatus(429);
        $this->assertStringContainsString('Demasiados intentos con este enlace. Espera un momento y vuelve a intentarlo.', (string) $response->getContent());
    }

    public function test_the_button_shares_the_limit_of_the_page(): void
    {
        for ($i = 0; $i < 20; $i++) {
            $this->get(route('gametables.confirm-guest', str_repeat('b', 64)));
        }

        $this->postJson(route('gametables.confirm-guest.store', str_repeat('b', 64)))
            ->assertStatus(429)
            ->assertJson(['message' => 'Demasiados intentos con este enlace. Espera un momento y vuelve a intentarlo.']);
    }

    /**
     * The whole public flow without fakes but the mail: the form sends the link, the link confirms.
     */
    public function test_a_web_guest_submission_sends_the_link_that_confirms_it(): void
    {
        $this->post(route('gametables.register-guest', 'la-cripta'), [
            'first_name' => 'Ana',
            'email' => 'ana@example.test',
            'gdpr_consent' => true,
        ])->assertSessionHasNoErrors();

        $row = ParticipantModel::query()->where('email', 'ana@example.test')->firstOrFail();
        $url = null;
        Notification::assertSentOnDemand(
            GuestEmailConfirmationRequest::class,
            function (GuestEmailConfirmationRequest $notification, array $channels, AnonymousNotifiable $notifiable) use (&$url): bool {
                $url = $notification->toMail($notifiable)->actionUrl;

                return $notifiable->routes['mail'] === 'ana@example.test';
            },
        );
        $this->assertSame(route('gametables.confirm-guest', $row->confirmation_token), $url);
        Notification::assertSentTimes(GuestRegistrationConfirmation::class, 0);

        $this->get((string) $url)->assertOk();
        $this->post(route('gametables.confirm-guest.store', $row->confirmation_token))
            ->assertSessionHas('success', 'Tu inscripción está confirmada. Te hemos enviado un email con los detalles.');
        $this->assertNotNull($row->refresh()->email_confirmed_at);
        Notification::assertSentTimes(GuestRegistrationConfirmation::class, 1);
    }

    private function submitGuest(): ParticipantModel
    {
        $this->post(route('gametables.register-guest', 'la-cripta'), [
            'first_name' => 'Ana',
            'email' => 'ana@example.test',
            'gdpr_consent' => true,
        ])->assertSessionHasNoErrors();

        return ParticipantModel::query()->where('email', 'ana@example.test')->firstOrFail();
    }
}
