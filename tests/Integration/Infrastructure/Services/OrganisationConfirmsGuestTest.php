<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Integration\Infrastructure\Services;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Carbon\CarbonImmutable;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Modules\GameTables\Application\DTOs\ParticipantResponseDTO;
use Modules\GameTables\Application\DTOs\RegisterParticipantDTO;
use Modules\GameTables\Application\Services\RegistrationServiceInterface;
use Modules\GameTables\Domain\Enums\ParticipantRole;
use Modules\GameTables\Domain\Enums\ParticipantStatus;
use Modules\GameTables\Domain\Events\GuestRegistered;
use Modules\GameTables\Domain\Events\ParticipantConfirmed;
use Modules\GameTables\Domain\Events\ParticipantPromotedFromWaitingList;
use Modules\GameTables\Domain\Exceptions\TableFullException;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameSystemModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameTableModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\ParticipantModel;
use Modules\GameTables\Notifications\GuestRegistrationConfirmation;
use Modules\GameTables\Notifications\ParticipantConfirmedNotification;
use Modules\GameTables\Notifications\ParticipantRegisteredNotification;
use Modules\GameTables\Notifications\WaitingListPromotionGmNotification;
use Modules\GameTables\Notifications\WaitingListPromotionNotification;
use Tests\Support\Modules\ModuleTestCase;

/**
 * The organisation acts from the panel on a guest whose email is not confirmed: approving,
 * promoting, marking a no-show or «Marcar email como confirmado» vouch for the email. The guest
 * gets the usual registration email (one email), the organisation nothing for its own action.
 */
final class OrganisationConfirmsGuestTest extends ModuleTestCase
{
    protected ?string $moduleName = 'game-tables';

    protected bool $autoEnableModule = true;

    private GameTableModel $table;

    /**
     * @var list<GuestRegistered>
     */
    private array $guestRegistered = [];

    /**
     * @var list<ParticipantConfirmed>
     */
    private array $participantConfirmed = [];

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        config()->set('modules.settings.game-tables', []);
        $this->travelTo(CarbonImmutable::parse('2026-11-02 10:00:00'));

