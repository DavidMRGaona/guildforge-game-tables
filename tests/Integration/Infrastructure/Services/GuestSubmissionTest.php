<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Integration\Infrastructure\Services;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Support\Testing\Fakes\EventFake;
use Modules\GameTables\Application\DTOs\ParticipantResponseDTO;
use Modules\GameTables\Application\DTOs\RegisterParticipantDTO;
use Modules\GameTables\Application\Services\RegistrationServiceInterface;
use Modules\GameTables\Domain\Enums\GuestConfirmationMode;
use Modules\GameTables\Domain\Enums\ParticipantRole;
use Modules\GameTables\Domain\Events\GuestConfirmationRequested;
use Modules\GameTables\Domain\Events\GuestRegistered;
use Modules\GameTables\Domain\Events\GuestRegistrationExpired;
use Modules\GameTables\Domain\Events\ParticipantConfirmed;
use Modules\GameTables\Domain\Events\ParticipantPromotedFromWaitingList;
use Modules\GameTables\Domain\Exceptions\AccountEmailRequiresLoginException;
use Modules\GameTables\Domain\Exceptions\AlreadyRegisteredException;
use Modules\GameTables\Domain\Exceptions\RegistrationNotAllowedException;
use Modules\GameTables\Domain\Repositories\GameTableRepositoryInterface;
use Modules\GameTables\Domain\ValueObjects\GameTableId;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameSystemModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameTableModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\ParticipantModel;
use Modules\GameTables\Infrastructure\Services\SeatCounter;
use Tests\Support\Modules\ModuleTestCase;

/**
 * A guest submitting the public form is left unconfirmed until they follow the emailed link: they
 * hold a seat until the deadline (or, past the cap, wait without one) and nobody else hears of the
 * registration yet.
 */
final class GuestSubmissionTest extends ModuleTestCase
{
    protected ?string $moduleName = 'game-tables';

    protected bool $autoEnableModule = true;

    private GameTableModel $table;

    protected function setUp(): void
    {
        parent::setUp();

        Event::fake([GuestConfirmationRequested::class, GuestRegistered::class, GuestRegistrationExpired::class, ParticipantConfirmed::class]);
        config()->set('modules.settings.game-tables', []);
        $this->travelTo(CarbonImmutable::parse('2026-11-02 10:00:00', 'Europe/Madrid'));

        $system = GameSystemModel::create(['id' => (string) Str::uuid(), 'name' => 'Sistema', 'slug' => 'sistema', 'is_active' => true]);
        $this->table = GameTableModel::create([
            'id' => (string) Str::uuid(),
            'game_system_id' => $system->id,
            'created_by' => UserModel::factory()->create()->id,
            'title' => 'Mesa',
            'slug' => 'mesa',
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
        ]);
    }

    public function test_a_web_guest_holds_a_seat_until_the_deadline(): void
    {
        $result = $this->submit('invitada@example.test', fingerprint: 'huella');

        $row = ParticipantModel::query()->findOrFail($result->id);
        $this->assertSame('confirmed', $row->status->value);
        $this->assertNull($row->email_confirmed_at);
        $this->assertSame('hold', $row->confirmation_mode?->value);
        $this->assertSame('2026-11-02 12:00:00', $row->confirmation_expires_at?->setTimezone('Europe/Madrid')->format('Y-m-d H:i:s'));
        $this->assertSame('2026-11-02 10:00:00', $row->confirmation_sent_at?->setTimezone('Europe/Madrid')->format('Y-m-d H:i:s'));
        $this->assertSame(64, strlen((string) $row->confirmation_token));
        $this->assertNotSame($row->cancellation_token, $row->confirmation_token);
        $this->assertSame('huella', $row->fingerprint);

        $this->assertTrue($result->awaitingEmailConfirmation);
        $this->assertSame(GuestConfirmationMode::Hold, $result->confirmationMode);
        $this->assertFalse($result->confirmationResent);
        $this->assertTrue($result->toArray()['awaiting_email_confirmation']);
        $this->assertSame(CarbonImmutable::parse('2026-11-02 12:00:00', 'Europe/Madrid')->format('c'), $result->toArray()['confirmation_expires_at']);

        Event::assertDispatchedTimes(GuestConfirmationRequested::class, 1);
        Event::assertDispatched(
            GuestConfirmationRequested::class,
            fn (GuestConfirmationRequested $event): bool => $event->participantId === $row->id
                && $event->gameTableId === $this->table->id
                && $event->email === 'invitada@example.test'
                && $event->firstName === 'Invitada'
                && $event->confirmationToken === $row->confirmation_token
                && $event->holdsPlace === true
                && $event->deadline === '02/11/2026 12:00',
        );
        Event::assertNotDispatched(GuestRegistered::class);
        Event::assertNotDispatched(ParticipantConfirmed::class);
    }

