<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Integration\Infrastructure\Services;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Modules\GameTables\Application\Services\RegistrationServiceInterface;
use Modules\GameTables\Domain\Events\ParticipantConfirmed;
use Modules\GameTables\Domain\Exceptions\ParticipantAwaitingEmailException;
use Modules\GameTables\Domain\Exceptions\ParticipantNotPendingException;
use Modules\GameTables\Domain\Exceptions\TableClosedException;
use Modules\GameTables\Domain\Exceptions\TableFullException;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameSystemModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameTableModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\ParticipantModel;
use Modules\GameTables\Notifications\ParticipantConfirmedNotification;
use Modules\GameTables\Notifications\ParticipantRejectedNotification;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\Modules\ModuleTestCase;

/**
 * The director's approvals and rejections from the website (approveAsDirector, rejectAsDirector):
 * everything is checked again under the table lock, so a registration answered meanwhile (a
 * cancellation, the panel) is never brought back, a guest's email is never vouched for and the
 * seats are never exceeded, whatever the waiting list setting. The panel keeps confirm()/reject().
 */
final class DirectorRegistrationServiceTest extends ModuleTestCase
{
    protected ?string $moduleName = 'game-tables';

    protected bool $autoEnableModule = true;

    private GameTableModel $table;

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
            'max_players' => 1,
            'max_spectators' => 1,
            'language' => 'es',
            'is_published' => true,
            'auto_confirm' => false,
            'delegates_registrations' => true,
        ]);
    }

    public function test_approving_a_pending_request_confirms_it_and_tells_the_participant(): void
    {
        $row = $this->row('pending');

        $this->service()->approveAsDirector($row->id);

        $this->assertSame('confirmed', $row->refresh()->status->value);
        $this->assertCount(1, $this->participantConfirmed);
        $this->assertFalse($this->participantConfirmed[0]->automatic);
        Notification::assertSentOnDemandTimes(ParticipantConfirmedNotification::class, 1);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function answeredMeanwhile(): array
    {
        return [
            'cancelled' => ['cancelled'],
            'rejected' => ['rejected'],
            'confirmed' => ['confirmed'],
            'waiting list' => ['waiting_list'],
        ];
    }

    #[DataProvider('answeredMeanwhile')]
    public function test_approving_a_registration_that_is_no_longer_pending_is_refused(string $status): void
    {
        $row = $this->row($status);

        try {
            $this->service()->approveAsDirector($row->id);
            $this->fail('Only a pending registration may be approved.');
        } catch (ParticipantNotPendingException $exception) {
            $this->assertSame('not_pending', $exception->reason);
        }

        $this->assertSame($status, $row->refresh()->status->value);
        $this->assertSame([], $this->participantConfirmed);
        Notification::assertNothingSent();
    }

    /**
     * @return array<string, array{string}>
     */
    public static function closedTables(): array
    {
        return ['completed' => ['completed'], 'cancelled' => ['cancelled']];
    }

    // Read under the lock: the table may have ended since the page was loaded
    #[DataProvider('closedTables')]
    public function test_approving_on_a_table_that_has_ended_or_been_cancelled_is_refused(string $status): void
    {
        $row = $this->row('pending');
        $this->table->forceFill(['status' => $status])->save();

        try {
            $this->service()->approveAsDirector($row->id);
            $this->fail('A director never approves on a table that has ended or been cancelled.');
        } catch (TableClosedException $exception) {
            $this->assertSame('table_closed', $exception->reason);
        }

        $this->assertSame('pending', $row->refresh()->status->value);
        $this->assertSame([], $this->participantConfirmed);
        Notification::assertNothingSent();
    }

    #[DataProvider('answeredMeanwhile')]
    public function test_rejecting_a_registration_that_is_no_longer_pending_is_refused(string $status): void
    {
        $row = $this->row($status);

        try {
            $this->service()->rejectAsDirector($row->id, 'Motivo');
            $this->fail('Only a pending registration may be rejected by a director.');
        } catch (ParticipantNotPendingException $exception) {
            $this->assertSame('not_pending', $exception->reason);
        }

        $this->assertSame($status, $row->refresh()->status->value);
        Notification::assertNothingSent();
    }

    public function test_the_panel_still_rejects_from_the_waiting_list(): void
    {
        $row = $this->row('waiting_list');

        $this->service()->reject($row->id);

        $this->assertSame('rejected', $row->refresh()->status->value);
    }

    public function test_rejecting_a_pending_request_sends_the_reason(): void
    {
        $row = $this->row('pending');

        $this->service()->rejectAsDirector($row->id, '  Sin plazas para principiantes  ');

        $this->assertSame('rejected', $row->refresh()->status->value);
        Notification::assertSentOnDemand(
            ParticipantRejectedNotification::class,
            fn (ParticipantRejectedNotification $n): bool => $n->reason === 'Sin plazas para principiantes',
        );
    }

    public function test_approving_a_guest_awaiting_their_email_is_refused(): void
    {
        $guest = $this->guest();

        try {
            $this->service()->approveAsDirector($guest->id);
            $this->fail('A director must never vouch for a guest email.');
        } catch (ParticipantAwaitingEmailException $exception) {
            $this->assertSame('awaiting_email', $exception->reason);
        }

        $guest->refresh();
        $this->assertSame('pending', $guest->status->value);
        $this->assertNull($guest->email_confirmed_at);
        $this->assertSame([], $this->participantConfirmed);
        Notification::assertNothingSent();
    }

    /**
     * @return array<string, array{bool}>
     */
    public static function waitingList(): array
    {
        return ['waiting list off' => [false], 'waiting list on' => [true]];
    }

    #[DataProvider('waitingList')]
    public function test_approving_never_takes_the_table_over_its_player_seats(bool $waitingList): void
    {
        config()->set('modules.settings.game-tables.registration.waiting_list_enabled', $waitingList);
        $this->row('confirmed');
        $row = $this->row('pending');

        try {
            $this->service()->approveAsDirector($row->id);
            $this->fail('The table is full.');
        } catch (TableFullException $exception) {
            $this->assertSame('table_full', $exception->reason);
        }

        $this->assertSame('pending', $row->refresh()->status->value);
        Notification::assertNothingSent();
    }

    #[DataProvider('waitingList')]
    public function test_approving_never_takes_the_table_over_its_spectator_seats(bool $waitingList): void
    {
        config()->set('modules.settings.game-tables.registration.waiting_list_enabled', $waitingList);
        $this->row('confirmed', 'spectator');
        $row = $this->row('pending', 'spectator');

        try {
            $this->service()->approveAsDirector($row->id);
            $this->fail('No spectator seat is free.');
        } catch (TableFullException $exception) {
            $this->assertSame('spectators_full', $exception->reason);
        }

        $this->assertSame('pending', $row->refresh()->status->value);
    }

    public function test_the_panel_confirmation_keeps_its_rule_with_the_waiting_list_off(): void
    {
        // Admins keep every capability: confirm() only checks seats with the waiting list on
        $this->row('confirmed');
        $row = $this->row('pending');

        $this->service()->confirm($row->id);

        $this->assertSame('confirmed', $row->refresh()->status->value);
    }

    private function service(): RegistrationServiceInterface
    {
        return app(RegistrationServiceInterface::class);
    }

    private function row(string $status, string $role = 'player'): ParticipantModel
    {
        return ParticipantModel::create([
            'id' => (string) Str::uuid(),
            'game_table_id' => $this->table->id,
            'user_id' => UserModel::factory()->create()->id,
            'role' => $role,
            'status' => $status,
            'waiting_list_position' => $status === 'waiting_list' ? 1 : null,
            'confirmed_at' => $status === 'confirmed' ? now() : null,
        ]);
    }

    private function guest(): ParticipantModel
    {
        return ParticipantModel::create([
            'id' => (string) Str::uuid(),
            'game_table_id' => $this->table->id,
            'user_id' => null,
            'first_name' => 'Janie',
            'last_name' => 'Roe',
            'email' => 'janie@example.com',
            'role' => 'player',
            'status' => 'pending',
            'email_confirmed_at' => null,
            'confirmation_mode' => 'hold',
            'confirmation_expires_at' => CarbonImmutable::now()->addDay(),
            'confirmation_token' => Str::random(64),
            'cancellation_token' => Str::random(64),
        ]);
    }
}
