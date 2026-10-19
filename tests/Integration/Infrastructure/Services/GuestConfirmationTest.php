<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Integration\Infrastructure\Services;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Modules\GameTables\Application\DTOs\ParticipantResponseDTO;
use Modules\GameTables\Application\DTOs\RegisterParticipantDTO;
use Modules\GameTables\Application\Services\RegistrationServiceInterface;
use Modules\GameTables\Domain\Enums\GuestConfirmationOutcome;
use Modules\GameTables\Domain\Enums\ParticipantRole;
use Modules\GameTables\Domain\Enums\ParticipantStatus;
use Modules\GameTables\Domain\Events\GuestConfirmationRequested;
use Modules\GameTables\Domain\Events\GuestRegistered;
use Modules\GameTables\Domain\Events\GuestRegistrationExpired;
use Modules\GameTables\Domain\Events\ParticipantConfirmed;
use Modules\GameTables\Domain\Events\ParticipantPromotedFromWaitingList;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameSystemModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameTableModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\ParticipantModel;
use Tests\Support\Modules\ModuleTestCase;

/**
 * The guest follows the emailed link: hold rows keep what they hold, wait rows are placed then,
 * and only a confirmed registration is announced. Overdue or closed links never confirm.
 */
final class GuestConfirmationTest extends ModuleTestCase
{
    protected ?string $moduleName = 'game-tables';

    protected bool $autoEnableModule = true;

    private GameTableModel $table;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        Event::fake([GuestConfirmationRequested::class, GuestRegistered::class, GuestRegistrationExpired::class, ParticipantConfirmed::class, ParticipantPromotedFromWaitingList::class]);
        config()->set('modules.settings.game-tables', []);
        $this->travelTo(CarbonImmutable::parse('2026-11-02 10:00:00'));

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

    public function test_hold_confirmation_keeps_the_seat_and_announces_it(): void
    {
        $guest = $this->submit('invitada@example.test');
        $token = $this->tokenOf($guest);

        $this->travelTo(CarbonImmutable::parse('2026-11-02 10:30:00'));
        $result = $this->service()->confirmGuestEmail($token);

        $this->assertSame(GuestConfirmationOutcome::Confirmed, $result->outcome);
        $this->assertSame($this->table->id, $result->gameTableId);
        $this->assertSame($guest->id, $result->participant?->id);
        $this->assertFalse($result->participant->awaitingEmailConfirmation);

        $row = ParticipantModel::query()->findOrFail($guest->id);
        $this->assertSame('confirmed', $row->status->value);
        $this->assertSame('2026-11-02 10:30:00', $row->email_confirmed_at?->format('Y-m-d H:i:s'));
        $this->assertNull($row->confirmation_expires_at);
        // Kept, so a second click finds the confirmed registration
        $this->assertSame($token, $row->confirmation_token);
        Event::assertNotDispatched(GuestRegistrationExpired::class);

        Event::assertDispatchedTimes(GuestRegistered::class, 1);
        Event::assertDispatched(
            GuestRegistered::class,
            fn (GuestRegistered $event): bool => $event->participantId === $guest->id
                && $event->email === 'invitada@example.test'
                && $event->cancellationToken === $row->cancellation_token
                && $event->status === ParticipantStatus::Confirmed
                && $event->notifyOrganization === true,
        );
        Event::assertDispatched(ParticipantConfirmed::class, fn (ParticipantConfirmed $event): bool => $event->participantId === $guest->id && $event->automatic);
    }

    public function test_a_second_click_is_already_confirmed(): void
    {
        $token = $this->tokenOf($this->submit('invitada@example.test'));
        $this->service()->confirmGuestEmail($token);

        $result = $this->service()->confirmGuestEmail($token);

        $this->assertSame(GuestConfirmationOutcome::AlreadyConfirmed, $result->outcome);
        $this->assertSame($this->table->id, $result->gameTableId);
        Event::assertDispatchedTimes(GuestRegistered::class, 1);
    }

    public function test_an_overdue_link_expires_the_registration(): void
    {
        $guest = $this->submit('invitada@example.test');
        $token = $this->tokenOf($guest);

        $this->travelTo(CarbonImmutable::parse('2026-11-02 12:01:00'));
        $result = $this->service()->confirmGuestEmail($token);

        $this->assertSame(GuestConfirmationOutcome::Invalid, $result->outcome);
        $row = ParticipantModel::query()->findOrFail($guest->id);
        $this->assertSame('expired', $row->status->value);
        $this->assertNull($row->email_confirmed_at);
        $this->assertNull($row->confirmation_token);
        Event::assertNotDispatched(GuestRegistered::class);
        Event::assertNotDispatched(ParticipantConfirmed::class);
        // As the expiry command would: the guest held a place and is told it was released
        Event::assertDispatchedTimes(GuestRegistrationExpired::class, 1);
        Event::assertDispatched(GuestRegistrationExpired::class, fn (GuestRegistrationExpired $event): bool => $event->participantId === $guest->id
            && $event->email === 'invitada@example.test'
            && $event->notifyGuest === true);
    }

