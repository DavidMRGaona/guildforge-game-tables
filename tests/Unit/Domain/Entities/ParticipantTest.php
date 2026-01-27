<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Unit\Domain\Entities;

use DateTimeImmutable;
use Modules\GameTables\Domain\Entities\Participant;
use Modules\GameTables\Domain\Enums\ParticipantRole;
use Modules\GameTables\Domain\Enums\ParticipantStatus;
use Modules\GameTables\Domain\ValueObjects\GameTableId;
use Modules\GameTables\Domain\ValueObjects\ParticipantId;
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

    public function test_it_can_mark_as_no_show(): void
    {
        $participant = $this->createParticipant(status: ParticipantStatus::Confirmed);

        $participant->markAsNoShow();

        $this->assertEquals(ParticipantStatus::NoShow, $participant->status);
        $this->assertTrue($participant->isNoShow());
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
