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
            status: TableStatus::Published,
            minPlayers: 2,
            maxPlayers: 6,
            registrationType: RegistrationType::Everyone,
            autoConfirm: $autoConfirm,
        );
    }
}