    public function test_the_deadline_is_the_registration_closing_when_it_comes_first(): void
    {
        $this->table->update(['registration_closes_at' => now()->addMinutes(30)]);

        $result = $this->submit('invitada@example.test');

        $this->assertSame('2026-11-02 10:30:00', ParticipantModel::query()->findOrFail($result->id)->confirmation_expires_at?->setTimezone('Europe/Madrid')->format('Y-m-d H:i:s'));
        Event::assertDispatched(GuestConfirmationRequested::class, fn (GuestConfirmationRequested $event): bool => $event->deadline === '02/11/2026 10:30');
    }

    // Registrations closing this very minute would leave a deadline already reached
    public function test_a_table_closing_now_creates_no_row_with_a_past_deadline(): void
    {
        $this->table->update(['registration_closes_at' => now()]);

        try {
            $this->submit('invitada@example.test');
            $this->fail('A registration was created with a deadline already reached');
        } catch (RegistrationNotAllowedException $e) {
            $this->assertSame('registration_closed', $e->reason);
            $this->assertSame('Las inscripciones están cerradas', $e->getMessage());
        }

        $this->assertSame(0, ParticipantModel::query()->count());
        Event::assertNotDispatched(GuestConfirmationRequested::class);
    }

    public function test_the_third_unconfirmed_guest_waits_without_a_seat(): void
    {
        $this->submit('primera@example.test');
        $this->submit('segunda@example.test');

        $third = $this->submit('tercera@example.test');

        $row = ParticipantModel::query()->findOrFail($third->id);
        $this->assertSame('pending', $row->status->value);
        $this->assertSame('wait', $row->confirmation_mode?->value);
        $this->assertNull($row->confirmation_expires_at);
        $this->assertNull($row->waiting_list_position);
        $this->assertSame(GuestConfirmationMode::Wait, $third->confirmationMode);
        $this->assertTrue($third->awaitingEmailConfirmation);
        $this->assertNull($third->toArray()['confirmation_expires_at']);
        $this->assertSame(2, app(SeatCounter::class)->takenPlayerSeats(
            app(GameTableRepositoryInterface::class)->findOrFail(new GameTableId($this->table->id)),
        ));
        Event::assertDispatched(
            GuestConfirmationRequested::class,
            fn (GuestConfirmationRequested $event): bool => $event->participantId === $row->id
                && $event->holdsPlace === false
                && $event->deadline === null,
        );
    }

