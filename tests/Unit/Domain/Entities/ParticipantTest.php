<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Unit\Domain\Entities;

use DateTimeImmutable;
use DateTimeZone;
use Modules\GameTables\Domain\Entities\Participant;
use Modules\GameTables\Domain\Enums\GuestConfirmationMode;
use Modules\GameTables\Domain\Enums\ParticipantCancellationReason;
use Modules\GameTables\Domain\Enums\ParticipantRole;
use Modules\GameTables\Domain\Enums\ParticipantStatus;
use Modules\GameTables\Domain\Exceptions\InvalidStatusTransitionException;
use Modules\GameTables\Domain\ValueObjects\GameTableId;
use Modules\GameTables\Domain\ValueObjects\ParticipantId;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ParticipantTest extends TestCase
{
    public function test_it_creates_participant_with_required_data(): void
    {
        $id = ParticipantId::generate();
        $gameTableId = GameTableId::generate();
        $userId = 'user-uuid-123';

        $participant = new Participant(
            id: $id,
            gameTableId: $gameTableId,
            userId: $userId,
            role: ParticipantRole::Player,
            status: ParticipantStatus::Pending,
        );

        $this->assertInstanceOf(Participant::class, $participant);
        $this->assertTrue($id->equals($participant->id));
        $this->assertTrue($gameTableId->equals($participant->gameTableId));
        $this->assertEquals($userId, $participant->userId);
        $this->assertEquals(ParticipantRole::Player, $participant->role);
        $this->assertEquals(ParticipantStatus::Pending, $participant->status);
        $this->assertNull($participant->waitingListPosition);
        $this->assertNull($participant->notes);
        $this->assertNull($participant->confirmedAt);
        $this->assertNull($participant->cancelledAt);
    }

    public function test_it_creates_game_master_participant(): void
    {
        $participant = $this->createParticipant(role: ParticipantRole::GameMaster);

        $this->assertEquals(ParticipantRole::GameMaster, $participant->role);
        $this->assertTrue($participant->isGameMaster());
        $this->assertFalse($participant->isPlayer());
    }

    public function test_it_creates_player_participant(): void
    {
        $participant = $this->createParticipant(role: ParticipantRole::Player);

        $this->assertEquals(ParticipantRole::Player, $participant->role);
        $this->assertTrue($participant->isPlayer());
        $this->assertFalse($participant->isGameMaster());
    }

    public function test_it_creates_spectator_participant(): void
    {
        $participant = $this->createParticipant(role: ParticipantRole::Spectator);

        $this->assertEquals(ParticipantRole::Spectator, $participant->role);
        $this->assertTrue($participant->isSpectator());
        $this->assertFalse($participant->isPlayer());
    }

    public function test_it_can_confirm(): void
    {
        $participant = $this->createParticipant(status: ParticipantStatus::Pending);

        $participant->confirm();

        $this->assertEquals(ParticipantStatus::Confirmed, $participant->status);
        $this->assertNotNull($participant->confirmedAt);
        $this->assertTrue($participant->isConfirmed());
    }

    public function test_it_can_cancel(): void
    {
        $participant = $this->createParticipant(status: ParticipantStatus::Confirmed);

        $participant->cancel();

        $this->assertEquals(ParticipantStatus::Cancelled, $participant->status);
        $this->assertNotNull($participant->cancelledAt);
        $this->assertTrue($participant->isCancelled());
    }

    public function test_it_can_reject(): void
    {
        $participant = $this->createParticipant(status: ParticipantStatus::Pending);

        $participant->reject();

        $this->assertEquals(ParticipantStatus::Rejected, $participant->status);
        $this->assertTrue($participant->isRejected());
    }

    public function test_it_can_add_to_waiting_list(): void
    {
        $participant = $this->createParticipant(status: ParticipantStatus::Pending);

        $participant->addToWaitingList(3);

        $this->assertEquals(ParticipantStatus::WaitingList, $participant->status);
        $this->assertEquals(3, $participant->waitingListPosition);
        $this->assertTrue($participant->isOnWaitingList());
    }

    public function test_it_can_promote_from_waiting_list(): void
    {
        $participant = $this->createParticipant(status: ParticipantStatus::WaitingList);
        $participant->waitingListPosition = 1;

        $participant->promoteFromWaitingList();

        $this->assertEquals(ParticipantStatus::Confirmed, $participant->status);
        $this->assertNull($participant->waitingListPosition);
        $this->assertNotNull($participant->confirmedAt);
    }

    public function test_a_promotion_on_a_table_with_manual_confirmation_leaves_the_participant_pending(): void
    {
        $participant = $this->createParticipant(status: ParticipantStatus::WaitingList);
        $participant->waitingListPosition = 1;

        $participant->promoteFromWaitingList(confirm: false);

        $this->assertEquals(ParticipantStatus::Pending, $participant->status);
        $this->assertNull($participant->waitingListPosition);
        $this->assertNull($participant->confirmedAt);
    }

    public function test_it_can_mark_as_no_show(): void
    {
        $participant = $this->createParticipant(status: ParticipantStatus::Confirmed);

        $participant->markAsNoShow();

        $this->assertEquals(ParticipantStatus::NoShow, $participant->status);
        $this->assertTrue($participant->isNoShow());
    }

    /**
     * @return array<string, array{ParticipantStatus}>
     */
    public static function statusesWithoutAConfirmedSeat(): array
    {
        return [
            'pending' => [ParticipantStatus::Pending],
            'waiting list' => [ParticipantStatus::WaitingList],
            'cancelled' => [ParticipantStatus::Cancelled],
            'rejected' => [ParticipantStatus::Rejected],
            'no show' => [ParticipantStatus::NoShow],
        ];
    }

    // Only someone who held a confirmed seat can fail to show up
    #[DataProvider('statusesWithoutAConfirmedSeat')]
    public function test_only_a_confirmed_participant_can_be_marked_as_no_show(ParticipantStatus $status): void
    {
        $participant = $this->createParticipant(status: $status);

        try {
            $participant->markAsNoShow();
            $this->fail('A participant without a confirmed seat was marked as no-show.');
        } catch (InvalidStatusTransitionException $exception) {
            $this->assertSame($status->value, $exception->from);
            $this->assertSame(ParticipantStatus::NoShow->value, $exception->to);
        }

        $this->assertSame($status, $participant->status);
    }

    public function test_it_checks_if_can_be_cancelled(): void
    {
        $pendingParticipant = $this->createParticipant(status: ParticipantStatus::Pending);
        $confirmedParticipant = $this->createParticipant(status: ParticipantStatus::Confirmed);
        $waitingListParticipant = $this->createParticipant(status: ParticipantStatus::WaitingList);
        $cancelledParticipant = $this->createParticipant(status: ParticipantStatus::Cancelled);

        $this->assertTrue($pendingParticipant->canBeCancelled());
        $this->assertTrue($confirmedParticipant->canBeCancelled());
        $this->assertTrue($waitingListParticipant->canBeCancelled());
        $this->assertFalse($cancelledParticipant->canBeCancelled());
    }

    public function test_it_checks_if_active(): void
    {
        $pendingParticipant = $this->createParticipant(status: ParticipantStatus::Pending);
        $confirmedParticipant = $this->createParticipant(status: ParticipantStatus::Confirmed);
        $waitingListParticipant = $this->createParticipant(status: ParticipantStatus::WaitingList);
        $cancelledParticipant = $this->createParticipant(status: ParticipantStatus::Cancelled);

        $this->assertTrue($pendingParticipant->isActive());
        $this->assertTrue($confirmedParticipant->isActive());
        $this->assertTrue($waitingListParticipant->isActive());
        $this->assertFalse($cancelledParticipant->isActive());
    }

    public function test_it_can_update_notes(): void
    {
        $participant = $this->createParticipant();

        $participant->updateNotes('Player wants to bring their own dice');

        $this->assertEquals('Player wants to bring their own dice', $participant->notes);
    }

    public function test_it_creates_guest_participant_with_null_user_id(): void
    {
        $id = ParticipantId::generate();
        $gameTableId = GameTableId::generate();

        $participant = new Participant(
            id: $id,
            gameTableId: $gameTableId,
            userId: null,
            role: ParticipantRole::Player,
            status: ParticipantStatus::Pending,
            firstName: 'John',
            email: 'john@example.com',
        );

        $this->assertInstanceOf(Participant::class, $participant);
        $this->assertNull($participant->userId);
        $this->assertEquals('John', $participant->firstName);
        $this->assertEquals('john@example.com', $participant->email);
    }

    public function test_guest_participant_is_guest_returns_true(): void
    {
        $participant = new Participant(
            id: ParticipantId::generate(),
            gameTableId: GameTableId::generate(),
            userId: null,
            role: ParticipantRole::Player,
            status: ParticipantStatus::Pending,
            firstName: 'Jane',
            email: 'jane@example.com',
        );

        $this->assertTrue($participant->isGuest());
    }

    public function test_registered_user_participant_is_guest_returns_false(): void
    {
        $participant = $this->createParticipant();

        $this->assertFalse($participant->isGuest());
    }

    public function test_guest_participant_get_display_name_returns_first_name(): void
    {
        $participant = new Participant(
            id: ParticipantId::generate(),
            gameTableId: GameTableId::generate(),
            userId: null,
            role: ParticipantRole::Player,
            status: ParticipantStatus::Pending,
            firstName: 'Alice',
            email: 'alice@example.com',
        );

        $this->assertEquals('Alice', $participant->getDisplayName());
    }

    public function test_guest_participant_get_display_name_returns_full_name_when_last_name_provided(): void
    {
        $participant = new Participant(
            id: ParticipantId::generate(),
            gameTableId: GameTableId::generate(),
            userId: null,
            role: ParticipantRole::Player,
            status: ParticipantStatus::Pending,
            firstName: 'Bob',
            lastName: 'Smith',
            email: 'bob@example.com',
        );

        $this->assertEquals('Bob Smith', $participant->getDisplayName());
    }

    public function test_user_participant_get_display_name_returns_empty_string(): void
    {
        $participant = $this->createParticipant();

        $this->assertEquals('', $participant->getDisplayName());
    }

    public function test_guest_participant_can_set_cancellation_token(): void
    {
        $participant = new Participant(
            id: ParticipantId::generate(),
            gameTableId: GameTableId::generate(),
            userId: null,
            role: ParticipantRole::Player,
            status: ParticipantStatus::Pending,
            firstName: 'Charlie',
            email: 'charlie@example.com',
        );

        $token = 'test-token-123';
        $participant->setCancellationToken($token);

        $this->assertEquals($token, $participant->cancellationToken);
    }

    public function test_require_email_confirmation_leaves_the_email_unconfirmed(): void
    {
        $now = new DateTimeImmutable('2030-01-01 10:00:00');
        $deadline = new DateTimeImmutable('2030-01-01 12:00:00');
        $guest = $this->createGuest();

        $guest->requireEmailConfirmation(GuestConfirmationMode::Hold, 'token-1', $deadline, $now);

        $this->assertTrue($guest->isAwaitingEmailConfirmation());
        $this->assertNull($guest->emailConfirmedAt);
        $this->assertSame('token-1', $guest->confirmationToken);
        $this->assertSame(GuestConfirmationMode::Hold, $guest->confirmationMode);
        $this->assertEquals($deadline, $guest->confirmationExpiresAt);
        $this->assertEquals($now, $guest->confirmationSentAt);
        $this->assertTrue($guest->holdsPlaceWhileUnconfirmed());
    }

    // The token survives so that following the link again finds a confirmed registration
    public function test_confirm_email_clears_the_expiry_and_keeps_the_token(): void
    {
        $now = new DateTimeImmutable('2030-01-01 10:30:00');
        $guest = $this->createGuest();
        $guest->requireEmailConfirmation(
            GuestConfirmationMode::Hold,
            'token-1',
            new DateTimeImmutable('2030-01-01 12:00:00'),
            new DateTimeImmutable('2030-01-01 10:00:00'),
        );

        $guest->confirmEmail($now);

        $this->assertEquals($now, $guest->emailConfirmedAt);
        $this->assertNull($guest->confirmationExpiresAt);
        $this->assertSame('token-1', $guest->confirmationToken);
        $this->assertFalse($guest->isAwaitingEmailConfirmation());
    }

    public function test_a_user_row_is_never_awaiting(): void
    {
        $participant = $this->createParticipant();
        $participant->requireEmailConfirmation(
            GuestConfirmationMode::Hold,
            'token-1',
            new DateTimeImmutable('2030-01-01 12:00:00'),
            new DateTimeImmutable('2030-01-01 10:00:00'),
        );

        $this->assertFalse($participant->isAwaitingEmailConfirmation());
        $this->assertFalse($participant->holdsPlaceWhileUnconfirmed());
        $this->assertFalse($participant->isConfirmationOverdue(new DateTimeImmutable('2031-01-01')));
    }

    public function test_a_cancelled_guest_is_not_awaiting(): void
    {
        $guest = $this->createGuest();
        $guest->requireEmailConfirmation(GuestConfirmationMode::Hold, 'token-1', null, new DateTimeImmutable());
        $guest->cancel();

        $this->assertFalse($guest->isAwaitingEmailConfirmation());
        $this->assertNull($guest->confirmationToken);
    }

    public function test_resend_waits_five_minutes(): void
    {
        $sentAt = new DateTimeImmutable('2030-01-01 10:00:00');
        $guest = $this->createGuest();

        $this->assertTrue($guest->canResendConfirmation($sentAt));

        $guest->requireEmailConfirmation(GuestConfirmationMode::Hold, 'token-1', null, $sentAt);

        $this->assertFalse($guest->canResendConfirmation(new DateTimeImmutable('2030-01-01 10:04:59')));
        $this->assertTrue($guest->canResendConfirmation(new DateTimeImmutable('2030-01-01 10:05:00')));

        $guest->markConfirmationSent(new DateTimeImmutable('2030-01-01 10:06:00'));

        $this->assertFalse($guest->canResendConfirmation(new DateTimeImmutable('2030-01-01 10:10:59')));
        $this->assertTrue($guest->canResendConfirmation(new DateTimeImmutable('2030-01-01 10:11:00')));
    }

    // Sent at 02:58 summer time; at 02:04 winter time, six minutes later, the clock reads earlier
    public function test_resend_waits_five_elapsed_minutes_when_the_clocks_go_back(): void
    {
        $madrid = new DateTimeZone('Europe/Madrid');
        $sentAt = (new DateTimeImmutable('2026-10-25T00:58:00Z'))->setTimezone($madrid);
        $guest = $this->createGuest();
        $guest->requireEmailConfirmation(GuestConfirmationMode::Hold, 'token-1', null, $sentAt);

        $this->assertFalse($guest->canResendConfirmation((new DateTimeImmutable('2026-10-25T01:02:59Z'))->setTimezone($madrid)));
        $this->assertTrue($guest->canResendConfirmation((new DateTimeImmutable('2026-10-25T01:04:00Z'))->setTimezone($madrid)));
    }

    public function test_overdue_only_in_hold_mode_at_or_after_the_deadline(): void
    {
        $deadline = new DateTimeImmutable('2030-01-01 12:00:00');
        $hold = $this->createGuest();
        $hold->requireEmailConfirmation(GuestConfirmationMode::Hold, 'token-1', $deadline, $deadline->modify('-2 hours'));
        $wait = $this->createGuest();
        $wait->requireEmailConfirmation(GuestConfirmationMode::Wait, 'token-2', $deadline, $deadline->modify('-2 hours'));

        $this->assertFalse($hold->isConfirmationOverdue($deadline->modify('-1 second')));
        $this->assertTrue($hold->isConfirmationOverdue($deadline));
        $this->assertFalse($wait->isConfirmationOverdue($deadline));
        $this->assertFalse($wait->holdsPlaceWhileUnconfirmed());
    }

    public function test_expire_frees_the_list_position(): void
    {
        $now = new DateTimeImmutable('2030-01-01 12:00:00');
        $guest = $this->createGuest(ParticipantStatus::WaitingList);
        $guest->addToWaitingList(3);
        $guest->requireEmailConfirmation(GuestConfirmationMode::Wait, 'token-1', $now, $now->modify('-2 hours'));

        $guest->expire($now);

        $this->assertSame(ParticipantStatus::Expired, $guest->status);
        $this->assertNull($guest->waitingListPosition);
        $this->assertEquals($now, $guest->cancelledAt);
        $this->assertNull($guest->confirmationExpiresAt);
        $this->assertNull($guest->confirmationToken);
    }

    public function test_attach_to_account_moves_the_row_to_the_user(): void
    {
        $now = new DateTimeImmutable('2030-01-01 11:00:00');
        $guest = $this->createGuest();
        $guest->setCancellationToken('cancel-token');
        $guest->lastName = 'Pérez';
        $guest->phone = '600000000';
        $guest->recordFingerprint('abc');
        $guest->requireEmailConfirmation(GuestConfirmationMode::Hold, 'token-1', $now->modify('+1 hour'), $now);

        $guest->attachToAccount('user-9', $now);

        $this->assertSame('user-9', $guest->userId);
        $this->assertFalse($guest->isGuest());
        $this->assertNull($guest->email);
        // The account holds the person's data now
        $this->assertNull($guest->firstName);
        $this->assertNull($guest->lastName);
        $this->assertNull($guest->phone);
        $this->assertSame('abc', $guest->fingerprint);
        $this->assertNull($guest->cancellationToken);
        $this->assertNull($guest->confirmationToken);
        $this->assertEquals($now, $guest->emailConfirmedAt);
        $this->assertNull($guest->confirmationExpiresAt);
    }

    public function test_attach_to_account_keeps_an_earlier_email_confirmation_date(): void
    {
        $confirmedAt = new DateTimeImmutable('2030-01-01 09:00:00');
        $guest = $this->createGuest();
        $guest->confirmEmail($confirmedAt);

        $guest->attachToAccount('user-9', new DateTimeImmutable('2030-01-01 11:00:00'));

        $this->assertEquals($confirmedAt, $guest->emailConfirmedAt);
    }

    public function test_reactivate_clears_the_confirmation_fields(): void
    {
        $now = new DateTimeImmutable('2030-01-01 10:00:00');
        $guest = $this->createGuest();
        $guest->requireEmailConfirmation(GuestConfirmationMode::Hold, 'token-1', $now->modify('+2 hours'), $now);
        $guest->confirmEmail($now);
        $guest->recordFingerprint('abc');
        $guest->cancel();

        $guest->reactivate();

        $this->assertNull($guest->emailConfirmedAt);
        $this->assertNull($guest->confirmationExpiresAt);
        $this->assertNull($guest->confirmationToken);
        $this->assertNull($guest->confirmationSentAt);
        $this->assertNull($guest->confirmationMode);
        $this->assertNull($guest->fingerprint);
    }

    public function test_cancel_keeps_the_reason(): void
    {
        $participant = $this->createParticipant(status: ParticipantStatus::Confirmed);

        $participant->cancel(ParticipantCancellationReason::LeftRoster);

        $this->assertSame(ParticipantCancellationReason::LeftRoster, $participant->cancellationReason);
        $this->assertTrue($participant->leftRoster());
        $this->assertFalse($participant->isSessionAbsence());
    }

    public function test_reactivate_clears_the_reason_and_keeps_the_roster_mark(): void
    {
        $participant = $this->createParticipant(status: ParticipantStatus::Confirmed);
        $participant->markFromRoster();
        $participant->cancel(ParticipantCancellationReason::SessionAbsence);

        $participant->reactivate();

        $this->assertNull($participant->cancellationReason);
        $this->assertTrue($participant->fromRoster);
    }

    public function test_unmark_from_roster_drops_the_roster_mark(): void
    {
        $participant = $this->createParticipant(status: ParticipantStatus::Confirmed);
        $participant->markFromRoster();

        $participant->unmarkFromRoster();

        $this->assertFalse($participant->fromRoster);
    }

    public function test_a_cancelled_roster_row_without_reason_counts_as_absence(): void
    {
        $participant = $this->createParticipant(status: ParticipantStatus::Confirmed);
        $participant->markFromRoster();
        $participant->cancel();

        $this->assertTrue($participant->isSessionAbsence());
        $this->assertFalse($participant->leftRoster());
    }

    public function test_a_cancelled_loose_row_without_reason_is_not_an_absence(): void
    {
        $participant = $this->createParticipant(status: ParticipantStatus::Confirmed);
        $participant->cancel();

        $this->assertFalse($participant->isSessionAbsence());
    }

    private function createGuest(ParticipantStatus $status = ParticipantStatus::Pending): Participant
    {
        return new Participant(
            id: ParticipantId::generate(),
            gameTableId: GameTableId::generate(),
            userId: null,
            role: ParticipantRole::Player,
            status: $status,
            firstName: 'Ana',
            email: 'ana@example.com',
        );
    }

    private function createParticipant(
        ParticipantRole $role = ParticipantRole::Player,
        ParticipantStatus $status = ParticipantStatus::Pending,
    ): Participant {
        return new Participant(
            id: ParticipantId::generate(),
            gameTableId: GameTableId::generate(),
            userId: 'user-uuid-123',
            role: $role,
            status: $status,
        );
    }
}
