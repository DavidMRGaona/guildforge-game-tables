<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Integration\Infrastructure\Services;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Carbon\CarbonImmutable;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Mockery;
use Modules\GameTables\Application\DTOs\ParticipantResponseDTO;
use Modules\GameTables\Application\DTOs\RegisterParticipantDTO;
use Modules\GameTables\Application\Services\RegistrationServiceInterface;
use Modules\GameTables\Domain\Enums\ParticipantRole;
use Modules\GameTables\Domain\Events\GuestRegistrationExpired;
use Modules\GameTables\Domain\Repositories\GameTableRepositoryInterface;
use Modules\GameTables\Domain\Repositories\ParticipantRepositoryInterface;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameSystemModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameTableModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\ParticipantModel;
use Modules\GameTables\Infrastructure\Services\GuestRegistrationExpirer;
use Modules\GameTables\Notifications\GuestRegistrationExpiredNotification;
use Modules\GameTables\Notifications\WaitingListPromotionGmNotification;
use RuntimeException;
use Tests\Support\Modules\ModuleTestCase;

/**
 * Unconfirmed guests lapse with the clock: a held place at its deadline (the waiting list takes
 * the seat and the guest is told), a place never held when registrations close (silently). Only
 * rows still unconfirmed are touched, and the fingerprints of past tables are cleared.
 */
final class GuestRegistrationExpirerTest extends ModuleTestCase
{
    protected ?string $moduleName = 'game-tables';

    protected bool $autoEnableModule = true;

    private GameSystemModel $system;

    private GameTableModel $table;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        config()->set('modules.settings.game-tables', []);
        $this->travelTo(CarbonImmutable::parse('2026-11-02 10:00:00'));

