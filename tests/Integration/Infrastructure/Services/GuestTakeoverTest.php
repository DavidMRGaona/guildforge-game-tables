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
use Modules\GameTables\Domain\Enums\ParticipantRole;
use Modules\GameTables\Domain\Enums\ParticipantStatus;
use Modules\GameTables\Domain\Events\GuestConfirmationRequested;
use Modules\GameTables\Domain\Events\GuestRegistered;
use Modules\GameTables\Domain\Events\GuestRegistrationExpired;
use Modules\GameTables\Domain\Events\ParticipantConfirmed;
use Modules\GameTables\Domain\Events\ParticipantPromotedFromWaitingList;
use Modules\GameTables\Domain\Events\ParticipantRegistered;
use Modules\GameTables\Domain\Exceptions\AlreadyRegisteredException;
use Modules\GameTables\Domain\Exceptions\RegistrationNotAllowedException;
use Modules\GameTables\Domain\Exceptions\TableFullException;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameSystemModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameTableModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\ParticipantModel;
use Tests\Support\Modules\ModuleTestCase;

/**
 * A signed-in user registers at a table where a guest registration with their account email is
 * active: with the email verified the row becomes theirs, keeping its place and role; otherwise,
 * or when the account rules refuse them, the guest row stays as it is.
 */
final class GuestTakeoverTest extends ModuleTestCase
{
    protected ?string $moduleName = 'game-tables';

    protected bool $autoEnableModule = true;

    private const array EVENTS = [
        GuestConfirmationRequested::class,
        GuestRegistered::class,
        GuestRegistrationExpired::class,
        ParticipantConfirmed::class,
        ParticipantPromotedFromWaitingList::class,
        ParticipantRegistered::class,
    ];

    private GameTableModel $table;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        Event::fake(self::EVENTS);
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

    public function test_a_verified_account_takes_over_a_confirmed_guest_row(): void
    {
        $guest = $this->guestByAdmin('cuenta@example.test');
        $user = UserModel::factory()->create(['email' => 'Cuenta@Example.test']);
        Event::fake(self::EVENTS);

        // The role asked for is ignored: the row keeps the guest's
        $result = $this->register($user, ParticipantRole::Spectator);

        $this->assertSame($guest->id, $result->id);
        $this->assertTrue($result->linkedFromGuest);
        $row = ParticipantModel::query()->findOrFail($guest->id);
        $this->assertSame($user->id, $row->user_id);
        $this->assertNull($row->email);
        $this->assertNull($row->cancellation_token);
        $this->assertNull($row->confirmation_token);
        // The account holds the person's data now: anonymizing it must leave nothing behind
        $this->assertNull($row->first_name);
        $this->assertNull($row->last_name);
        $this->assertNull($row->phone);
        $this->assertSame('player', $row->role->value);
        $this->assertSame('confirmed', $row->status->value);
        $this->assertSame(1, ParticipantModel::query()->where('game_table_id', $this->table->id)->count());
        // The organisation and the guest were told when the guest registered
        foreach (self::EVENTS as $event) {
            Event::assertNotDispatched($event);
        }
    }

    public function test_an_unconfirmed_hold_guest_is_confirmed_and_announced(): void
    {
        $guest = $this->submit('cuenta@example.test');
        $user = UserModel::factory()->create(['email' => 'cuenta@example.test']);
        Event::fake(self::EVENTS);

        $this->travelTo(CarbonImmutable::parse('2026-11-02 10:30:00'));
        $result = $this->register($user);

        $this->assertSame($guest->id, $result->id);
        $this->assertTrue($result->linkedFromGuest);
        $this->assertFalse($result->awaitingEmailConfirmation);
        $row = ParticipantModel::query()->findOrFail($guest->id);
        $this->assertSame($user->id, $row->user_id);
        $this->assertSame('confirmed', $row->status->value);
        $this->assertSame('2026-11-02 10:30:00', $row->email_confirmed_at?->format('Y-m-d H:i:s'));
        $this->assertNull($row->confirmation_expires_at);
        $this->assertNull($row->confirmation_token);
        $this->assertNull($row->email);
        $this->assertNull($row->first_name);
        $this->assertNull($row->phone);
        // Kept until the table is over, as for any web registration
        $this->assertSame(str_repeat('a', 64), $row->fingerprint);
        // Never told before: the organisation hears of it now, once
        Event::assertDispatchedTimes(ParticipantRegistered::class, 1);
        Event::assertDispatched(ParticipantRegistered::class, fn (ParticipantRegistered $event): bool => $event->participantId === $guest->id
            && $event->userId === $user->id
            && $event->status === ParticipantStatus::Confirmed);
        Event::assertDispatched(ParticipantConfirmed::class, fn (ParticipantConfirmed $event): bool => $event->participantId === $guest->id && $event->automatic);
        Event::assertNotDispatched(GuestRegistered::class);
    }