    public function test_resubmitting_resends_without_moving_the_deadline(): void
    {
        $first = $this->submit('invitada@example.test', name: 'Invitada', notes: 'Primera vez');
        $row = ParticipantModel::query()->findOrFail($first->id);
        $deadline = $row->confirmation_expires_at?->setTimezone('Europe/Madrid')->format('Y-m-d H:i:s');

        $this->travelTo(CarbonImmutable::parse('2026-11-02 10:10:00', 'Europe/Madrid'));
        $second = $this->submit('Invitada@Example.TEST', name: 'Otro nombre', notes: 'Otra nota', role: ParticipantRole::Spectator);

        $this->assertSame($first->id, $second->id);
        $this->assertTrue($second->confirmationResent);
        $row->refresh();
        $this->assertSame($deadline, $row->confirmation_expires_at?->setTimezone('Europe/Madrid')->format('Y-m-d H:i:s'));
        $this->assertSame('2026-11-02 10:10:00', $row->confirmation_sent_at?->setTimezone('Europe/Madrid')->format('Y-m-d H:i:s'));
        $this->assertSame('Invitada', $row->first_name);
        $this->assertSame('Primera vez', $row->notes);
        $this->assertSame('player', $row->role->value);
        $this->assertSame($first->id, ParticipantModel::query()->sole()->id);
        Event::assertDispatchedTimes(GuestConfirmationRequested::class, 2);

        // Within five minutes of the last email: the same answer, no email
        $this->travelTo(CarbonImmutable::parse('2026-11-02 10:12:00', 'Europe/Madrid'));
        $third = $this->submit('invitada@example.test');

        $this->assertTrue($third->confirmationResent);
        $this->assertSame('2026-11-02 10:10:00', $row->refresh()->confirmation_sent_at?->setTimezone('Europe/Madrid')->format('Y-m-d H:i:s'));
        Event::assertDispatchedTimes(GuestConfirmationRequested::class, 2);
        Event::assertNotDispatched(GuestRegistered::class);
    }

    // The guest holds one of the seats: a full table is no reason to refuse the resend
    public function test_resubmitting_on_a_table_filled_meanwhile_still_resends(): void
    {
        $this->table->update(['max_players' => 1]);
        $first = $this->submit('invitada@example.test');

        $this->travelTo(CarbonImmutable::parse('2026-11-02 10:10:00', 'Europe/Madrid'));
        $second = $this->submit('invitada@example.test');

        $this->assertSame($first->id, $second->id);
        $this->assertTrue($second->confirmationResent);
        Event::assertDispatchedTimes(GuestConfirmationRequested::class, 2);
    }

    // The usual rules come first: once registrations close, nothing is resent
    public function test_resubmitting_after_the_closing_is_refused(): void
    {
        $this->submit('invitada@example.test');
        $this->table->update(['registration_closes_at' => now()->addMinutes(5)]);

        $this->travelTo(CarbonImmutable::parse('2026-11-02 10:10:00', 'Europe/Madrid'));

        try {
            $this->submit('invitada@example.test');
            $this->fail('A closed table resent the confirmation');
        } catch (RegistrationNotAllowedException $e) {
            $this->assertSame('registration_closed', $e->reason);
        }

        Event::assertDispatchedTimes(GuestConfirmationRequested::class, 1);
    }

    public function test_a_confirmed_guest_is_still_a_duplicate(): void
    {
        $this->submit('invitada@example.test');
        ParticipantModel::query()->update(['email_confirmed_at' => now()]);

        try {
            $this->submit('Invitada@Example.TEST');
            $this->fail('A confirmed guest was registered twice');
        } catch (AlreadyRegisteredException $e) {
            $this->assertSame('guest_already_registered', $e->reason);
        }

        $this->assertSame(1, ParticipantModel::query()->count());
        Event::assertDispatchedTimes(GuestConfirmationRequested::class, 1);
    }

    public function test_a_cancelled_guest_is_reactivated_unconfirmed(): void
    {
        $first = $this->submit('invitada@example.test', fingerprint: 'vieja');
        ParticipantModel::query()->update(['status' => 'cancelled', 'email_confirmed_at' => now(), 'confirmation_token' => null]);

        $again = $this->submit('invitada@example.test', name: 'Nueva', fingerprint: 'nueva');

        $row = ParticipantModel::query()->findOrFail($first->id);
        $this->assertSame($first->id, $again->id);
        $this->assertSame('confirmed', $row->status->value);
        $this->assertNull($row->email_confirmed_at);
        $this->assertSame('Nueva', $row->first_name);
        $this->assertSame('nueva', $row->fingerprint);
        $this->assertSame(64, strlen((string) $row->confirmation_token));
        $this->assertFalse($again->confirmationResent);
        Event::assertDispatchedTimes(GuestConfirmationRequested::class, 2);
    }

