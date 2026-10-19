<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Integration\Infrastructure\Services;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Modules\GameTables\Application\DTOs\RegisterParticipantDTO;
use Modules\GameTables\Application\Services\RegistrationServiceInterface;
use Modules\GameTables\Domain\Enums\ParticipantRole;
use Modules\GameTables\Domain\Exceptions\CannotCancelException;
use Modules\GameTables\Domain\Exceptions\TableFullException;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameSystemModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameTableModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\ParticipantModel;
use Modules\GameTables\Notifications\WaitingListPromotionGmNotification;
use Modules\GameTables\Notifications\WaitingListPromotionNotification;
use Tests\Support\Modules\ModuleTestCase;

/**
 * A freed player seat promotes exactly one waiting player, with the real event listeners running.
 */
final class WaitingListPromotionTest extends ModuleTestCase
{
    protected ?string $moduleName = 'game-tables';

    protected bool $autoEnableModule = true;

    private GameTableModel $table;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        $system = GameSystemModel::create(['id' => (string) Str::uuid(), 'name' => 'Sistema', 'slug' => 'sistema', 'is_active' => true]);
        $this->table = GameTableModel::create([
            'id' => (string) Str::uuid(),
            'game_system_id' => $system->id,
            'created_by' => UserModel::factory()->create()->id,
            'title' => 'Mesa llena',
            'slug' => 'mesa-llena',
            'starts_at' => now()->addWeek(),
            'duration_minutes' => 240,
            'table_type' => 'one_shot',
            'table_format' => 'in_person',
            'status' => 'scheduled',
            'min_players' => 1,
            'max_players' => 2,
            'max_spectators' => 2,
            'language' => 'es',
            'is_published' => true,
        ]);
    }

    public function test_a_cancelled_player_seat_promotes_one_waiting_player(): void
    {
        $leaving = $this->participant('player', 'confirmed');
        $this->participant('player', 'confirmed');
        $first = $this->participant('player', 'waiting_list', 1);
        $second = $this->participant('player', 'waiting_list', 2);

        app(RegistrationServiceInterface::class)->cancel($leaving->id);

        $this->assertSame('confirmed', $first->refresh()->status->value);
        $this->assertSame('waiting_list', $second->refresh()->status->value);
        $this->assertSame(2, $this->confirmedPlayers());
    }

    public function test_a_waiting_spectator_does_not_take_a_freed_player_seat(): void
    {
        $leaving = $this->participant('player', 'confirmed');
        $this->participant('player', 'confirmed');
        $spectator = $this->participant('spectator', 'waiting_list', 1);
        $player = $this->participant('player', 'waiting_list', 2);

        app(RegistrationServiceInterface::class)->cancel($leaving->id);

        $this->assertSame('waiting_list', $spectator->refresh()->status->value);
        $this->assertSame('confirmed', $player->refresh()->status->value);
    }

    public function test_a_cancelled_spectator_promotes_no_waiting_player(): void
    {
        $spectator = $this->participant('spectator', 'confirmed');
        $waiting = $this->participant('player', 'waiting_list', 1);

        app(RegistrationServiceInterface::class)->cancel($spectator->id);

        $this->assertSame('waiting_list', $waiting->refresh()->status->value);
    }

    public function test_a_guest_cancelling_by_link_promotes_one_waiting_player(): void
    {
        $guest = $this->participant('player', 'confirmed', guestToken: 'token-de-prueba');
        $this->participant('player', 'confirmed');
        $first = $this->participant('player', 'waiting_list', 1);
        $second = $this->participant('player', 'waiting_list', 2);

        app(RegistrationServiceInterface::class)->cancelByToken('token-de-prueba');

        $this->assertSame('confirmed', $first->refresh()->status->value);
        $this->assertSame('waiting_list', $second->refresh()->status->value);
        $this->assertNotSame('waiting_list', $guest->refresh()->status->value);
    }

    public function test_two_cancellations_promote_two_waiting_players_in_order(): void
    {
        $firstLeaving = $this->participant('player', 'confirmed');
        $secondLeaving = $this->participant('player', 'confirmed');
        $first = $this->participant('player', 'waiting_list', 1);
        $second = $this->participant('player', 'waiting_list', 2);
        $third = $this->participant('player', 'waiting_list', 3);

        app(RegistrationServiceInterface::class)->cancel($firstLeaving->id);
        app(RegistrationServiceInterface::class)->cancel($secondLeaving->id);

        $this->assertSame('confirmed', $first->refresh()->status->value);
        $this->assertSame('confirmed', $second->refresh()->status->value);
        $this->assertSame('waiting_list', $third->refresh()->status->value);
        $this->assertSame(2, $this->confirmedPlayers());
    }

    public function test_cancelling_the_same_registration_twice_promotes_once(): void
    {
        $leaving = $this->participant('player', 'confirmed');
        $this->participant('player', 'confirmed');
        $first = $this->participant('player', 'waiting_list', 1);
        $second = $this->participant('player', 'waiting_list', 2);

        app(RegistrationServiceInterface::class)->cancel($leaving->id);

        try {
            app(RegistrationServiceInterface::class)->cancel($leaving->id);
            $this->fail('A cancelled registration cannot be cancelled again');
        } catch (CannotCancelException) {
            // Expected
        }

        $this->assertSame('confirmed', $first->refresh()->status->value);
        $this->assertSame('waiting_list', $second->refresh()->status->value);
        $this->assertSame(2, $this->confirmedPlayers());
    }

    // A table already over its seats (players added from the panel) promotes nobody until a seat is free
    public function test_a_promotion_never_exceeds_the_player_seats(): void
    {
        $leaving = $this->participant('player', 'confirmed');
        $this->participant('player', 'confirmed');
        $this->participant('player', 'confirmed');
        $waiting = $this->participant('player', 'waiting_list', 1);

        app(RegistrationServiceInterface::class)->cancel($leaving->id);

        $this->assertSame('waiting_list', $waiting->refresh()->status->value);
        $this->assertSame(2, $this->confirmedPlayers());
    }

    public function test_on_a_table_with_manual_confirmation_the_promoted_player_is_pending(): void
    {
        $this->table->update(['auto_confirm' => false, 'notification_email' => 'mesa@example.test']);
        $leaving = $this->participant('player', 'confirmed');
        $this->participant('player', 'confirmed');
        $first = $this->participant('player', 'waiting_list', 1, email: 'espera@example.test');

        app(RegistrationServiceInterface::class)->cancel($leaving->id);

        $first->refresh();
        $this->assertSame('pending', $first->status->value);
        $this->assertNull($first->waiting_list_position);
        $this->assertNull($first->confirmed_at);
        $this->assertSame(1, $this->confirmedPlayers());

        Notification::assertSentOnDemand(
            WaitingListPromotionNotification::class,
            fn (WaitingListPromotionNotification $notification): bool => $this->mailSays($notification, __('game-tables::emails.waiting_list_promotion.outro_pending')),
        );
        Notification::assertSentOnDemand(
            WaitingListPromotionGmNotification::class,
            fn (WaitingListPromotionGmNotification $notification): bool => $this->mailSays($notification, __('game-tables::emails.waiting_list_promotion_gm.pending')),
        );
    }

    public function test_on_a_table_with_automatic_confirmation_the_promoted_player_is_told_the_seat_is_confirmed(): void
    {
        $this->table->update(['notification_email' => 'mesa@example.test']);
        $leaving = $this->participant('player', 'confirmed');
        $this->participant('player', 'confirmed');
        $this->participant('player', 'waiting_list', 1, email: 'espera@example.test');

        app(RegistrationServiceInterface::class)->cancel($leaving->id);

        Notification::assertSentOnDemand(
            WaitingListPromotionNotification::class,
            fn (WaitingListPromotionNotification $notification): bool => $this->mailSays($notification, __('game-tables::emails.waiting_list_promotion.outro')),
        );
        Notification::assertSentOnDemand(
            WaitingListPromotionGmNotification::class,
            fn (WaitingListPromotionGmNotification $notification): bool => ! $this->mailSays($notification, __('game-tables::emails.waiting_list_promotion_gm.pending')),
        );
    }

    public function test_with_the_promotion_setting_off_only_the_directors_mail_is_skipped(): void
    {
        config()->set('modules.settings.game-tables.notifications.notify_waiting_list_promotion', false);
        $this->table->update(['notification_email' => 'mesa@example.test']);
        $leaving = $this->participant('player', 'confirmed');
        $this->participant('player', 'confirmed');
        $this->participant('player', 'waiting_list', 1, email: 'espera@example.test');

        app(RegistrationServiceInterface::class)->cancel($leaving->id);

        Notification::assertSentOnDemand(WaitingListPromotionNotification::class);
        Notification::assertSentOnDemandTimes(WaitingListPromotionGmNotification::class, 0);
    }

    public function test_after_the_registrations_close_a_cancellation_promotes_nobody(): void
    {
        $this->table->update(['registration_closes_at' => now()->subHour()]);
        $leaving = $this->participant('player', 'confirmed');
        $this->participant('player', 'confirmed');
        $waiting = $this->participant('player', 'waiting_list', 1);

        app(RegistrationServiceInterface::class)->cancel($leaving->id);

        $this->assertSame('cancelled', $leaving->refresh()->status->value);
        $this->assertSame('waiting_list', $waiting->refresh()->status->value);
        $this->assertSame(1, $waiting->waiting_list_position);
    }

    public function test_a_table_that_no_longer_admits_registrations_promotes_nobody(): void
    {
        $this->table->update(['status' => 'in_progress']);
        $leaving = $this->participant('player', 'confirmed');
        $this->participant('player', 'confirmed');
        $waiting = $this->participant('player', 'waiting_list', 1);

        app(RegistrationServiceInterface::class)->cancel($leaving->id);

        $this->assertSame('waiting_list', $waiting->refresh()->status->value);
    }

    public function test_the_director_can_still_promote_by_hand_after_the_registrations_close(): void
    {
        $this->table->update(['registration_closes_at' => now()->subHour()]);
        $waiting = $this->participant('player', 'waiting_list', 1);

        app(RegistrationServiceInterface::class)->promoteParticipant($waiting->id);

        $this->assertSame('confirmed', $waiting->refresh()->status->value);
    }

    // With the waiting list on, a table with manual confirmation counts pending players as taken seats
    public function test_on_a_manual_table_with_the_list_on_a_newcomer_waits_behind_the_list(): void
    {
        $this->manualTableWithTheListOn();
        $leaving = $this->participant('player', 'confirmed');
        $this->participant('player', 'confirmed');
        $first = $this->participant('player', 'waiting_list', 1);
        $second = $this->participant('player', 'waiting_list', 2);

        app(RegistrationServiceInterface::class)->cancel($leaving->id);
        $newcomer = app(RegistrationServiceInterface::class)->register(new RegisterParticipantDTO(
            gameTableId: $this->table->id,
            userId: UserModel::factory()->create()->id,
            role: ParticipantRole::Player,
        ));

        $this->assertSame('pending', $first->refresh()->status->value);
        $this->assertSame('waiting_list', $second->refresh()->status->value);
        $this->assertSame('waiting_list', $newcomer->status->value);
        $this->assertSame(3, $newcomer->waitingListPosition);
    }

    public function test_on_a_manual_table_with_the_list_on_a_cancelled_pending_player_frees_a_seat(): void
    {
        $this->manualTableWithTheListOn();
        $this->participant('player', 'confirmed');
        $pending = $this->participant('player', 'pending');
        $first = $this->participant('player', 'waiting_list', 1);

        app(RegistrationServiceInterface::class)->cancel($pending->id);

        $this->assertSame('pending', $first->refresh()->status->value);
    }

    public function test_with_the_list_off_a_cancelled_pending_player_promotes_nobody(): void
    {
        $this->table->update(['auto_confirm' => false]);
        $this->participant('player', 'confirmed');
        $pending = $this->participant('player', 'pending');
        $waiting = $this->participant('player', 'waiting_list', 1);

        app(RegistrationServiceInterface::class)->cancel($pending->id);

        $this->assertSame('waiting_list', $waiting->refresh()->status->value);
    }

    public function test_with_the_list_on_confirming_never_exceeds_the_player_seats(): void
    {
        $this->manualTableWithTheListOn();
        $this->participant('player', 'confirmed');
        $this->participant('player', 'confirmed');
        $pending = $this->participant('player', 'pending');

        try {
            app(RegistrationServiceInterface::class)->confirm($pending->id);
            $this->fail('A full table cannot confirm another player');
        } catch (TableFullException) {
            // Expected
        }

        $this->assertSame('pending', $pending->refresh()->status->value);
        $this->assertSame(2, $this->confirmedPlayers());
    }

    public function test_with_the_list_on_confirming_a_pending_player_with_a_free_seat_works(): void
    {
        $this->manualTableWithTheListOn();
        $this->participant('player', 'confirmed');
        $pending = $this->participant('player', 'pending');

        app(RegistrationServiceInterface::class)->confirm($pending->id);

        $this->assertSame('confirmed', $pending->refresh()->status->value);
    }

    public function test_with_the_list_off_confirming_keeps_the_previous_behaviour(): void
    {
        $this->table->update(['auto_confirm' => false]);
        $this->participant('player', 'confirmed');
        $this->participant('player', 'confirmed');
        $pending = $this->participant('player', 'pending');

        app(RegistrationServiceInterface::class)->confirm($pending->id);

        $this->assertSame('confirmed', $pending->refresh()->status->value);
    }

    public function test_promoting_by_hand_someone_no_longer_waiting_changes_nothing(): void
    {
        $cancelled = $this->participant('player', 'cancelled');
        $confirmed = $this->participant('player', 'confirmed');

        app(RegistrationServiceInterface::class)->promoteParticipant($cancelled->id);
        app(RegistrationServiceInterface::class)->promoteParticipant($confirmed->id);

        $this->assertSame('cancelled', $cancelled->refresh()->status->value);
        $this->assertSame('confirmed', $confirmed->refresh()->status->value);
        Notification::assertNothingSent();
    }

    public function test_on_a_manual_table_with_the_list_on_rejecting_a_pending_player_promotes_the_first_waiting(): void
    {
        $this->manualTableWithTheListOn();
        $this->participant('player', 'confirmed');
        $pending = $this->participant('player', 'pending');
        $first = $this->participant('player', 'waiting_list', 1, email: 'espera@example.test');
        $second = $this->participant('player', 'waiting_list', 2);

        app(RegistrationServiceInterface::class)->reject($pending->id);

        $this->assertSame('rejected', $pending->refresh()->status->value);
        $this->assertSame('pending', $first->refresh()->status->value);
        $this->assertSame('waiting_list', $second->refresh()->status->value);
        Notification::assertSentOnDemand(WaitingListPromotionNotification::class);
    }

    public function test_with_the_list_off_rejecting_a_pending_player_promotes_nobody(): void
    {
        $this->table->update(['auto_confirm' => false]);
        $this->participant('player', 'confirmed');
        $pending = $this->participant('player', 'pending');
        $waiting = $this->participant('player', 'waiting_list', 1);

        app(RegistrationServiceInterface::class)->reject($pending->id);

        $this->assertSame('rejected', $pending->refresh()->status->value);
        $this->assertSame('waiting_list', $waiting->refresh()->status->value);
    }

    public function test_with_the_list_on_promoting_by_hand_never_exceeds_the_player_seats(): void
    {
        config()->set('modules.settings.game-tables.registration.waiting_list_enabled', true);
        $this->participant('player', 'confirmed');
        $this->participant('player', 'confirmed');
        $waiting = $this->participant('player', 'waiting_list', 1);

        try {
            app(RegistrationServiceInterface::class)->promoteParticipant($waiting->id);
            $this->fail('A full table cannot take another player by hand');
        } catch (TableFullException) {
            // Expected
        }

        $this->assertSame('waiting_list', $waiting->refresh()->status->value);
        $this->assertSame(2, $this->confirmedPlayers());
    }

    public function test_with_the_list_off_promoting_by_hand_keeps_the_previous_behaviour(): void
    {
        $this->participant('player', 'confirmed');
        $this->participant('player', 'confirmed');
        $waiting = $this->participant('player', 'waiting_list', 1);

        app(RegistrationServiceInterface::class)->promoteParticipant($waiting->id);

        $this->assertSame('confirmed', $waiting->refresh()->status->value);
    }

    public function test_after_the_start_a_participant_cannot_cancel(): void
    {
        $this->table->update(['starts_at' => now()->subMinute()]);
        $leaving = $this->participant('player', 'confirmed');
        $waiting = $this->participant('player', 'waiting_list', 1);

        try {
            app(RegistrationServiceInterface::class)->cancel($leaving->id);
            $this->fail('A participant cancelled after the start of the table');
        } catch (CannotCancelException $e) {
            $this->assertSame('table_started', $e->reason);
        }

        $this->assertSame('confirmed', $leaving->refresh()->status->value);
        $this->assertSame('waiting_list', $waiting->refresh()->status->value);
    }

    public function test_after_the_start_the_organisation_cancels_and_promotes_nobody(): void
    {
        $this->table->update(['starts_at' => now()->subMinute()]);
        $leaving = $this->participant('player', 'confirmed');
        $this->participant('player', 'confirmed');
        $waiting = $this->participant('player', 'waiting_list', 1);

        app(RegistrationServiceInterface::class)->cancelByAdmin($leaving->id);

        $this->assertSame('cancelled', $leaving->refresh()->status->value);
        $this->assertSame('waiting_list', $waiting->refresh()->status->value);
        Notification::assertSentTimes(WaitingListPromotionNotification::class, 0);
        $this->assertSame(1, $this->confirmedPlayers());
    }

    // Tables that admit registrations in play close the waiting list at the start as well
    public function test_after_the_start_rejecting_a_pending_player_promotes_nobody(): void
    {
        $this->manualTableWithTheListOn();
        $this->table->update(['starts_at' => now()->subMinute(), 'accepts_registrations_in_progress' => true]);
        $this->participant('player', 'confirmed');
        $pending = $this->participant('player', 'pending');
        $waiting = $this->participant('player', 'waiting_list', 1);

        app(RegistrationServiceInterface::class)->reject($pending->id);

        $this->assertSame('rejected', $pending->refresh()->status->value);
        $this->assertSame('waiting_list', $waiting->refresh()->status->value);
    }

    private function manualTableWithTheListOn(): void
    {
        config()->set('modules.settings.game-tables.registration.waiting_list_enabled', true);
        $this->table->update(['auto_confirm' => false]);
    }

    private function mailSays(WaitingListPromotionNotification|WaitingListPromotionGmNotification $notification, string $line): bool
    {
        $mail = $notification->toMail(new AnonymousNotifiable());

        return in_array($line, [...$mail->introLines, ...$mail->outroLines], true);
    }

    private function confirmedPlayers(): int
    {
        return ParticipantModel::query()
            ->where('game_table_id', $this->table->id)
            ->where('role', 'player')
            ->where('status', 'confirmed')
            ->count();
    }

    private function participant(string $role, string $status, ?int $position = null, ?string $guestToken = null, ?string $email = null): ParticipantModel
    {
        return ParticipantModel::create([
            'id' => (string) Str::uuid(),
            'game_table_id' => $this->table->id,
            'user_id' => $guestToken === null ? UserModel::factory()->create($email === null ? [] : ['email' => $email])->id : null,
            'first_name' => $guestToken === null ? null : 'Invitada',
            'email' => $guestToken === null ? null : 'invitada@example.test',
            'cancellation_token' => $guestToken,
            'role' => $role,
            'status' => $status,
            'waiting_list_position' => $position,
            'confirmed_at' => $status === 'confirmed' ? now() : null,
        ]);
    }
}