    public function test_an_unverified_account_is_refused_and_the_guest_row_stays(): void
    {
        $guest = $this->submit('cuenta@example.test');
        $before = ParticipantModel::query()->findOrFail($guest->id)->getAttributes();
        $user = UserModel::factory()->unverified()->create(['email' => 'cuenta@example.test']);
        Event::fake(self::EVENTS);

        try {
            $this->register($user);
            $this->fail('An unverified account must not take over the guest registration');
        } catch (AlreadyRegisteredException $e) {
            $this->assertSame('email_registered_as_guest', $e->reason);
            $this->assertSame('Ya hay una inscripción con tu email.', $e->getMessage());
        }

        $this->assertSame($before, ParticipantModel::query()->findOrFail($guest->id)->getAttributes());
        $this->assertNull(ParticipantModel::query()->where('user_id', $user->id)->first());
        Event::assertNotDispatched(ParticipantRegistered::class);
    }

    public function test_a_wait_guest_on_a_full_table_without_list_is_refused_and_stays_a_guest(): void
    {
        config()->set('modules.settings.game-tables.guest_confirmation.mode', 'wait');
        config()->set('modules.settings.game-tables.registration.waiting_list_enabled', false);
        $this->table->update(['max_players' => 1]);
        $guest = $this->submit('cuenta@example.test');
        $this->registerOther();
        $user = UserModel::factory()->create(['email' => 'cuenta@example.test']);
        Event::fake(self::EVENTS);

        try {
            $this->register($user);
            $this->fail('No room: the takeover must be refused');
        } catch (TableFullException $e) {
            $this->assertSame('table_full', $e->reason);
        }

        $row = ParticipantModel::query()->findOrFail($guest->id);
        $this->assertNull($row->user_id);
        $this->assertSame('cuenta@example.test', $row->email);
        $this->assertSame('pending', $row->status->value);
        $this->assertNull($row->email_confirmed_at);
        $this->assertNotNull($row->confirmation_token);
        Event::assertNotDispatched(ParticipantRegistered::class);
    }

    public function test_a_wait_guest_is_placed_as_the_link_places_it(): void
    {
        config()->set('modules.settings.game-tables.guest_confirmation.mode', 'wait');
        config()->set('modules.settings.game-tables.registration.waiting_list_enabled', true);
        $this->table->update(['max_players' => 1]);
        $guest = $this->submit('cuenta@example.test');
        $this->registerOther();
        $user = UserModel::factory()->create(['email' => 'cuenta@example.test']);
        Event::fake(self::EVENTS);

        $result = $this->register($user);

        $this->assertSame($guest->id, $result->id);
        $this->assertSame(ParticipantStatus::WaitingList, $result->status);
        $this->assertSame(1, $result->waitingListRank);
        $row = ParticipantModel::query()->findOrFail($guest->id);
        $this->assertSame($user->id, $row->user_id);
        $this->assertNotNull($row->email_confirmed_at);
        Event::assertDispatched(ParticipantRegistered::class, fn (ParticipantRegistered $event): bool => $event->participantId === $guest->id
            && $event->status === ParticipantStatus::WaitingList
            && $event->waitingListPosition === 1);
    }

    public function test_the_account_rules_refuse_first_and_the_row_stays_a_guest(): void
    {
        $guest = $this->guestByAdmin('cuenta@example.test');
        $this->table->update(['registration_type' => 'members_only']);
        $user = UserModel::factory()->create(['email' => 'cuenta@example.test']);
        Event::fake(self::EVENTS);

        try {
            $this->register($user);
            $this->fail('The members-only rule must refuse the user');
        } catch (RegistrationNotAllowedException $e) {
            $this->assertSame('members_only', $e->reason);
            $this->assertSame('Esta mesa es solo para socios', $e->getMessage());
        }

        $row = ParticipantModel::query()->findOrFail($guest->id);
        $this->assertNull($row->user_id);
        $this->assertSame('cuenta@example.test', $row->email);
        Event::assertNotDispatched(ParticipantRegistered::class);
    }