    public function test_an_account_email_asks_to_log_in(): void
    {
        UserModel::factory()->create(['email' => 'cuenta@example.test']);

        try {
            $this->submit('Cuenta@Example.TEST');
            $this->fail('An account email registered as a guest');
        } catch (AccountEmailRequiresLoginException $e) {
            $this->assertSame('email_has_account', $e->reason);
            $this->assertSame('Este email pertenece a una cuenta. Inicia sesión para inscribirte.', $e->getMessage());
        }

        $this->assertSame(0, ParticipantModel::query()->count());
        Event::assertNotDispatched(GuestConfirmationRequested::class);
    }

    // The account check comes before the resend
    public function test_an_unconfirmed_guest_whose_email_now_has_an_account_is_asked_to_log_in(): void
    {
        $this->submit('cuenta@example.test');
        UserModel::factory()->create(['email' => 'cuenta@example.test']);
        $this->travelTo(CarbonImmutable::parse('2026-11-02 10:10:00', 'Europe/Madrid'));

        $this->expectException(AccountEmailRequiresLoginException::class);

        $this->submit('cuenta@example.test');
    }

    public function test_allow_guest_mode_admits_the_account_email(): void
    {
        config()->set('modules.settings.game-tables.guest_accounts.mode', 'allow_guest');
        UserModel::factory()->create(['email' => 'cuenta@example.test']);

        $result = $this->submit('cuenta@example.test');

        $this->assertTrue($result->isGuest);
        $this->assertTrue($result->awaitingEmailConfirmation);
        Event::assertDispatchedTimes(GuestConfirmationRequested::class, 1);
    }

    public function test_allow_guest_mode_refuses_when_the_account_is_registered(): void
    {
        config()->set('modules.settings.game-tables.guest_accounts.mode', 'allow_guest');
        $account = UserModel::factory()->create(['email' => 'cuenta@example.test']);
        ParticipantModel::create([
            'id' => (string) Str::uuid(),
            'game_table_id' => $this->table->id,
            'user_id' => $account->id,
            'role' => 'player',
            'status' => 'confirmed',
        ]);

        try {
            $this->submit('cuenta@example.test');
            $this->fail('The account was registered twice');
        } catch (AlreadyRegisteredException $e) {
            $this->assertSame('guest_already_registered', $e->reason);
        }

        $this->assertSame(1, ParticipantModel::query()->count());
    }

    // An unverified account may hold someone else's email: it does not send the guest to log in
    public function test_an_unverified_account_email_registers_as_a_plain_guest(): void
    {
        UserModel::factory()->unverified()->create(['email' => 'cuenta@example.test']);

        $result = $this->submit('cuenta@example.test');

        $this->assertTrue($result->isGuest);
        $this->assertTrue($result->awaitingEmailConfirmation);
        Event::assertDispatchedTimes(GuestConfirmationRequested::class, 1);
    }

    public function test_allow_guest_mode_ignores_a_registered_unverified_account(): void
    {
        config()->set('modules.settings.game-tables.guest_accounts.mode', 'allow_guest');
        $account = UserModel::factory()->unverified()->create(['email' => 'cuenta@example.test']);
        ParticipantModel::create([
            'id' => (string) Str::uuid(),
            'game_table_id' => $this->table->id,
            'user_id' => $account->id,
            'role' => 'player',
            'status' => 'confirmed',
        ]);

        $result = $this->submit('cuenta@example.test');

        $this->assertTrue($result->isGuest);
        $this->assertSame(2, ParticipantModel::query()->count());
    }

    // Seats are the last rule: on a full table the duplicate and the account answers still come first
    public function test_on_a_full_table_a_confirmed_guest_still_gets_the_duplicate(): void
    {
        $this->table->update(['max_players' => 1]);
        $this->submit('invitada@example.test');
        ParticipantModel::query()->update(['email_confirmed_at' => now()]);

        try {
            $this->submit('invitada@example.test');
            $this->fail('A confirmed guest on a full table was not told about the duplicate');
        } catch (AlreadyRegisteredException $e) {
            $this->assertSame('guest_already_registered', $e->reason);
        }
    }

