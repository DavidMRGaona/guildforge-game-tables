<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Feature\Infrastructure\Services;

use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Modules\GameTables\Application\DTOs\RegisterParticipantDTO;
use Modules\GameTables\Application\Services\EligibilityServiceInterface;
use Modules\GameTables\Domain\Entities\GameTable;
use Modules\GameTables\Domain\Entities\Participant;
use Modules\GameTables\Domain\Enums\ParticipantRole;
use Modules\GameTables\Domain\Enums\ParticipantStatus;
use Modules\GameTables\Domain\Enums\RegistrationType;
use Modules\GameTables\Domain\Enums\TableFormat;
use Modules\GameTables\Domain\Enums\TableStatus;
use Modules\GameTables\Domain\Enums\TableType;
use Modules\GameTables\Domain\ValueObjects\GameSystemId;
use Modules\GameTables\Domain\ValueObjects\TimeSlot;
use Modules\GameTables\Domain\Events\GuestRegistered;
use Modules\GameTables\Domain\Events\ParticipantCancelled;
use Modules\GameTables\Domain\Events\ParticipantConfirmed;
use Modules\GameTables\Domain\Exceptions\AlreadyRegisteredException;
use Modules\GameTables\Domain\Exceptions\CannotCancelException;
use Modules\GameTables\Domain\Exceptions\ParticipantNotFoundException;
use Modules\GameTables\Domain\Repositories\GameTableRepositoryInterface;
use Modules\GameTables\Domain\Repositories\ParticipantRepositoryInterface;
use Modules\GameTables\Domain\ValueObjects\GameTableId;
use Modules\GameTables\Domain\ValueObjects\ParticipantId;
use Modules\GameTables\Infrastructure\Services\RegistrationService;
use Tests\TestCase;

final class RegistrationServiceTest extends TestCase
{
    use RefreshDatabase;

    private ParticipantRepositoryInterface $participantRepository;
    private GameTableRepositoryInterface $gameTableRepository;
    private EligibilityServiceInterface $eligibilityService;
    private RegistrationService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->participantRepository = $this->createMock(ParticipantRepositoryInterface::class);
        $this->gameTableRepository = $this->createMock(GameTableRepositoryInterface::class);
        $this->eligibilityService = $this->createMock(EligibilityServiceInterface::class);

        $this->service = new RegistrationService(
            $this->participantRepository,
            $this->gameTableRepository,
            $this->eligibilityService,
        );