    public function test_the_account_rules_come_before_the_verified_email(): void
    {
        $this->guestByAdmin('cuenta@example.test');
        $this->table->update(['registration_type' => 'members_only']);
        $user = UserModel::factory()->unverified()->create(['email' => 'cuenta@example.test']);

        $this->expectException(RegistrationNotAllowedException::class);
        $this->expectExceptionMessage('Esta mesa es solo para socios');

        $this->register($user);
    }

    public function test_the_users_own_lapsed_row_gives_way_to_the_taken_over_one(): void
    {
        $user = UserModel::factory()->create(['email' => 'cuenta@example.test']);
        $own = $this->register($user);
        $this->service()->cancel($own->id);
        $guest = $this->guestByAdmin('cuenta@example.test');
        Event::fake(self::EVENTS);

        $result = $this->register($user);

        $this->assertSame($guest->id, $result->id);
        $this->assertNull(ParticipantModel::query()->find($own->id));
        $rows = ParticipantModel::query()->where('game_table_id', $this->table->id)->where('user_id', $user->id)->get();
        $this->assertCount(1, $rows);
        $this->assertSame('confirmed', $rows->first()?->status->value);
        $this->assertSame($guest->id, $this->service()->findByTableAndUser($this->table->id, $user->id)?->id);
    }

    public function test_an_overdue_hold_guest_lapses_and_the_user_registers_anew(): void
    {
        $guest = $this->submit('cuenta@example.test');
        $user = UserModel::factory()->create(['email' => 'cuenta@example.test']);
        Event::fake(self::EVENTS);

        $this->travelTo(CarbonImmutable::parse('2026-11-02 12:01:00'));
        $result = $this->register($user, ParticipantRole::Player);

        $this->assertNotSame($guest->id, $result->id);
        $this->assertFalse($result->linkedFromGuest);
        $this->assertSame(ParticipantStatus::Confirmed, $result->status);
        $old = ParticipantModel::query()->findOrFail($guest->id);
        $this->assertSame('expired', $old->status->value);
        $this->assertNull($old->user_id);
        $this->assertNull($old->confirmation_token);
        $this->assertSame($user->id, ParticipantModel::query()->findOrFail($result->id)->user_id);
        Event::assertDispatched(ParticipantRegistered::class, fn (ParticipantRegistered $event): bool => $event->participantId === $result->id);
    }

    public function test_the_organisation_registering_the_user_never_takes_over(): void
    {
        $guest = $this->guestByAdmin('cuenta@example.test');
        $user = UserModel::factory()->create(['email' => 'cuenta@example.test']);

        $result = $this->service()->registerByAdmin(new RegisterParticipantDTO(
            gameTableId: $this->table->id,
            userId: $user->id,
            role: ParticipantRole::Player,
        ));

        $this->assertNotSame($guest->id, $result->id);
        $this->assertFalse($result->linkedFromGuest);
        $row = ParticipantModel::query()->findOrFail($guest->id);
        $this->assertNull($row->user_id);
        $this->assertSame('cuenta@example.test', $row->email);
    }

    public function test_the_users_own_rejected_row_gives_way_to_the_taken_over_one(): void
    {
        $user = UserModel::factory()->create(['email' => 'cuenta@example.test']);
        $own = $this->register($user);
        $this->service()->reject($own->id);
        $guest = $this->guestByAdmin('cuenta@example.test');

        $result = $this->register($user);

        $this->assertSame($guest->id, $result->id);
        $this->assertTrue($result->linkedFromGuest);
        $this->assertNull(ParticipantModel::query()->find($own->id));
        $this->assertSame(1, ParticipantModel::query()->where('game_table_id', $this->table->id)->where('user_id', $user->id)->count());
    }