    public function test_on_a_full_table_an_account_email_is_asked_to_log_in(): void
    {
        $this->table->update(['max_players' => 1]);
        $this->submit('primera@example.test');
        UserModel::factory()->create(['email' => 'cuenta@example.test']);

        $this->expectException(AccountEmailRequiresLoginException::class);

        $this->submit('cuenta@example.test');
    }

    public function test_on_a_full_table_allow_guest_mode_refuses_a_registered_account_as_a_duplicate(): void
    {
        config()->set('modules.settings.game-tables.guest_accounts.mode', 'allow_guest');
        $this->table->update(['max_players' => 1]);
        $account = UserModel::factory()->create(['email' => 'cuenta@example.test']);
        ParticipantModel::create([
            'id' => (string) Str::uuid(),
            'game_table_id' => $this->table->id,
            'user_id' => $account->id,
            'role' => 'player',
            'status' => 'confirmed',
        ]);

        try {
            $this->submit('cuenta@example.test');
            $this->fail('A registered account on a full table was not told about the duplicate');
        } catch (AlreadyRegisteredException $e) {
            $this->assertSame('guest_already_registered', $e->reason);
        }
    }

    public function test_on_a_full_table_a_new_guest_is_told_it_is_full(): void
    {
        $this->table->update(['max_players' => 1]);
        $this->submit('primera@example.test');

        try {
            $this->submit('segunda@example.test');
            $this->fail('A new guest was placed on a full table');
        } catch (RegistrationNotAllowedException $e) {
            $this->assertSame('table_full', $e->reason);
        }

        $this->assertSame(1, ParticipantModel::query()->count());
    }

    // Past the deadline, before the expiry command runs: never an email with a deadline already gone
    public function test_resubmitting_past_the_deadline_expires_the_row_and_registers_again(): void
    {
        $first = $this->submit('invitada@example.test', name: 'Invitada');
        $old = ParticipantModel::query()->findOrFail($first->id);

        $this->travelTo(CarbonImmutable::parse('2026-11-02 12:01:00', 'Europe/Madrid'));
        $again = $this->submit('invitada@example.test', name: 'Nueva');

        $row = ParticipantModel::query()->findOrFail($first->id);
        $this->assertSame($first->id, $again->id);
        $this->assertFalse($again->confirmationResent);
        $this->assertSame('confirmed', $row->status->value);
        $this->assertSame('Nueva', $row->first_name);
        $this->assertSame('2026-11-02 14:01:00', $row->confirmation_expires_at?->setTimezone('Europe/Madrid')->format('Y-m-d H:i:s'));
        $this->assertNotSame($old->confirmation_token, $row->confirmation_token);
        $this->assertNotSame($old->cancellation_token, $row->cancellation_token);
        Event::assertDispatchedTimes(GuestConfirmationRequested::class, 2);
        Event::assertDispatched(GuestConfirmationRequested::class, fn (GuestConfirmationRequested $event): bool => $event->deadline === '02/11/2026 14:01'
            && $event->confirmationToken === $row->confirmation_token);
        // Only the first email gave the deadline already gone
        $events = Event::getFacadeRoot();
        $this->assertInstanceOf(EventFake::class, $events);
        $this->assertCount(1, $events->dispatched(GuestConfirmationRequested::class, fn (GuestConfirmationRequested $event): bool => $event->deadline === '02/11/2026 12:00'));
        // Registering again, not told the old registration lapsed
        Event::assertNotDispatched(GuestRegistrationExpired::class);
    }