    public function test_an_overdue_link_gives_the_seat_to_the_waiting_list(): void
    {
        config()->set('modules.settings.game-tables.registration.waiting_list_enabled', true);
        $this->table->update(['max_players' => 1]);
        $token = $this->tokenOf($this->submit('invitada@example.test'));
        $waiting = $this->registerUser();
        $this->assertSame('waiting_list', ParticipantModel::query()->findOrFail($waiting->id)->status->value);

        $this->travelTo(CarbonImmutable::parse('2026-11-02 12:01:00'));
        $result = $this->service()->confirmGuestEmail($token);

        $this->assertSame(GuestConfirmationOutcome::Invalid, $result->outcome);
        $this->assertSame('confirmed', ParticipantModel::query()->findOrFail($waiting->id)->status->value);
        Event::assertDispatched(ParticipantPromotedFromWaitingList::class, fn (ParticipantPromotedFromWaitingList $event): bool => $event->participantId === $waiting->id);
    }

    public function test_after_the_closing_the_link_does_not_confirm(): void
    {
        $guest = $this->submit('invitada@example.test');
        $token = $this->tokenOf($guest);
        $this->table->update(['registration_closes_at' => now()]);

        $result = $this->service()->confirmGuestEmail($token);

        $this->assertSame(GuestConfirmationOutcome::Closed, $result->outcome);
        $this->assertSame($this->table->id, $result->gameTableId);
        $row = ParticipantModel::query()->findOrFail($guest->id);
        $this->assertSame('expired', $row->status->value);
        $this->assertNull($row->email_confirmed_at);
        Event::assertNotDispatched(GuestRegistered::class);
        // The place was held: told as the expiry command would
        Event::assertDispatched(GuestRegistrationExpired::class, fn (GuestRegistrationExpired $event): bool => $event->participantId === $guest->id
            && $event->notifyGuest === true);
    }

    public function test_an_unknown_or_cleared_token_is_invalid(): void
    {
        $guest = $this->submit('invitada@example.test');
        $token = $this->tokenOf($guest);
        $this->service()->cancel($guest->id);

        $this->assertSame(GuestConfirmationOutcome::Invalid, $this->service()->confirmGuestEmail($token)->outcome);
        $this->assertSame(GuestConfirmationOutcome::Invalid, $this->service()->confirmGuestEmail(str_repeat('f', 64))->outcome);
        $this->assertNull($this->service()->findByConfirmationToken(str_repeat('f', 64)));
        Event::assertNotDispatched(GuestRegistered::class);
    }

    public function test_wait_confirmation_takes_a_free_seat(): void
    {
        config()->set('modules.settings.game-tables.guest_confirmation.mode', 'wait');
        $guest = $this->submit('invitada@example.test');
        $this->assertSame('pending', ParticipantModel::query()->findOrFail($guest->id)->status->value);

        $result = $this->service()->confirmGuestEmail($this->tokenOf($guest));

        $this->assertSame(GuestConfirmationOutcome::Confirmed, $result->outcome);
        $row = ParticipantModel::query()->findOrFail($guest->id);
        $this->assertSame('confirmed', $row->status->value);
        $this->assertNotNull($row->email_confirmed_at);
        Event::assertDispatched(GuestRegistered::class, fn (GuestRegistered $event): bool => $event->participantId === $guest->id
            && $event->status === ParticipantStatus::Confirmed);
    }

    public function test_wait_confirmation_joins_the_end_of_the_list(): void
    {
        config()->set('modules.settings.game-tables.guest_confirmation.mode', 'wait');
        config()->set('modules.settings.game-tables.registration.waiting_list_enabled', true);
        $this->table->update(['max_players' => 1]);
        $guest = $this->submit('invitada@example.test');
        $this->registerUser();
        $first = $this->registerUser();
        $this->assertSame(1, ParticipantModel::query()->findOrFail($first->id)->waiting_list_position);

        $result = $this->service()->confirmGuestEmail($this->tokenOf($guest));

        $this->assertSame(GuestConfirmationOutcome::Confirmed, $result->outcome);
        $row = ParticipantModel::query()->findOrFail($guest->id);
        $this->assertSame('waiting_list', $row->status->value);
        $this->assertSame(2, $row->waiting_list_position);
        $this->assertSame(2, $result->participant?->waitingListRank);
        Event::assertDispatched(GuestRegistered::class, fn (GuestRegistered $event): bool => $event->participantId === $guest->id
            && $event->status === ParticipantStatus::WaitingList
            && $event->waitingListPosition === 2);
    }