    public function test_an_unconfirmed_guest_waiting_on_the_list_takes_the_free_seat(): void
    {
        config()->set('modules.settings.game-tables.registration.waiting_list_enabled', true);
        $this->table->update(['max_players' => 1]);
        $other = $this->registerOther();
        $guest = $this->submit('cuenta@example.test');
        $this->assertSame('waiting_list', ParticipantModel::query()->findOrFail($guest->id)->status->value);
        // The freed seat skips the unconfirmed guest: it stays on the list, the seat stays free
        $this->service()->cancel($other->id);
        $this->assertSame('waiting_list', ParticipantModel::query()->findOrFail($guest->id)->status->value);
        $user = UserModel::factory()->create(['email' => 'cuenta@example.test']);
        Event::fake(self::EVENTS);

        $result = $this->register($user);

        $this->assertSame($guest->id, $result->id);
        $this->assertSame(ParticipantStatus::Confirmed, $result->status);
        $this->assertNull($result->waitingListRank);
        $row = ParticipantModel::query()->findOrFail($guest->id);
        $this->assertSame($user->id, $row->user_id);
        $this->assertNull($row->waiting_list_position);
        Event::assertDispatched(ParticipantRegistered::class, fn (ParticipantRegistered $event): bool => $event->participantId === $guest->id
            && $event->status === ParticipantStatus::Confirmed);
        // Its own move to the seat is the registration itself, not a promotion of someone else
        Event::assertNotDispatched(ParticipantPromotedFromWaitingList::class);
    }

    public function test_a_spectator_guest_stays_a_spectator(): void
    {
        $this->table->update(['max_spectators' => 2]);
        $guest = $this->guestByAdmin('cuenta@example.test', ParticipantRole::Spectator);
        $user = UserModel::factory()->create(['email' => 'cuenta@example.test']);

        $result = $this->register($user, ParticipantRole::Player);

        $this->assertSame($guest->id, $result->id);
        $this->assertSame(ParticipantRole::Spectator, $result->role);
        $this->assertSame('spectator', ParticipantModel::query()->findOrFail($guest->id)->role->value);
    }

    public function test_a_wait_spectator_without_a_spectator_seat_is_refused(): void
    {
        config()->set('modules.settings.game-tables.guest_confirmation.mode', 'wait');
        $this->table->update(['max_spectators' => 1]);
        $guest = $this->submit('cuenta@example.test', ParticipantRole::Spectator);
        $this->service()->registerByAdmin(new RegisterParticipantDTO(
            gameTableId: $this->table->id,
            userId: UserModel::factory()->create()->id,
            role: ParticipantRole::Spectator,
        ));
        $user = UserModel::factory()->create(['email' => 'cuenta@example.test']);

        try {
            $this->register($user, ParticipantRole::Spectator);
            $this->fail('No spectator seat: the takeover must be refused');
        } catch (TableFullException $e) {
            $this->assertSame('spectators_full', $e->reason);
        }

        $this->assertNull(ParticipantModel::query()->findOrFail($guest->id)->user_id);
    }

    private function service(): RegistrationServiceInterface
    {
        return app(RegistrationServiceInterface::class);
    }

    private function register(UserModel $user, ParticipantRole $role = ParticipantRole::Player): ParticipantResponseDTO
    {
        return $this->service()->register(new RegisterParticipantDTO(
            gameTableId: $this->table->id,
            userId: $user->id,
            role: $role,
        ));
    }

    private function submit(string $email, ParticipantRole $role = ParticipantRole::Player): ParticipantResponseDTO
    {
        return $this->service()->registerGuest(new RegisterParticipantDTO(
            gameTableId: $this->table->id,
            userId: null,
            role: $role,
            firstName: 'Invitada',
            lastName: 'Pérez',
            email: $email,
            phone: '600000000',
            fingerprint: str_repeat('a', 64),
        ));
    }

    private function guestByAdmin(string $email, ParticipantRole $role = ParticipantRole::Player): ParticipantResponseDTO
    {
        return $this->service()->registerGuestByAdmin(new RegisterParticipantDTO(
            gameTableId: $this->table->id,
            userId: null,
            role: $role,
            firstName: 'Invitada',
            lastName: 'Pérez',
            email: $email,
            phone: '600000000',
        ));
    }

    private function registerOther(): ParticipantResponseDTO
    {
        return $this->service()->registerByAdmin(new RegisterParticipantDTO(
            gameTableId: $this->table->id,
            userId: UserModel::factory()->create()->id,
            role: ParticipantRole::Player,
        ));
    }
}