        $this->system = GameSystemModel::create(['id' => (string) Str::uuid(), 'name' => 'Sistema', 'slug' => 'sistema', 'is_active' => true]);
        $this->table = $this->createTable('Mesa', 'mesa', now()->addWeek());
    }

    public function test_an_overdue_hold_expires_and_promotes_the_first_confirmed_waiting_player(): void
    {
        Event::fake([GuestRegistrationExpired::class]);
        config()->set('modules.settings.game-tables.registration.waiting_list_enabled', true);
        $this->table->update(['max_players' => 1, 'notification_email' => 'mesa@example.test']);

        $seated = $this->submit('sentada@example.test');
        $this->travelTo(CarbonImmutable::parse('2026-11-02 11:00:00'));
        // Unconfirmed, first on the list, with a later deadline
        $listed = $this->submit('lista@example.test');
        $user = $this->registerUser();
        $this->assertSame(1, ParticipantModel::query()->findOrFail($listed->id)->waiting_list_position);
        $this->assertSame(2, ParticipantModel::query()->findOrFail($user->id)->waiting_list_position);

        $this->travelTo(CarbonImmutable::parse('2026-11-02 12:00:00'));
        $counts = $this->expirer()->run(CarbonImmutable::now()->toDateTimeImmutable());

        $this->assertSame(['expired' => 1, 'closed' => 0, 'fingerprintsCleared' => 0, 'failed' => 0], $counts);
        $this->assertSame('expired', ParticipantModel::query()->findOrFail($seated->id)->status->value);
        $this->assertSame('confirmed', ParticipantModel::query()->findOrFail($user->id)->status->value);
        $listedRow = ParticipantModel::query()->findOrFail($listed->id);
        $this->assertSame('waiting_list', $listedRow->status->value);
        $this->assertSame(1, $listedRow->waiting_list_position);

        Event::assertDispatchedTimes(GuestRegistrationExpired::class, 1);
        Event::assertDispatched(GuestRegistrationExpired::class, fn (GuestRegistrationExpired $event): bool => $event->participantId === $seated->id
            && $event->gameTableId === $this->table->id
            && $event->email === 'sentada@example.test'
            && $event->firstName === 'Invitada'
            && $event->notifyGuest === true);
        // The director hears of the promoted user only, never of the expired guest
        Notification::assertSentOnDemandTimes(WaitingListPromotionGmNotification::class, 1);
    }

    // The expiry is committed: its failing email is reported, the promotion is still announced
    public function test_a_failing_expiry_email_never_costs_the_promoted_player_the_notice(): void
    {
        Exceptions::fake();
        config()->set('modules.settings.game-tables.registration.waiting_list_enabled', true);
        $this->table->update(['max_players' => 1, 'notification_email' => 'mesa@example.test']);
        $seated = $this->submit('sentada@example.test');
        $user = $this->registerUser();
        Event::listen(GuestRegistrationExpired::class, static function (): void {
            throw new RuntimeException('Mail transport down');
        });

        $this->travelTo(CarbonImmutable::parse('2026-11-02 12:00:00'));
        $counts = $this->expirer()->run(CarbonImmutable::now()->toDateTimeImmutable());

        $this->assertSame(['expired' => 1, 'closed' => 0, 'fingerprintsCleared' => 0, 'failed' => 0], $counts);
        $this->assertSame('expired', ParticipantModel::query()->findOrFail($seated->id)->status->value);
        $this->assertSame('confirmed', ParticipantModel::query()->findOrFail($user->id)->status->value);
        Notification::assertSentOnDemandTimes(WaitingListPromotionGmNotification::class, 1);
        Exceptions::assertReported(RuntimeException::class);
    }

    public function test_the_expired_guest_is_emailed_and_the_organisation_is_not(): void
    {
        $this->table->update(['notification_email' => 'mesa@example.test']);
        $guest = $this->submit('invitada@example.test');

        $this->travelTo(CarbonImmutable::parse('2026-11-02 12:00:00'));
        $this->expirer()->run(CarbonImmutable::now()->toDateTimeImmutable());

        $this->assertSame('expired', ParticipantModel::query()->findOrFail($guest->id)->status->value);
        Notification::assertSentOnDemandTimes(GuestRegistrationExpiredNotification::class, 1);
        Notification::assertSentOnDemand(
            GuestRegistrationExpiredNotification::class,
            fn (GuestRegistrationExpiredNotification $notification, array $channels, object $notifiable): bool => ($notifiable->routes['mail'] ?? null) === 'invitada@example.test',
        );
        $this->assertNotContains('mesa@example.test', $this->mailRecipients());
    }

    public function test_a_hold_one_minute_before_the_deadline_stays(): void
    {
        Event::fake([GuestRegistrationExpired::class]);
        $guest = $this->submit('invitada@example.test');

        $this->travelTo(CarbonImmutable::parse('2026-11-02 11:59:00'));
        $counts = $this->expirer()->run(CarbonImmutable::now()->toDateTimeImmutable());

        $this->assertSame(0, $counts['expired']);
        $row = ParticipantModel::query()->findOrFail($guest->id);
        $this->assertSame('confirmed', $row->status->value);
        $this->assertNull($row->email_confirmed_at);
        $this->assertNotNull($row->confirmation_token);
        Event::assertNotDispatched(GuestRegistrationExpired::class);
    }

    public function test_wait_rows_expire_silently_when_registrations_close(): void
    {
        Event::fake([GuestRegistrationExpired::class]);
        config()->set('modules.settings.game-tables.guest_confirmation.mode', 'wait');
        $this->table->update(['registration_closes_at' => CarbonImmutable::parse('2026-11-03 10:00:00')]);
        $guest = $this->submit('invitada@example.test');

        $this->travelTo(CarbonImmutable::parse('2026-11-03 09:59:00'));
        $this->assertSame(0, $this->expirer()->run(CarbonImmutable::now()->toDateTimeImmutable())['closed']);
        $this->assertSame('pending', ParticipantModel::query()->findOrFail($guest->id)->status->value);

        $this->travelTo(CarbonImmutable::parse('2026-11-03 10:00:00'));
        $counts = $this->expirer()->run(CarbonImmutable::now()->toDateTimeImmutable());

        $this->assertSame(['expired' => 0, 'closed' => 1, 'fingerprintsCleared' => 0, 'failed' => 0], $counts);
        $row = ParticipantModel::query()->findOrFail($guest->id);
        $this->assertSame('expired', $row->status->value);
        $this->assertNull($row->confirmation_token);
        Event::assertDispatched(GuestRegistrationExpired::class, fn (GuestRegistrationExpired $event): bool => $event->participantId === $guest->id
            && $event->notifyGuest === false);
    }

    public function test_a_wait_row_left_unconfirmed_is_not_emailed(): void
    {
        config()->set('modules.settings.game-tables.guest_confirmation.mode', 'wait');
        $this->table->update(['registration_closes_at' => CarbonImmutable::parse('2026-11-03 10:00:00')]);
        $this->submit('invitada@example.test');

        $this->travelTo(CarbonImmutable::parse('2026-11-03 10:00:00'));
        $this->expirer()->run(CarbonImmutable::now()->toDateTimeImmutable());

        Notification::assertSentOnDemandTimes(GuestRegistrationExpiredNotification::class, 0);
    }

    public function test_a_row_confirmed_meanwhile_is_skipped(): void
    {
        Event::fake([GuestRegistrationExpired::class]);
        $guest = $this->submit('invitada@example.test');
        $this->service()->confirmGuestEmail((string) ParticipantModel::query()->findOrFail($guest->id)->confirmation_token);

        $this->travelTo(CarbonImmutable::parse('2026-11-02 12:00:00'));

        $this->assertFalse($this->service()->expireGuestRegistration($guest->id));
        $this->assertSame(0, $this->expirer()->run(CarbonImmutable::now()->toDateTimeImmutable())['expired']);
        $this->assertSame('confirmed', ParticipantModel::query()->findOrFail($guest->id)->status->value);
        Event::assertNotDispatched(GuestRegistrationExpired::class);
    }

    public function test_a_row_not_due_under_the_lock_is_skipped(): void
    {
        Event::fake([GuestRegistrationExpired::class]);
        $guest = $this->submit('invitada@example.test');

        // Listed as overdue, then sent again as a new registration with a new deadline
        $this->assertFalse($this->service()->expireGuestRegistration($guest->id, true, CarbonImmutable::parse('2026-11-02 11:00:00')->toDateTimeImmutable()));
        $this->assertSame('confirmed', ParticipantModel::query()->findOrFail($guest->id)->status->value);
        Event::assertNotDispatched(GuestRegistrationExpired::class);
    }

    public function test_fingerprints_are_cleared_after_the_table_ends(): void
    {
        $ended = $this->createTable('Pasada', 'pasada', now()->subDay());
        $pastGuest = $this->participant($ended, 'past@example.test', fingerprint: str_repeat('a', 64));
        $pastUser = $this->participant($ended, null, fingerprint: str_repeat('b', 64));
        $upcoming = $this->participant($this->table, 'next@example.test', fingerprint: str_repeat('c', 64));

        $counts = $this->expirer()->run(CarbonImmutable::now()->toDateTimeImmutable());

        $this->assertSame(2, $counts['fingerprintsCleared']);
        $this->assertNull($pastGuest->refresh()->fingerprint);
        $this->assertNull($pastUser->refresh()->fingerprint);
        $this->assertSame(str_repeat('c', 64), $upcoming->refresh()->fingerprint);

        // Nothing left to clear on the next run
        $this->assertSame(0, $this->expirer()->run(CarbonImmutable::now()->toDateTimeImmutable())['fingerprintsCleared']);
    }

    public function test_one_failing_row_does_not_stop_the_run(): void
    {
        Event::fake([GuestRegistrationExpired::class]);
        $failing = $this->submit('falla@example.test');
        $other = $this->submit('otra@example.test');
        Log::spy();

        $real = $this->service();
        $registrations = $this->createStub(RegistrationServiceInterface::class);
        $registrations->method('expireGuestRegistration')->willReturnCallback(
            function (string $participantId, bool $notifyGuest = true, ?\DateTimeImmutable $now = null) use ($real, $failing): bool {
                if ($participantId === $failing->id) {
                    throw new RuntimeException('boom');
                }

                return $real->expireGuestRegistration($participantId, $notifyGuest, $now);
            },
        );
        $expirer = new GuestRegistrationExpirer($registrations, app(ParticipantRepositoryInterface::class), app(GameTableRepositoryInterface::class));

        $this->travelTo(CarbonImmutable::parse('2026-11-02 12:00:00'));
        $counts = $expirer->run(CarbonImmutable::now()->toDateTimeImmutable());

        $this->assertSame(['expired' => 1, 'closed' => 0, 'fingerprintsCleared' => 0, 'failed' => 1], $counts);
        $this->assertSame('expired', ParticipantModel::query()->findOrFail($other->id)->status->value);
        $this->assertSame('confirmed', ParticipantModel::query()->findOrFail($failing->id)->status->value);
        Log::shouldHaveReceived('error')->once()->with(
            'game-tables: guest registration expiry failed',
            Mockery::on(fn (array $context): bool => $context['participant_id'] === $failing->id && $context['exception'] instanceof RuntimeException),
        );
    }

    /**
     * @return list<string|null> the address of every email sent on demand
     */
    private function mailRecipients(): array
    {
        $recipients = [];

        foreach (Notification::sentNotifications()[AnonymousNotifiable::class] ?? [] as $byKey) {
            foreach ($byKey as $sentOfClass) {
                foreach ($sentOfClass as $sent) {
                    $recipients[] = $sent['notifiable']->routes['mail'] ?? null;
                }
            }
        }

        return $recipients;
    }

    private function expirer(): GuestRegistrationExpirer
    {
        return app(GuestRegistrationExpirer::class);
    }

    private function service(): RegistrationServiceInterface
    {
        return app(RegistrationServiceInterface::class);
    }

    private function createTable(string $title, string $slug, mixed $startsAt): GameTableModel
    {
        return GameTableModel::create([
            'id' => (string) Str::uuid(),
            'game_system_id' => $this->system->id,
            'created_by' => UserModel::factory()->create()->id,
            'title' => $title,
            'slug' => $slug,
            'starts_at' => $startsAt,
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

    private function submit(string $email): ParticipantResponseDTO
    {
        return $this->service()->registerGuest(new RegisterParticipantDTO(
            gameTableId: $this->table->id,
            userId: null,
            role: ParticipantRole::Player,
            firstName: 'Invitada',
            email: $email,
        ));
    }

    private function registerUser(): ParticipantResponseDTO
    {
        return $this->service()->registerByAdmin(new RegisterParticipantDTO(
            gameTableId: $this->table->id,
            userId: UserModel::factory()->create()->id,
            role: ParticipantRole::Player,
        ));
    }

    private function participant(GameTableModel $table, ?string $email, string $fingerprint): ParticipantModel
    {
        return ParticipantModel::create([
            'id' => (string) Str::uuid(),
            'game_table_id' => $table->id,
            'user_id' => $email === null ? UserModel::factory()->create()->id : null,
            'email' => $email,
            'first_name' => $email === null ? null : 'Invitada',
            'role' => 'player',
            'status' => 'confirmed',
            'email_confirmed_at' => now(),
            'fingerprint' => $fingerprint,
        ]);
    }
}
