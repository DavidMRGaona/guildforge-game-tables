<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Feature\Http\Controllers;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Carbon\CarbonImmutable;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Modules\GameTables\Application\DTOs\ParticipantResponseDTO;
use Modules\GameTables\Application\DTOs\RegisterParticipantDTO;
use Modules\GameTables\Application\Services\RegistrationServiceInterface;
use Modules\GameTables\Domain\Enums\ParticipantRole;
use Modules\GameTables\Domain\Events\GuestRegistrationExpired;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameSystemModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameTableModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\ParticipantModel;
use Modules\GameTables\Notifications\GuestEmailConfirmationRequest;
use Modules\GameTables\Notifications\GuestRegistrationConfirmation;
use Modules\GameTables\Notifications\ParticipantRegisteredNotification;
use Modules\GameTables\Notifications\WaitingListPromotionNotification;
use RuntimeException;
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
            // The email is what is pending, whatever the seat it holds meanwhile
            ->where('participant.status_label', 'Pendiente de confirmar')
            ->where('gameTable.title', 'La cripta')
            ->where('gameTable.slug', 'la-cripta')
            ->where('token', $row->confirmation_token)
            ->where('alreadyConfirmed', false)
            ->where('result', null)
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
                ->where('participant.status_label', 'Confirmado')
                ->where('alreadyConfirmed', true)
                ->where('result', null));
    }

    public function test_store_confirms_and_shows_the_seat_on_the_confirmation_page(): void
    {
        $row = $this->submitGuest();

        $response = $this->post(route('gametables.confirm-guest.store', $row->confirmation_token));

        $response->assertOk();
        $this->assertResult($response, 'success', 'Tu inscripción está confirmada. Te hemos enviado un email con los detalles.', 'Confirmado');
        $response->assertInertia(fn ($page) => $page
            ->where('participant.first_name', 'Ana')
            ->where('gameTable.title', 'La cripta')
            ->where('gameTable.slug', 'la-cripta')
            ->where('alreadyConfirmed', true)
            ->missing('participant.email'));
        $this->assertNotNull($row->refresh()->email_confirmed_at);
        Notification::assertSentOnDemand(
            GuestRegistrationConfirmation::class,
            fn (GuestRegistrationConfirmation $notification, array $channels, AnonymousNotifiable $notifiable): bool => $notifiable->routes['mail'] === 'ana@example.test',
        );
    }

    public function test_on_a_table_with_manual_approval_the_page_says_it_waits_for_the_organisation(): void
    {
        $this->table->update(['auto_confirm' => false, 'notification_email' => 'mesa@example.test']);
        $row = $this->submitGuest();
        $this->assertSame('pending', $row->status->value);
        Notification::assertSentTimes(ParticipantRegisteredNotification::class, 0);

        $response = $this->post(route('gametables.confirm-guest.store', $row->confirmation_token));

        $this->assertResult($response, 'info', 'Has confirmado tu email. Tu inscripción está pendiente de aprobación por la organización: te avisaremos por correo.', 'Pendiente');
        $row->refresh();
        $this->assertSame('pending', $row->status->value);
        $this->assertNotNull($row->email_confirmed_at);
        // The organisation is told once, now that the email is confirmed
        Notification::assertSentOnDemandTimes(ParticipantRegisteredNotification::class, 1);
        Notification::assertSentOnDemand(
            ParticipantRegisteredNotification::class,
            fn (ParticipantRegisteredNotification $notification, array $channels, AnonymousNotifiable $notifiable): bool => $notifiable->routes['mail'] === 'mesa@example.test',
        );
    }

    public function test_a_wait_row_on_a_table_with_manual_approval_is_placed_pending(): void
    {
        config()->set('modules.settings.game-tables.guest_confirmation.mode', 'wait');
        $this->table->update(['auto_confirm' => false]);
        $row = $this->submitGuest();

        $response = $this->post(route('gametables.confirm-guest.store', $row->confirmation_token));

        $this->assertResult($response, 'info', 'Has confirmado tu email. Tu inscripción está pendiente de aprobación por la organización: te avisaremos por correo.', 'Pendiente');
        $this->assertSame('pending', $row->refresh()->status->value);
    }

    public function test_on_the_waiting_list_the_page_gives_the_position(): void
    {
        config()->set('modules.settings.game-tables.registration.waiting_list_enabled', true);
        $this->table->update(['max_players' => 1]);
        $this->registerUser();
        $this->registerUser();
        $row = $this->submitGuest();
        $this->assertSame('waiting_list', $row->status->value);

        $response = $this->post(route('gametables.confirm-guest.store', $row->confirmation_token));

        $this->assertResult($response, 'info', 'Has confirmado tu email. Estás en la lista de espera (puesto 2): si se libera una plaza, te avisaremos por correo.', 'Lista de espera');
        $this->assertSame('waiting_list', $row->refresh()->status->value);
    }

    public function test_a_second_post_says_it_was_already_confirmed(): void
    {
        $row = $this->submitGuest();
        $this->post(route('gametables.confirm-guest.store', $row->confirmation_token));

        $response = $this->post(route('gametables.confirm-guest.store', $row->confirmation_token));

        $this->assertResult($response, 'success', 'Tu inscripción ya estaba confirmada.', 'Confirmado');
    }

    public function test_a_closed_table_answers_with_the_closing_message(): void
    {
        $row = $this->submitGuest();
        $this->table->update(['registration_closes_at' => now()]);

        $response = $this->post(route('gametables.confirm-guest.store', $row->confirmation_token));

        $this->assertResult($response, 'error', 'Las inscripciones ya se han cerrado.', 'Caducada');
        $response->assertInertia(fn ($page) => $page->where('gameTable.slug', 'la-cripta'));
        $this->assertSame('expired', $row->refresh()->status->value);
    }

    public function test_a_table_filled_meanwhile_answers_with_the_filled_up_message(): void
    {
        config()->set('modules.settings.game-tables.guest_confirmation.mode', 'wait');
        $this->table->update(['max_players' => 1]);
        $row = $this->submitGuest();
        $this->registerUser();

        $response = $this->post(route('gametables.confirm-guest.store', $row->confirmation_token));

        $this->assertResult($response, 'error', 'Se ha llenado mientras confirmabas.', 'Caducada');
        $this->assertSame('expired', $row->refresh()->status->value);
    }

    public function test_unknown_token_answers_with_the_invalid_message(): void
    {
        $message = 'Este enlace ya no es válido. Si quedan plazas, puedes volver a apuntarte.';

        // The page itself says so (the list of tables shows no flash message)
        $this->assertMessagePage($this->get(route('gametables.confirm-guest', str_repeat('a', 64))), 200, $message);

        // The button of a page left open: the answer stays on that page
        $this->post(route('gametables.confirm-guest.store', str_repeat('a', 64)))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('GameTables/ConfirmRegistration', shouldExist: false)
                ->where('result.tone', 'error')
                ->where('result.message', $message)
                ->where('participant', null)
                ->where('gameTable', null));
    }

    public function test_an_expired_registration_link_is_invalid_on_the_page(): void
    {
        $row = $this->submitGuest();
        $token = (string) $row->confirmation_token;

        $this->travelTo(CarbonImmutable::parse('2026-11-02 12:01:00'));

        $response = $this->post(route('gametables.confirm-guest.store', $token));

        $this->assertResult($response, 'error', 'Este enlace ya no es válido. Si quedan plazas, puedes volver a apuntarte.', 'Caducada');
        $this->assertSame('expired', $row->refresh()->status->value);

        // A refresh: the link no longer leads to the registration, and the page says so
        $this->assertMessagePage(
            $this->get(route('gametables.confirm-guest', $token)),
            200,
            'Este enlace ya no es válido. Si quedan plazas, puedes volver a apuntarte.',
        );
    }

    public function test_the_page_of_a_link_whose_table_is_no_longer_published_says_it_is_invalid(): void
    {
        $row = $this->submitGuest();
        $this->table->update(['is_published' => false]);

        $this->assertMessagePage(
            $this->get(route('gametables.confirm-guest', $row->confirmation_token)),
            200,
            'Este enlace ya no es válido. Si quedan plazas, puedes volver a apuntarte.',
        );
    }

    public function test_the_button_of_an_unpublished_table_confirms_nothing(): void
    {
        $row = $this->submitGuest();
        $this->table->update(['is_published' => false]);

        $this->post(route('gametables.confirm-guest.store', $row->confirmation_token))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('result.tone', 'error')
                ->where('result.message', 'Este enlace ya no es válido. Si quedan plazas, puedes volver a apuntarte.')
                ->where('participant', null)
                ->where('gameTable', null));

        $row->refresh();
        $this->assertNull($row->email_confirmed_at);
        $this->assertSame('confirmed', $row->status->value);
        Notification::assertSentTimes(GuestRegistrationConfirmation::class, 0);
    }

    // A failing expiry email never costs the promoted player their notice, nor the guest the page
    public function test_a_failing_expiry_announcement_keeps_the_promotion_notice_and_the_page(): void
    {
        Exceptions::fake();
        config()->set('modules.settings.game-tables.registration.waiting_list_enabled', true);
        $this->table->update(['max_players' => 1]);
        $row = $this->submitGuest();
        $waiting = $this->registerUser();
        $this->assertSame('waiting_list', $waiting->status->value);
        Event::listen(GuestRegistrationExpired::class, static function (): void {
            throw new RuntimeException('Mail transport down');
        });

        $this->travelTo(CarbonImmutable::parse('2026-11-02 12:01:00'));
        $response = $this->post(route('gametables.confirm-guest.store', $row->confirmation_token));

        $this->assertResult($response, 'error', 'Este enlace ya no es válido. Si quedan plazas, puedes volver a apuntarte.', 'Caducada');
        $this->assertSame('confirmed', ParticipantModel::query()->findOrFail($waiting->id)->status->value);
        $email = UserModel::query()->findOrFail($waiting->userId)->email;
        Notification::assertSentOnDemand(
            WaitingListPromotionNotification::class,
            fn (WaitingListPromotionNotification $notification, array $channels, AnonymousNotifiable $notifiable): bool => $notifiable->routes['mail'] === $email,
        );
        Exceptions::assertReported(RuntimeException::class);
    }

    public function test_the_link_is_rate_limited(): void
    {
        for ($i = 0; $i < 20; $i++) {
            $this->get(route('gametables.confirm-guest', str_repeat('b', 64)))->assertOk();
        }

        $this->assertMessagePage(
            $this->get(route('gametables.confirm-guest', str_repeat('b', 64))),
            429,
            'Demasiados intentos con este enlace. Espera un momento y vuelve a intentarlo.',
        );
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

    // Inertia shows a plain-text 429 in its raw error modal: the button gets the confirmation page
    // with the message instead, still with the 429 status
    public function test_the_button_over_the_limit_answers_with_the_message_on_the_page(): void
    {
        for ($i = 0; $i < 20; $i++) {
            $this->get(route('gametables.confirm-guest', str_repeat('b', 64)));
        }

        $response = $this->from(route('gametables.confirm-guest', str_repeat('b', 64)))
            ->withHeaders(['X-Inertia' => 'true'])
            ->post(route('gametables.confirm-guest.store', str_repeat('b', 64)));

        // An Inertia page (JSON), which Inertia shows even with an error status
        $response->assertStatus(429);
        $response->assertHeader('X-Inertia', 'true');
        $response->assertJsonPath('component', 'GameTables/ConfirmRegistration');
        $response->assertJsonPath('props.result.tone', 'error');
        $response->assertJsonPath('props.result.message', 'Demasiados intentos con este enlace. Espera un momento y vuelve a intentarlo.');
        $response->assertJsonPath('props.participant', null);
        $response->assertJsonPath('props.gameTable', null);
    }

    /**
     * The whole public flow without fakes but the mail: the form sends the link, the link confirms.
     */
    public function test_a_web_guest_submission_sends_the_link_that_confirms_it(): void
    {
        $this->table->update(['notification_email' => 'mesa@example.test']);

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
        // The organisation hears of the registration only once the email is confirmed
        Notification::assertSentTimes(ParticipantRegisteredNotification::class, 0);

        $this->get((string) $url)->assertOk();
        $this->assertResult(
            $this->post(route('gametables.confirm-guest.store', $row->confirmation_token)),
            'success',
            'Tu inscripción está confirmada. Te hemos enviado un email con los detalles.',
            'Confirmado',
        );
        $this->assertNotNull($row->refresh()->email_confirmed_at);
        Notification::assertSentTimes(GuestRegistrationConfirmation::class, 1);
        Notification::assertSentOnDemandTimes(ParticipantRegisteredNotification::class, 1);
    }

    private function assertResult(TestResponse $response, string $tone, string $message, string $statusLabel): void
    {
        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('GameTables/ConfirmRegistration', shouldExist: false)
            ->where('result.tone', $tone)
            ->where('result.message', $message)
            ->where('participant.status_label', $statusLabel)
            ->where('gameTable.title', 'La cripta'));
    }

    /**
     * The confirmation page with only a message: no registration, no table, a link to the list.
     */
    private function assertMessagePage(TestResponse $response, int $status, string $message): void
    {
        $response->assertStatus($status);
        $response->assertInertia(fn ($page) => $page
            ->component('GameTables/ConfirmRegistration', shouldExist: false)
            ->where('result.tone', 'error')
            ->where('result.message', $message)
            ->where('participant', null)
            ->where('gameTable', null));
    }

    private function registerUser(): ParticipantResponseDTO
    {
        return app(RegistrationServiceInterface::class)->registerByAdmin(new RegisterParticipantDTO(
            gameTableId: $this->table->id,
            userId: UserModel::factory()->create()->id,
            role: ParticipantRole::Player,
        ));
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