        Event::fake();
    }

    public function test_register_guest_creates_participant_with_null_user_id(): void
    {
        $gameTableId = GameTableId::generate()->value;
        $email = 'guest@example.com';

        $gameTable = $this->createGameTable($gameTableId);

        $this->participantRepository
            ->expects($this->once())
            ->method('findByTableAndEmail')
            ->willReturn(null);

        $this->gameTableRepository
            ->expects($this->once())
            ->method('findOrFail')
            ->willReturn($gameTable);

        $this->participantRepository
            ->expects($this->once())
            ->method('countConfirmedPlayers')
            ->willReturn(0);

        $savedParticipant = null;
        $this->participantRepository
            ->expects($this->once())
            ->method('save')
            ->willReturnCallback(function (Participant $participant) use (&$savedParticipant) {
                $savedParticipant = $participant;
            });

        $dto = new RegisterParticipantDTO(
            gameTableId: $gameTableId,
            userId: null,
            role: ParticipantRole::Player,
            firstName: 'John',
            email: $email,
        );

        $result = $this->service->registerGuest($dto);

        $this->assertNotNull($savedParticipant);
        $this->assertNull($savedParticipant->userId);
        $this->assertEquals('John', $savedParticipant->firstName);
        $this->assertEquals($email, $savedParticipant->email);
        $this->assertTrue($result->isGuest);
    }

    public function test_register_guest_generates_cancellation_token(): void
    {
        $gameTableId = GameTableId::generate()->value;
        $gameTable = $this->createGameTable($gameTableId);

        $this->participantRepository
            ->expects($this->once())
            ->method('findByTableAndEmail')
            ->willReturn(null);

        $this->gameTableRepository
            ->expects($this->once())
            ->method('findOrFail')
            ->willReturn($gameTable);

        $this->participantRepository
            ->expects($this->once())
            ->method('countConfirmedPlayers')
            ->willReturn(0);

        $savedParticipant = null;
        $this->participantRepository
            ->expects($this->once())
            ->method('save')
            ->willReturnCallback(function (Participant $participant) use (&$savedParticipant) {
                $savedParticipant = $participant;
            });

        $dto = new RegisterParticipantDTO(
            gameTableId: $gameTableId,
            userId: null,
            firstName: 'Jane',
            email: 'jane@example.com',
        );

        $this->service->registerGuest($dto);

        $this->assertNotNull($savedParticipant);
        $this->assertNotNull($savedParticipant->cancellationToken);
        $this->assertGreaterThan(30, strlen($savedParticipant->cancellationToken));
    }

    public function test_register_guest_dispatches_guest_registered_event(): void
    {
        $gameTableId = GameTableId::generate()->value;
        $gameTable = $this->createGameTable($gameTableId);

        $this->participantRepository
            ->method('findByTableAndEmail')
            ->willReturn(null);

        $this->gameTableRepository
            ->method('findOrFail')
            ->willReturn($gameTable);

        $this->participantRepository
            ->method('countConfirmedPlayers')
            ->willReturn(0);

        $this->participantRepository
            ->method('save');

        $dto = new RegisterParticipantDTO(
            gameTableId: $gameTableId,
            userId: null,
            firstName: 'Alice',
            email: 'alice@example.com',
        );

        $this->service->registerGuest($dto);

        Event::assertDispatched(GuestRegistered::class);
    }

    public function test_register_guest_dispatches_confirmed_event_when_auto_confirm(): void
    {
        $gameTableId = GameTableId::generate()->value;
        $gameTable = $this->createGameTable($gameTableId, autoConfirm: true);

        $this->participantRepository
            ->method('findByTableAndEmail')
            ->willReturn(null);

        $this->gameTableRepository
            ->method('findOrFail')
            ->willReturn($gameTable);

        $this->participantRepository
            ->method('countConfirmedPlayers')
            ->willReturn(0);

        $this->participantRepository
            ->method('save');

        $dto = new RegisterParticipantDTO(
            gameTableId: $gameTableId,
            userId: null,
            firstName: 'Bob',
            email: 'bob@example.com',
        );

        $result = $this->service->registerGuest($dto);

        Event::assertDispatched(GuestRegistered::class);
        Event::assertDispatched(ParticipantConfirmed::class);
        $this->assertEquals(ParticipantStatus::Confirmed->value, $result->status->value);
    }

    public function test_register_guest_throws_when_email_already_registered(): void
    {
        $gameTableId = GameTableId::generate()->value;
        $email = 'duplicate@example.com';

        $existingParticipant = new Participant(
            id: ParticipantId::generate(),
            gameTableId: new GameTableId($gameTableId),
            userId: null,
            role: ParticipantRole::Player,
            status: ParticipantStatus::Confirmed,
            email: $email,
            firstName: 'Existing',
        );

        $this->participantRepository
            ->expects($this->once())
            ->method('findByTableAndEmail')
            ->willReturn($existingParticipant);

        $dto = new RegisterParticipantDTO(
            gameTableId: $gameTableId,
            userId: null,
            firstName: 'New',
            email: $email,
        );

        $this->expectException(AlreadyRegisteredException::class);

        $this->service->registerGuest($dto);
    }

    public function test_cancel_by_token_cancels_participant(): void
    {
        $token = 'valid-token-123';
        $participant = new Participant(
            id: ParticipantId::generate(),
            gameTableId: GameTableId::generate(),
            userId: null,
            role: ParticipantRole::Player,
            status: ParticipantStatus::Confirmed,
            email: 'test@example.com',
            firstName: 'Test',
            cancellationToken: $token,
        );

        $this->participantRepository
            ->expects($this->once())
            ->method('findByCancellationToken')
            ->with($token)
            ->willReturn($participant);

        $this->participantRepository
            ->expects($this->once())
            ->method('save')
            ->with($this->callback(function (Participant $p) {
                return $p->status === ParticipantStatus::Cancelled
                    && $p->cancelledAt !== null;
            }));

        $result = $this->service->cancelByToken($token);

        $this->assertEquals(ParticipantStatus::Cancelled->value, $result->status->value);
        Event::assertDispatched(ParticipantCancelled::class);
    }

    public function test_cancel_by_token_throws_when_token_not_found(): void
    {
        $token = 'invalid-token';

        $this->participantRepository
            ->expects($this->once())
            ->method('findByCancellationToken')
            ->with($token)
            ->willReturn(null);

        $this->expectException(ParticipantNotFoundException::class);

        $this->service->cancelByToken($token);
    }

    public function test_cancel_by_token_throws_when_cannot_cancel(): void
    {
        $token = 'valid-token-123';
        $participant = new Participant(
            id: ParticipantId::generate(),
            gameTableId: GameTableId::generate(),
            userId: null,
            role: ParticipantRole::Player,
            status: ParticipantStatus::Cancelled,
            email: 'test@example.com',
            firstName: 'Test',
            cancellationToken: $token,
        );

        $this->participantRepository
            ->expects($this->once())
            ->method('findByCancellationToken')
            ->with($token)
            ->willReturn($participant);

        $this->expectException(CannotCancelException::class);

        $this->service->cancelByToken($token);
    }

    public function test_find_by_token_returns_participant(): void
    {
        $token = 'valid-token-123';
        $participant = new Participant(
            id: ParticipantId::generate(),
            gameTableId: GameTableId::generate(),
            userId: null,
            role: ParticipantRole::Player,
            status: ParticipantStatus::Confirmed,
            email: 'test@example.com',
            firstName: 'Test',
            cancellationToken: $token,
        );

        $this->participantRepository
            ->expects($this->once())
            ->method('findByCancellationToken')
            ->with($token)
            ->willReturn($participant);

        $result = $this->service->findByToken($token);

        $this->assertNotNull($result);
        $this->assertEquals($participant->id->value, $result->id);
        $this->assertTrue($result->isGuest);
    }

    public function test_find_by_token_returns_null_when_not_found(): void
    {
        $token = 'invalid-token';

        $this->participantRepository
            ->expects($this->once())
            ->method('findByCancellationToken')
            ->with($token)
            ->willReturn(null);

        $result = $this->service->findByToken($token);

        $this->assertNull($result);
    }

    public function test_user_can_reregister_after_cancellation(): void
    {
        $gameTableId = GameTableId::generate()->value;
        $userId = 'user-123';

        $gameTable = $this->createGameTable($gameTableId);

        // Create a cancelled participant
        $cancelledParticipant = new Participant(
            id: ParticipantId::generate(),
            gameTableId: new GameTableId($gameTableId),
            userId: $userId,
            role: ParticipantRole::Player,
            status: ParticipantStatus::Cancelled,
            cancelledAt: new DateTimeImmutable('-1 hour'),
        );

        $this->participantRepository
            ->expects($this->once())
            ->method('findByTableAndUser')
            ->with(
                $this->callback(fn (GameTableId $id) => $id->value === $gameTableId),
                $userId,
            )
            ->willReturn($cancelledParticipant);

        $this->gameTableRepository
            ->expects($this->once())
            ->method('findOrFail')
            ->willReturn($gameTable);

        $this->participantRepository
            ->expects($this->once())
            ->method('countConfirmedPlayers')
            ->willReturn(0);

        $savedParticipant = null;
        $this->participantRepository
            ->expects($this->once())
            ->method('save')
            ->willReturnCallback(function (Participant $participant) use (&$savedParticipant) {
                $savedParticipant = $participant;
            });

        $dto = new RegisterParticipantDTO(
            gameTableId: $gameTableId,
            userId: $userId,
            role: ParticipantRole::Player,
            notes: 'Re-registered',
        );

        $result = $this->service->register($dto);

        // Assert: reused the same participant ID (not a new one)
        $this->assertNotNull($savedParticipant);
        $this->assertEquals($cancelledParticipant->id->value, $savedParticipant->id->value);
        $this->assertEquals($cancelledParticipant->id->value, $result->id);

        // Assert: status is now active (confirmed due to autoConfirm)
        $this->assertEquals(ParticipantStatus::Confirmed, $savedParticipant->status);

        // Assert: cancellation data cleared
        $this->assertNull($savedParticipant->cancelledAt);

        // Assert: notes updated
        $this->assertEquals('Re-registered', $savedParticipant->notes);
    }

    public function test_user_reregistration_goes_to_waiting_list_when_full(): void
    {
        $gameTableId = GameTableId::generate()->value;
        $userId = 'user-123';

        $gameTable = $this->createGameTable($gameTableId, autoConfirm: true);

        $cancelledParticipant = new Participant(
            id: ParticipantId::generate(),
            gameTableId: new GameTableId($gameTableId),
            userId: $userId,
            role: ParticipantRole::Player,
            status: ParticipantStatus::Cancelled,
            cancelledAt: new DateTimeImmutable('-1 hour'),
        );

        $this->participantRepository
            ->expects($this->once())
            ->method('findByTableAndUser')
            ->willReturn($cancelledParticipant);

        $this->gameTableRepository
            ->expects($this->once())
            ->method('findOrFail')
            ->willReturn($gameTable);

        // Table is full (6 players = maxPlayers)
        $this->participantRepository
            ->expects($this->once())
            ->method('countConfirmedPlayers')
            ->willReturn(6);

        $this->participantRepository
            ->expects($this->once())
            ->method('getNextWaitingListPosition')
            ->willReturn(1);

        $savedParticipant = null;
        $this->participantRepository
            ->expects($this->once())
            ->method('save')
            ->willReturnCallback(function (Participant $participant) use (&$savedParticipant) {
                $savedParticipant = $participant;
            });

        $dto = new RegisterParticipantDTO(
            gameTableId: $gameTableId,
            userId: $userId,
            role: ParticipantRole::Player,
        );

        $result = $this->service->register($dto);

        $this->assertNotNull($savedParticipant);
        $this->assertEquals(ParticipantStatus::WaitingList, $savedParticipant->status);
        $this->assertEquals(1, $savedParticipant->waitingListPosition);
    }

    public function test_guest_can_reregister_after_cancellation(): void
    {
        $gameTableId = GameTableId::generate()->value;
        $email = 'guest@example.com';

        $gameTable = $this->createGameTable($gameTableId);

        $cancelledParticipant = new Participant(
            id: ParticipantId::generate(),
            gameTableId: new GameTableId($gameTableId),
            userId: null,
            role: ParticipantRole::Player,
            status: ParticipantStatus::Cancelled,
            email: $email,
            firstName: 'Old Name',
            cancellationToken: 'old-token',
            cancelledAt: new DateTimeImmutable('-1 hour'),
        );

        $this->participantRepository
            ->expects($this->once())
            ->method('findByTableAndEmail')
            ->with(
                $this->callback(fn (GameTableId $id) => $id->value === $gameTableId),
                $email,
            )
            ->willReturn($cancelledParticipant);

        $this->gameTableRepository
            ->expects($this->once())
            ->method('findOrFail')
            ->willReturn($gameTable);

        $this->participantRepository
            ->expects($this->once())
            ->method('countConfirmedPlayers')
            ->willReturn(0);

        $savedParticipant = null;
        $this->participantRepository
            ->expects($this->once())
            ->method('save')
            ->willReturnCallback(function (Participant $participant) use (&$savedParticipant) {
                $savedParticipant = $participant;
            });

        $dto = new RegisterParticipantDTO(
            gameTableId: $gameTableId,
            userId: null,
            role: ParticipantRole::Player,
            firstName: 'New Name',
            email: $email,
        );

        $result = $this->service->registerGuest($dto);

        // Assert: reused the same participant ID
        $this->assertNotNull($savedParticipant);
        $this->assertEquals($cancelledParticipant->id->value, $savedParticipant->id->value);
        $this->assertEquals($cancelledParticipant->id->value, $result->id);

        // Assert: status is now active
        $this->assertEquals(ParticipantStatus::Confirmed, $savedParticipant->status);

        // Assert: cancellation data cleared
        $this->assertNull($savedParticipant->cancelledAt);

        // Assert: name updated
        $this->assertEquals('New Name', $savedParticipant->firstName);

        // Assert: new cancellation token generated
        $this->assertNotNull($savedParticipant->cancellationToken);
        $this->assertNotEquals('old-token', $savedParticipant->cancellationToken);
    }

    private function createGameTable(
        string $id,
        bool $autoConfirm = true,
    ): GameTable {
        $startsAt = new DateTimeImmutable('+1 week');
        $timeSlot = new TimeSlot($startsAt, 240);

        return new GameTable(
            id: new GameTableId($id),
            gameSystemId: GameSystemId::generate(),
            createdBy: 'test-user-123',
            title: 'Test Game Table',
            timeSlot: $timeSlot,
            tableType: TableType::OneShot,
            tableFormat: TableFormat::InPerson,
            status: TableStatus::Scheduled,
            minPlayers: 2,
            maxPlayers: 6,
            registrationType: RegistrationType::Everyone,
            autoConfirm: $autoConfirm,
        );
    }
}