    public function test_wait_confirmation_without_room_reports_filled_up(): void
    {
        config()->set('modules.settings.game-tables.guest_confirmation.mode', 'wait');
        $this->table->update(['max_players' => 1]);
        $guest = $this->submit('invitada@example.test');
        $this->registerUser();

        $result = $this->service()->confirmGuestEmail($this->tokenOf($guest));

        $this->assertSame(GuestConfirmationOutcome::FilledUp, $result->outcome);
        $this->assertSame($this->table->id, $result->gameTableId);
        $row = ParticipantModel::query()->findOrFail($guest->id);
        $this->assertSame('expired', $row->status->value);
        $this->assertNull($row->email_confirmed_at);
        $this->assertNull($row->confirmation_token);
        Event::assertNotDispatched(GuestRegistered::class);
        // No place was ever held (wait mode): no expiry email, the page says it
        Event::assertDispatched(GuestRegistrationExpired::class, fn (GuestRegistrationExpired $event): bool => $event->participantId === $guest->id
            && $event->notifyGuest === false);
    }

    public function test_hold_confirmation_from_the_list_takes_a_free_seat(): void
    {
        config()->set('modules.settings.game-tables.registration.waiting_list_enabled', true);
        $this->table->update(['max_players' => 1]);
        $seated = $this->registerUser();
        $guest = $this->submit('invitada@example.test');
        $this->assertSame('waiting_list', ParticipantModel::query()->findOrFail($guest->id)->status->value);

        // The freed seat is not given to an unconfirmed guest: it keeps its place and waits
        $this->service()->cancelByAdmin($seated->id);
        $row = ParticipantModel::query()->findOrFail($guest->id);
        $this->assertSame('waiting_list', $row->status->value);
        $this->assertSame(1, $row->waiting_list_position);
        Event::assertNotDispatched(ParticipantPromotedFromWaitingList::class);

        $result = $this->service()->confirmGuestEmail($this->tokenOf($guest));

        $this->assertSame(GuestConfirmationOutcome::Confirmed, $result->outcome);
        $row->refresh();
        $this->assertSame('confirmed', $row->status->value);
        $this->assertNull($row->waiting_list_position);
        $this->assertNotNull($row->email_confirmed_at);
        $this->assertSame(ParticipantStatus::Confirmed, $result->participant?->status);
        // The guest is told once, by the registration email, not also by a promotion email
        Event::assertNotDispatched(ParticipantPromotedFromWaitingList::class);
        Event::assertDispatched(GuestRegistered::class, fn (GuestRegistered $event): bool => $event->participantId === $guest->id
            && $event->status === ParticipantStatus::Confirmed
            && $event->waitingListPosition === null);
    }

    public function test_hold_confirmation_on_the_list_keeps_the_place_when_the_table_is_full(): void
    {
        config()->set('modules.settings.game-tables.registration.waiting_list_enabled', true);
        $this->table->update(['max_players' => 1]);
        $this->registerUser();
        $guest = $this->submit('invitada@example.test');

        $result = $this->service()->confirmGuestEmail($this->tokenOf($guest));

        $this->assertSame(GuestConfirmationOutcome::Confirmed, $result->outcome);
        $row = ParticipantModel::query()->findOrFail($guest->id);
        $this->assertSame('waiting_list', $row->status->value);
        $this->assertSame(1, $row->waiting_list_position);
        Event::assertDispatched(GuestRegistered::class, fn (GuestRegistered $event): bool => $event->waitingListPosition === 1);
    }

    private function service(): RegistrationServiceInterface
    {
        return app(RegistrationServiceInterface::class);
    }

    private function submit(string $email, ParticipantRole $role = ParticipantRole::Player): ParticipantResponseDTO
    {
        return $this->service()->registerGuest(new RegisterParticipantDTO(
            gameTableId: $this->table->id,
            userId: null,
            role: $role,
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

    private function tokenOf(ParticipantResponseDTO $participant): string
    {
        return (string) ParticipantModel::query()->findOrFail($participant->id)->confirmation_token;
    }
}