        // The real listeners still run: these only record what was dispatched
        Event::listen(GuestRegistered::class, function (GuestRegistered $event): void {
            $this->guestRegistered[] = $event;
        });
        Event::listen(ParticipantConfirmed::class, function (ParticipantConfirmed $event): void {
            $this->participantConfirmed[] = $event;
        });

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
            'notification_email' => 'mesa@example.test',
        ]);
    }

    public function test_approving_an_awaiting_hold_guest_confirms_the_email_and_sends_the_registration_email(): void
    {
        $this->table->update(['auto_confirm' => false]);
        $guest = $this->submit('invitada@example.test');
        $this->assertSame('pending', $this->row($guest)->status->value);

        $this->travelTo(CarbonImmutable::parse('2026-11-02 10:30:00'));
        $this->service()->confirm($guest->id);

        $row = $this->row($guest);
        $this->assertSame('confirmed', $row->status->value);
        $this->assertSame('2026-11-02 10:30:00', $row->email_confirmed_at?->format('Y-m-d H:i:s'));
        $this->assertNull($row->confirmation_expires_at);

        $this->assertCount(1, $this->guestRegistered);
        $this->assertSame($guest->id, $this->guestRegistered[0]->participantId);
        $this->assertSame(ParticipantStatus::Confirmed, $this->guestRegistered[0]->status);
        $this->assertFalse($this->guestRegistered[0]->notifyOrganization);
        $this->assertCount(1, $this->participantConfirmed);
        $this->assertTrue($this->participantConfirmed[0]->automatic);

        // One email to the guest, with the cancellation link; nothing to the organisation
        $this->assertGuestEmailedOnce('invitada@example.test', GuestRegistrationConfirmation::class);
        Notification::assertSentOnDemandTimes(ParticipantConfirmedNotification::class, 0);
        Notification::assertSentOnDemandTimes(ParticipantRegisteredNotification::class, 0);
    }

    public function test_approving_a_confirmed_guest_works_as_before(): void
    {
        $this->table->update(['auto_confirm' => false]);
        $guest = $this->registerGuestByAdmin('invitada@example.test');
        $this->forgetRecordedEvents();

        $this->service()->confirm($guest->id);

        $this->assertSame('confirmed', $this->row($guest)->status->value);
        $this->assertSame([], $this->guestRegistered);
        $this->assertCount(1, $this->participantConfirmed);
        $this->assertFalse($this->participantConfirmed[0]->automatic);
        Notification::assertSentOnDemandTimes(ParticipantConfirmedNotification::class, 1);
    }

    public function test_approving_a_wait_guest_places_it_first(): void
    {
        config()->set('modules.settings.game-tables.guest_confirmation.mode', 'wait');
        $this->table->update(['auto_confirm' => false, 'max_players' => 1]);
        $guest = $this->submit('invitada@example.test');
        $this->assertSame('pending', $this->row($guest)->status->value);

        $this->service()->confirm($guest->id);

        $row = $this->row($guest);
        $this->assertSame('confirmed', $row->status->value);
        $this->assertNotNull($row->email_confirmed_at);
        $this->assertNull($row->waiting_list_position);
        $this->assertGuestEmailedOnce('invitada@example.test', GuestRegistrationConfirmation::class);
        Notification::assertSentOnDemandTimes(ParticipantConfirmedNotification::class, 0);
    }

    public function test_approving_a_wait_guest_without_a_free_seat_changes_nothing(): void
    {
        config()->set('modules.settings.game-tables.guest_confirmation.mode', 'wait');
        config()->set('modules.settings.game-tables.registration.waiting_list_enabled', true);
        $this->table->update(['auto_confirm' => false, 'max_players' => 1]);
        $guest = $this->submit('invitada@example.test');
        $seated = $this->registerUser();
        $this->service()->confirm($seated->id);
        $this->guestRegistered = [];

        try {
            $this->service()->confirm($guest->id);
            $this->fail('A wait guest was approved without a free seat.');
        } catch (TableFullException $exception) {
            $this->assertSame('table_full', $exception->reason);
        }

        // Never into the waiting list either: approving means a seat
        $row = $this->row($guest);
        $this->assertSame('pending', $row->status->value);
        $this->assertNull($row->waiting_list_position);
        $this->assertNull($row->email_confirmed_at);
        $this->assertSame([], $this->guestRegistered);
        Notification::assertSentOnDemandTimes(GuestRegistrationConfirmation::class, 0);
    }

    // With the list off, approving a user never checks the seats; a guest without one is never let over them
    public function test_approving_a_wait_guest_with_the_list_off_on_a_full_table_changes_nothing(): void
    {
        config()->set('modules.settings.game-tables.guest_confirmation.mode', 'wait');
        $this->table->update(['auto_confirm' => false, 'max_players' => 1]);
        $guest = $this->submit('invitada@example.test');
        $seated = $this->registerUser();
        $this->service()->confirm($seated->id);
        $this->forgetRecordedEvents();

        try {
            $this->service()->confirm($guest->id);
            $this->fail('A wait guest was approved over the seats.');
        } catch (TableFullException $exception) {
            $this->assertSame('table_full', $exception->reason);
        }

        $row = $this->row($guest);
        $this->assertSame('pending', $row->status->value);
        $this->assertNull($row->confirmed_at);
        $this->assertNull($row->waiting_list_position);
        $this->assertNull($row->email_confirmed_at);
        $this->assertSame(1, ParticipantModel::query()->where('game_table_id', $this->table->id)->where('status', 'confirmed')->count());
        $this->assertSame([], $this->guestRegistered);
        $this->assertSame([], $this->participantConfirmed);
        Notification::assertSentOnDemandTimes(GuestRegistrationConfirmation::class, 0);
    }

    // On a table that confirms by itself the placement already confirms: approved once, one email
    public function test_approving_a_wait_guest_on_an_auto_confirm_table(): void
    {
        config()->set('modules.settings.game-tables.guest_confirmation.mode', 'wait');
        $guest = $this->submit('invitada@example.test');
        $this->assertSame('pending', $this->row($guest)->status->value);

        $this->service()->confirm($guest->id);

        $row = $this->row($guest);
        $this->assertSame('confirmed', $row->status->value);
        $this->assertNotNull($row->confirmed_at);
        $this->assertNotNull($row->email_confirmed_at);
        $this->assertCount(1, $this->guestRegistered);
        $this->assertSame(ParticipantStatus::Confirmed, $this->guestRegistered[0]->status);
        $this->assertCount(1, $this->participantConfirmed);
        $this->assertTrue($this->participantConfirmed[0]->automatic);
        $this->assertGuestEmailedOnce('invitada@example.test', GuestRegistrationConfirmation::class);
        Notification::assertSentOnDemandTimes(ParticipantConfirmedNotification::class, 0);
        Notification::assertSentOnDemandTimes(ParticipantRegisteredNotification::class, 0);
    }

    public function test_promoting_an_awaiting_waiting_guest_confirms_the_email_with_one_email(): void
    {
        config()->set('modules.settings.game-tables.registration.waiting_list_enabled', true);
        $this->table->update(['max_players' => 1]);
        $this->registerUser();
        $guest = $this->submit('invitada@example.test');
        $this->assertSame('waiting_list', $this->row($guest)->status->value);
        // A seat frees up without anyone promoted (an unconfirmed guest is skipped automatically)
        $this->table->update(['max_players' => 2]);

        $this->service()->promoteParticipant($guest->id);

        $row = $this->row($guest);
        $this->assertSame('confirmed', $row->status->value);
        $this->assertNull($row->waiting_list_position);
        $this->assertNotNull($row->email_confirmed_at);
        $this->assertCount(1, $this->guestRegistered);
        $this->assertFalse($this->guestRegistered[0]->notifyOrganization);
        $this->assertSame(ParticipantStatus::Confirmed, $this->guestRegistered[0]->status);

        // The promotion email has no cancellation link: the registration email goes instead
        $this->assertGuestEmailedOnce('invitada@example.test', GuestRegistrationConfirmation::class);
        Notification::assertSentOnDemandTimes(WaitingListPromotionNotification::class, 0);
        Notification::assertSentOnDemandTimes(WaitingListPromotionGmNotification::class, 0);
    }

    public function test_promoting_a_user_still_sends_the_promotion_emails(): void
    {
        Event::fake([ParticipantPromotedFromWaitingList::class]);
        config()->set('modules.settings.game-tables.registration.waiting_list_enabled', true);
        $this->table->update(['max_players' => 1]);
        $this->registerUser();
        $waiting = $this->registerUser();
        $this->table->update(['max_players' => 2]);

        $this->service()->promoteParticipant($waiting->id);

        Event::assertDispatched(ParticipantPromotedFromWaitingList::class, fn (ParticipantPromotedFromWaitingList $event): bool => $event->participantId === $waiting->id);
    }

    public function test_marking_an_awaiting_guest_as_no_show_confirms_the_email_silently(): void
    {
        $this->table->update(['starts_at' => now()->subHour()]);
        $row = ParticipantModel::create([
            'id' => (string) Str::uuid(),
            'game_table_id' => $this->table->id,
            'user_id' => null,
            'first_name' => 'Invitada',
            'email' => 'invitada@example.test',
            'cancellation_token' => bin2hex(random_bytes(32)),
            'role' => 'player',
            'status' => 'confirmed',
            'confirmed_at' => now(),
            'confirmation_mode' => 'hold',
            'confirmation_token' => bin2hex(random_bytes(32)),
            'confirmation_sent_at' => now()->subHours(3),
            'confirmation_expires_at' => now()->addHour(),
        ]);

        $this->service()->markAsNoShow($row->id);

        $row->refresh();
        $this->assertSame('no_show', $row->status->value);
        $this->assertSame('2026-11-02 10:00:00', $row->email_confirmed_at?->format('Y-m-d H:i:s'));
        $this->assertSame([], $this->guestRegistered);
        $this->assertSame([], $this->participantConfirmed);
        Notification::assertNothingSent();
    }

    public function test_marking_the_email_confirmed_keeps_a_hold_seat_and_sends_the_registration_email(): void
    {
        $guest = $this->submit('invitada@example.test');

        $result = $this->service()->confirmGuestEmailByAdmin($guest->id);

        $this->assertFalse($result->awaitingEmailConfirmation);
        $row = $this->row($guest);
        $this->assertSame('confirmed', $row->status->value);
        $this->assertSame('2026-11-02 10:00:00', $row->email_confirmed_at?->format('Y-m-d H:i:s'));
        $this->assertCount(1, $this->guestRegistered);
        $this->assertFalse($this->guestRegistered[0]->notifyOrganization);
        $this->assertCount(1, $this->participantConfirmed);
        $this->assertTrue($this->participantConfirmed[0]->automatic);
        $this->assertGuestEmailedOnce('invitada@example.test', GuestRegistrationConfirmation::class);
        Notification::assertSentOnDemandTimes(ParticipantRegisteredNotification::class, 0);
        Notification::assertSentOnDemandTimes(ParticipantConfirmedNotification::class, 0);
    }

    public function test_marking_the_email_confirmed_places_a_wait_guest_as_the_link_would(): void
    {
        config()->set('modules.settings.game-tables.guest_confirmation.mode', 'wait');
        config()->set('modules.settings.game-tables.registration.waiting_list_enabled', true);
        $this->table->update(['max_players' => 1]);
        $guest = $this->submit('invitada@example.test');
        $this->registerUser();

        $result = $this->service()->confirmGuestEmailByAdmin($guest->id);

        $row = $this->row($guest);
        $this->assertSame('waiting_list', $row->status->value);
        $this->assertNotNull($row->email_confirmed_at);
        $this->assertSame(1, $result->waitingListRank);
        $this->assertSame(ParticipantStatus::WaitingList, $this->guestRegistered[0]->status);
        $this->assertSame(1, $this->guestRegistered[0]->waitingListPosition);
    }

    public function test_marking_the_email_confirmed_without_room_leaves_the_guest_awaiting(): void
    {
        config()->set('modules.settings.game-tables.guest_confirmation.mode', 'wait');
        $this->table->update(['max_players' => 1]);
        $guest = $this->submit('invitada@example.test');
        $this->registerUser();
        $token = $this->row($guest)->confirmation_token;

        try {
            $this->service()->confirmGuestEmailByAdmin($guest->id);
            $this->fail('A wait guest was confirmed without room.');
        } catch (TableFullException $exception) {
            $this->assertSame('table_full', $exception->reason);
        }

        $row = $this->row($guest);
        $this->assertSame('pending', $row->status->value);
        $this->assertNull($row->email_confirmed_at);
        $this->assertSame($token, $row->confirmation_token);
        $this->assertSame([], $this->guestRegistered);
    }

    public function test_marking_a_confirmed_email_changes_nothing(): void
    {
        $guest = $this->registerGuestByAdmin('invitada@example.test');
        $before = $this->row($guest)->email_confirmed_at;
        $this->forgetRecordedEvents();

        $this->travelTo(CarbonImmutable::parse('2026-11-02 11:00:00'));
        $result = $this->service()->confirmGuestEmailByAdmin($guest->id);

        $this->assertSame($guest->id, $result->id);
        $this->assertEquals($before, $this->row($guest)->email_confirmed_at);
        $this->assertSame([], $this->guestRegistered);
        $this->assertSame([], $this->participantConfirmed);
    }

    private function forgetRecordedEvents(): void
    {
        $this->guestRegistered = [];
        $this->participantConfirmed = [];
    }

    /**
     * @param  class-string  $notification
     */
    private function assertGuestEmailedOnce(string $email, string $notification): void
    {
        Notification::assertSentOnDemandTimes($notification, 1);
        Notification::assertSentOnDemand($notification, fn (object $sent, array $channels, AnonymousNotifiable $notifiable): bool => $notifiable->routes['mail'] === $email);
    }

    private function service(): RegistrationServiceInterface
    {
        return app(RegistrationServiceInterface::class);
    }

    private function row(ParticipantResponseDTO $participant): ParticipantModel
    {
        return ParticipantModel::query()->findOrFail($participant->id);
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

    private function registerGuestByAdmin(string $email): ParticipantResponseDTO
    {
        return $this->service()->registerGuestByAdmin(new RegisterParticipantDTO(
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
}