    public function test_the_seat_freed_by_an_overdue_row_goes_to_the_waiting_list_first(): void
    {
        Notification::fake();
        Event::fake([GuestConfirmationRequested::class, GuestRegistered::class, ParticipantConfirmed::class, ParticipantPromotedFromWaitingList::class]);
        config()->set('modules.settings.game-tables.registration.waiting_list_enabled', true);
        $this->table->update(['max_players' => 1]);
        $guest = $this->submit('invitada@example.test');
        $waiting = app(RegistrationServiceInterface::class)->registerByAdmin(new RegisterParticipantDTO(
            gameTableId: $this->table->id,
            userId: UserModel::factory()->create()->id,
            role: ParticipantRole::Player,
        ));
        $this->assertSame('waiting_list', ParticipantModel::query()->findOrFail($waiting->id)->status->value);

        $this->travelTo(CarbonImmutable::parse('2026-11-02 12:01:00', 'Europe/Madrid'));
        $again = $this->submit('invitada@example.test');

        $this->assertSame('confirmed', ParticipantModel::query()->findOrFail($waiting->id)->status->value);
        Event::assertDispatched(ParticipantPromotedFromWaitingList::class, fn (ParticipantPromotedFromWaitingList $event): bool => $event->participantId === $waiting->id);
        $row = ParticipantModel::query()->findOrFail($guest->id);
        $this->assertSame($guest->id, $again->id);
        $this->assertSame('waiting_list', $row->status->value);
        $this->assertSame('2026-11-02 14:01:00', $row->confirmation_expires_at?->setTimezone('Europe/Madrid')->format('Y-m-d H:i:s'));
    }

    public function test_an_expired_guest_is_reactivated_unconfirmed(): void
    {
        $first = $this->submit('invitada@example.test');
        ParticipantModel::query()->update(['status' => 'expired', 'confirmation_token' => null, 'confirmation_expires_at' => null, 'cancelled_at' => now()]);

        $this->travelTo(CarbonImmutable::parse('2026-11-02 11:00:00', 'Europe/Madrid'));
        $again = $this->submit('invitada@example.test', name: 'Nueva');

        $row = ParticipantModel::query()->findOrFail($first->id);
        $this->assertSame($first->id, $again->id);
        $this->assertSame('confirmed', $row->status->value);
        $this->assertNull($row->email_confirmed_at);
        $this->assertNull($row->cancelled_at);
        $this->assertSame('Nueva', $row->first_name);
        $this->assertSame('2026-11-02 13:00:00', $row->confirmation_expires_at?->setTimezone('Europe/Madrid')->format('Y-m-d H:i:s'));
        $this->assertSame(64, strlen((string) $row->confirmation_token));
        Event::assertDispatchedTimes(GuestConfirmationRequested::class, 2);
    }

    public function test_an_admin_guest_needs_no_confirmation(): void
    {
        $result = app(RegistrationServiceInterface::class)->registerGuestByAdmin(new RegisterParticipantDTO(
            gameTableId: $this->table->id,
            userId: null,
            role: ParticipantRole::Player,
            firstName: 'Invitada',
            email: 'invitada@example.test',
            fingerprint: 'huella',
        ));

        $row = ParticipantModel::query()->findOrFail($result->id);
        $this->assertSame('2026-11-02 10:00:00', $row->email_confirmed_at?->setTimezone('Europe/Madrid')->format('Y-m-d H:i:s'));
        $this->assertNull($row->confirmation_expires_at);
        $this->assertNull($row->confirmation_mode);
        $this->assertNull($row->fingerprint);
        $this->assertFalse($result->awaitingEmailConfirmation);
        Event::assertDispatched(GuestRegistered::class, fn (GuestRegistered $event): bool => $event->participantId === $row->id);
        Event::assertNotDispatched(GuestConfirmationRequested::class);
    }

    private function submit(
        string $email,
        string $name = 'Invitada',
        ?string $notes = null,
        ParticipantRole $role = ParticipantRole::Player,
        ?string $fingerprint = null,
    ): ParticipantResponseDTO {
        return app(RegistrationServiceInterface::class)->registerGuest(new RegisterParticipantDTO(
            gameTableId: $this->table->id,
            userId: null,
            role: $role,
            notes: $notes,
            firstName: $name,
            email: $email,
            fingerprint: $fingerprint,
        ));
    }
}
