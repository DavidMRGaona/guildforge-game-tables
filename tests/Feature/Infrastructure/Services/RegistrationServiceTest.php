<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Feature\Infrastructure\Services;

use Closure;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use InvalidArgumentException;
use LogicException;
use Modules\GameTables\Application\DTOs\RegisterParticipantDTO;
use Modules\GameTables\Application\Services\EligibilityServiceInterface;
use Modules\GameTables\Application\Services\RegistrationLockInterface;
use Modules\GameTables\Domain\Entities\GameTable;
use Modules\GameTables\Domain\Entities\Participant;
use Modules\GameTables\Domain\Enums\ParticipantCancellationReason;
use Modules\GameTables\Domain\Enums\ParticipantRole;
use Modules\GameTables\Domain\Enums\ParticipantStatus;
use Modules\GameTables\Domain\Enums\RegistrationType;
use Modules\GameTables\Domain\Enums\TableFormat;
use Modules\GameTables\Domain\Enums\TableStatus;
use Modules\GameTables\Domain\Enums\TableType;
use Modules\GameTables\Domain\Events\GuestConfirmationRequested;
use Modules\GameTables\Domain\Events\GuestRegistered;
use Modules\GameTables\Domain\Events\ParticipantCancelled;
use Modules\GameTables\Domain\Events\ParticipantConfirmed;
use Modules\GameTables\Domain\Events\ParticipantRegistered;
use Modules\GameTables\Domain\Exceptions\AlreadyRegisteredException;
use Modules\GameTables\Domain\Exceptions\CannotCancelException;
use Modules\GameTables\Domain\Exceptions\ParticipantNotFoundException;
use Modules\GameTables\Domain\Repositories\GameTableRepositoryInterface;
use Modules\GameTables\Domain\Repositories\ParticipantRepositoryInterface;
use Modules\GameTables\Domain\ValueObjects\GameSystemId;
use Modules\GameTables\Domain\ValueObjects\GameTableId;
use Modules\GameTables\Domain\ValueObjects\ParticipantId;
use Modules\GameTables\Domain\ValueObjects\TimeSlot;
use Modules\GameTables\Infrastructure\Services\ParticipantActorContext;
use Modules\GameTables\Infrastructure\Services\RegistrationService;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\TestCase;

final class RegistrationServiceTest extends TestCase
{
    use RefreshDatabase;

    private ParticipantRepositoryInterface $participantRepository;

    private GameTableRepositoryInterface $gameTableRepository;

    private RegistrationService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->participantRepository = $this->createMock(ParticipantRepositoryInterface::class);
        $this->gameTableRepository = $this->createMock(GameTableRepositoryInterface::class);

        $this->service = new RegistrationService(
            $this->participantRepository,
            $this->gameTableRepository,
            // Eligibility and locking have their own tests; here they let every call through
            $this->createStub(EligibilityServiceInterface::class),
            new class implements RegistrationLockInterface
            {
                public function forTable(string $gameTableId, Closure $callback): mixed
                {
                    return $callback();
                }

                public function forCampaign(string $campaignId, Closure $callback): mixed
                {
                    return $callback();
                }

                public function forParticipant(string $participantId, Closure $callback): mixed
                {
                    return $callback();
                }

                public function forCancellationToken(string $token, Closure $callback): mixed
                {
                    return $callback();
                }
            },
            new ParticipantActorContext(),
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

    // The registration is announced once the guest confirms the email
    #[AllowMockObjectsWithoutExpectations]
    public function test_register_guest_asks_for_the_email_confirmation_instead_of_announcing_it(): void
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

        Event::assertDispatched(GuestConfirmationRequested::class, fn (GuestConfirmationRequested $event): bool => $event->email === 'alice@example.com');
        Event::assertNotDispatched(GuestRegistered::class);
    }

    // Confirmed on an automatic table, but nobody hears of it before the guest confirms the email
    #[AllowMockObjectsWithoutExpectations]
    public function test_register_guest_on_an_auto_confirm_table_takes_the_seat_without_announcing_it(): void
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

        Event::assertNotDispatched(GuestRegistered::class);
        Event::assertNotDispatched(ParticipantConfirmed::class);
        $this->assertEquals(ParticipantStatus::Confirmed->value, $result->status->value);
        $this->assertTrue($result->awaitingEmailConfirmation);
    }

    public function test_register_guest_throws_when_email_already_registered(): void
    {
        $this->gameTableRepository->expects($this->never())->method($this->anything());

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
            // An unconfirmed guest sending the form again is sent the confirmation email again
            emailConfirmedAt: new DateTimeImmutable('-1 day'),
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

        // Read once to check the start before the lock, once more under it
        $this->participantRepository
            ->expects($this->exactly(2))
            ->method('findByCancellationToken')
            ->with($token)
            ->willReturn($participant);

        $this->gameTableRepository
            ->expects($this->once())
            ->method('find')
            ->willReturn($this->createGameTable($participant->gameTableId->value));

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
        $this->gameTableRepository->expects($this->never())->method($this->anything());

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
            ->expects($this->exactly(2))
            ->method('findByCancellationToken')
            ->with($token)
            ->willReturn($participant);

        // Not cancellable anyway: the status error is reported without looking at the start
        $this->gameTableRepository->expects($this->never())->method($this->anything());

        try {
            $this->service->cancelByToken($token);
            $this->fail('A cancelled registration was cancelled again');
        } catch (CannotCancelException $e) {
            $this->assertSame('not_allowed', $e->reason);
        }
    }

    public function test_find_by_token_returns_participant(): void
    {
        $this->gameTableRepository->expects($this->never())->method($this->anything());

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
        $this->gameTableRepository->expects($this->never())->method($this->anything());

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

    #[AllowMockObjectsWithoutExpectations]
    public function test_register_with_auto_confirm_dispatches_automatic_confirmation(): void
    {
        $gameTableId = GameTableId::generate()->value;

        $this->gameTableRepository
            ->method('findOrFail')
            ->willReturn($this->createGameTable($gameTableId, autoConfirm: true));

        $this->service->register(new RegisterParticipantDTO(
            gameTableId: $gameTableId,
            userId: 'user-123',
            role: ParticipantRole::Player,
        ));

        Event::assertDispatched(
            ParticipantRegistered::class,
            fn (ParticipantRegistered $event): bool => $event->status === ParticipantStatus::Confirmed,
        );
        Event::assertDispatched(
            ParticipantConfirmed::class,
            fn (ParticipantConfirmed $event): bool => $event->automatic === true,
        );
    }

    #[AllowMockObjectsWithoutExpectations]
    public function test_register_without_auto_confirm_dispatches_pending_registration_only(): void
    {
        $gameTableId = GameTableId::generate()->value;

        $this->gameTableRepository
            ->method('findOrFail')
            ->willReturn($this->createGameTable($gameTableId, autoConfirm: false));

        $this->service->register(new RegisterParticipantDTO(
            gameTableId: $gameTableId,
            userId: 'user-123',
            role: ParticipantRole::Player,
        ));

        Event::assertDispatched(
            ParticipantRegistered::class,
            fn (ParticipantRegistered $event): bool => $event->status === ParticipantStatus::Pending,
        );
        Event::assertNotDispatched(ParticipantConfirmed::class);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function test_register_on_full_table_dispatches_waiting_list_registration_with_its_place(): void
    {
        $gameTableId = GameTableId::generate()->value;

        $this->gameTableRepository
            ->method('findOrFail')
            ->willReturn($this->createGameTable($gameTableId));

        $this->participantRepository->method('countConfirmedPlayers')->willReturn(6);
        $this->participantRepository->method('getNextWaitingListPosition')->willReturn(4);
        // Someone ahead left the list: stored position 4, second among those waiting
        /** @var MockObject&ParticipantRepositoryInterface $participants */
        $participants = $this->participantRepository;
        $participants->method('getWaitingListRank')->willReturn(2);

        $this->service->register(new RegisterParticipantDTO(
            gameTableId: $gameTableId,
            userId: 'user-123',
            role: ParticipantRole::Player,
        ));

        Event::assertDispatched(
            ParticipantRegistered::class,
            fn (ParticipantRegistered $event): bool => $event->status === ParticipantStatus::WaitingList
                && $event->waitingListPosition === 2,
        );
    }

    // From the panel the email needs no confirmation: the registration is announced at once
    #[AllowMockObjectsWithoutExpectations]
    public function test_register_guest_by_admin_with_auto_confirm_dispatches_automatic_confirmation(): void
    {
        $gameTableId = GameTableId::generate()->value;

        $this->gameTableRepository
            ->method('findOrFail')
            ->willReturn($this->createGameTable($gameTableId, autoConfirm: true));

        $this->service->registerGuestByAdmin(new RegisterParticipantDTO(
            gameTableId: $gameTableId,
            userId: null,
            firstName: 'Bob',
            email: 'bob@example.com',
        ));

        Event::assertDispatched(
            GuestRegistered::class,
            fn (GuestRegistered $event): bool => $event->status === ParticipantStatus::Confirmed,
        );
        Event::assertDispatched(
            ParticipantConfirmed::class,
            fn (ParticipantConfirmed $event): bool => $event->automatic === true,
        );
    }

    public function test_register_without_user_is_rejected(): void
    {
        $this->gameTableRepository->expects($this->never())->method($this->anything());
        $this->participantRepository->expects($this->never())->method($this->anything());

        $this->expectException(InvalidArgumentException::class);

        $this->service->register(new RegisterParticipantDTO(
            gameTableId: GameTableId::generate()->value,
            userId: null,
        ));
    }

    public function test_register_by_admin_without_user_is_rejected(): void
    {
        $this->gameTableRepository->expects($this->never())->method($this->anything());
        $this->participantRepository->expects($this->never())->method($this->anything());

        $this->expectException(InvalidArgumentException::class);

        $this->service->registerByAdmin(new RegisterParticipantDTO(
            gameTableId: GameTableId::generate()->value,
            userId: null,
        ));
    }

    #[AllowMockObjectsWithoutExpectations]
    public function test_manual_confirm_dispatches_non_automatic_confirmation(): void
    {
        $this->gameTableRepository->expects($this->never())->method($this->anything());

        $participant = new Participant(
            id: ParticipantId::generate(),
            gameTableId: GameTableId::generate(),
            userId: 'user-123',
            role: ParticipantRole::Player,
            status: ParticipantStatus::Pending,
        );

        $this->participantRepository->method('findOrFail')->willReturn($participant);

        $this->service->confirm($participant->id->value);

        Event::assertDispatched(
            ParticipantConfirmed::class,
            fn (ParticipantConfirmed $event): bool => $event->automatic === false,
        );
    }

    public function test_cancel_by_token_after_the_start_is_refused_before_taking_the_lock(): void
    {
        $participant = $this->participantWith(ParticipantStatus::Confirmed, token: 'started-token');
        $this->participantRepository->expects($this->once())->method('findByCancellationToken')->with('started-token')->willReturn($participant);
        $this->participantRepository->expects($this->never())->method('save');
        $this->gameTableRepository->expects($this->once())->method('find')->willReturn($this->startedTable($participant));

        $this->assertRefusedBecauseStarted(fn () => $this->serviceWhoseLockMustNotRun()->cancelByToken('started-token'));
        Event::assertNotDispatched(ParticipantCancelled::class);
    }

    public function test_cancel_after_the_start_is_refused_before_taking_the_lock(): void
    {
        $participant = $this->participantWith(ParticipantStatus::Confirmed);
        $this->participantRepository->expects($this->once())->method('find')->willReturn($participant);
        $this->participantRepository->expects($this->never())->method('save');
        $this->gameTableRepository->expects($this->once())->method('find')->willReturn($this->startedTable($participant));

        $this->assertRefusedBecauseStarted(fn () => $this->serviceWhoseLockMustNotRun()->cancel($participant->id->value));
    }

    public function test_cancel_by_user_after_the_start_is_refused_before_taking_the_lock(): void
    {
        $participant = $this->participantWith(ParticipantStatus::WaitingList);
        $this->participantRepository->expects($this->once())->method('findByTableAndUser')->willReturn($participant);
        $this->participantRepository->expects($this->never())->method('save');
        $this->gameTableRepository->expects($this->once())->method('find')->willReturn($this->startedTable($participant));

        $this->assertRefusedBecauseStarted(
            fn () => $this->serviceWhoseLockMustNotRun()->cancelByUser($participant->gameTableId->value, 'user-123'),
        );
    }

    // The panel has no time limit: it never even looks at the table's start
    public function test_the_organisation_cancels_without_looking_at_the_start(): void
    {
        $participant = $this->participantWith(ParticipantStatus::Confirmed);
        $this->participantRepository->expects($this->once())->method('findOrFail')->willReturn($participant);
        $this->gameTableRepository->expects($this->never())->method($this->anything());

        $result = $this->service->cancelByAdmin($participant->id->value);

        $this->assertSame(ParticipantStatus::Cancelled, $result->status);
    }

    /**
     * @return array<string, array{ParticipantStatus, string, string|null}>
     */
    public static function withdrawalCases(): array
    {
        return [
            'confirmed before the start' => [ParticipantStatus::Confirmed, '+1 hour', null],
            'pending before the start' => [ParticipantStatus::Pending, '+1 hour', null],
            'waiting before the start' => [ParticipantStatus::WaitingList, '+1 hour', null],
            'confirmed after the start' => [ParticipantStatus::Confirmed, '-1 minute', 'table_started'],
            'waiting after the start' => [ParticipantStatus::WaitingList, '-1 minute', 'table_started'],
            'cancelled before the start' => [ParticipantStatus::Cancelled, '+1 hour', 'not_allowed'],
            'cancelled after the start' => [ParticipantStatus::Cancelled, '-1 minute', 'not_allowed'],
        ];
    }

    #[DataProvider('withdrawalCases')]
    public function test_withdrawal_refusal(ParticipantStatus $status, string $startsAt, ?string $expected): void
    {
        $participant = $this->participantWith($status);
        $this->participantRepository->expects($this->once())->method('find')->willReturn($participant);
        $this->gameTableRepository
            ->expects($expected === 'not_allowed' ? $this->never() : $this->once())
            ->method('find')
            ->willReturn($this->createGameTable($participant->gameTableId->value, startsAt: new DateTimeImmutable($startsAt)));

        $this->assertSame($expected, $this->service->withdrawalRefusal($participant->id->value));
    }

    public function test_withdrawal_closes_at_the_exact_start(): void
    {
        $participant = $this->participantWith(ParticipantStatus::Confirmed);
        $startsAt = new DateTimeImmutable('2030-01-10 18:00:00');
        $this->participantRepository->expects($this->exactly(2))->method('find')->willReturn($participant);
        $this->gameTableRepository->expects($this->exactly(2))->method('find')
            ->willReturn($this->createGameTable($participant->gameTableId->value, startsAt: $startsAt));

        $this->assertNull($this->service->withdrawalRefusal($participant->id->value, $startsAt->modify('-1 second')));
        $this->assertSame('table_started', $this->service->withdrawalRefusal($participant->id->value, $startsAt));
    }

    public function test_an_unknown_registration_cannot_be_withdrawn(): void
    {
        $this->participantRepository->expects($this->once())->method('find')->willReturn(null);
        $this->gameTableRepository->expects($this->never())->method($this->anything());

        $this->assertSame('not_allowed', $this->service->withdrawalRefusal(ParticipantId::generate()->value));
    }

    #[AllowMockObjectsWithoutExpectations]
    public function test_cancelling_a_roster_row_records_an_absence(): void
    {
        $participant = $this->participantWith(ParticipantStatus::Confirmed);
        $participant->markFromRoster();
        $this->participantRepository->method('findByTableAndUser')->willReturn($participant);
        $this->participantRepository->method('findOrFail')->willReturn($participant);
        $this->gameTableRepository->method('find')
            ->willReturn($this->createGameTable($participant->gameTableId->value));

        $this->service->cancelByUser($participant->gameTableId->value, 'user-123');

        $this->assertSame(ParticipantStatus::Cancelled, $participant->status);
        $this->assertSame(ParticipantCancellationReason::SessionAbsence, $participant->cancellationReason);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function test_cancelling_a_loose_row_records_no_reason(): void
    {
        $participant = $this->participantWith(ParticipantStatus::Confirmed);
        $this->participantRepository->method('findByTableAndUser')->willReturn($participant);
        $this->participantRepository->method('findOrFail')->willReturn($participant);
        $this->gameTableRepository->method('find')
            ->willReturn($this->createGameTable($participant->gameTableId->value));

        $this->service->cancelByUser($participant->gameTableId->value, 'user-123');

        $this->assertSame(ParticipantStatus::Cancelled, $participant->status);
        $this->assertNull($participant->cancellationReason);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function test_a_promoted_roster_row_is_confirmed_on_a_manual_table(): void
    {
        $participant = $this->participantWith(ParticipantStatus::WaitingList);
        $participant->markFromRoster();
        $this->participantRepository->method('getFirstInWaitingList')->willReturn($participant);
        $table = $this->createGameTable($participant->gameTableId->value, autoConfirm: false);
        $table->isPublished = true;
        $this->gameTableRepository->method('find')->willReturn($table);

        $result = $this->service->promoteFromWaitingList($participant->gameTableId->value);

        $this->assertNotNull($result);
        $this->assertSame(ParticipantStatus::Confirmed, $participant->status);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function test_a_promoted_loose_row_stays_pending_on_a_manual_table(): void
    {
        $participant = $this->participantWith(ParticipantStatus::WaitingList);
        $this->participantRepository->method('getFirstInWaitingList')->willReturn($participant);
        $table = $this->createGameTable($participant->gameTableId->value, autoConfirm: false);
        $table->isPublished = true;
        $this->gameTableRepository->method('find')->willReturn($table);

        $this->service->promoteFromWaitingList($participant->gameTableId->value);

        $this->assertSame(ParticipantStatus::Pending, $participant->status);
    }

    private function participantWith(ParticipantStatus $status, ?string $token = null): Participant
    {
        return new Participant(
            id: ParticipantId::generate(),
            gameTableId: GameTableId::generate(),
            userId: $token === null ? 'user-123' : null,
            role: ParticipantRole::Player,
            status: $status,
            waitingListPosition: $status === ParticipantStatus::WaitingList ? 1 : null,
            firstName: $token === null ? null : 'Invitada',
            email: $token === null ? null : 'invitada@example.test',
            cancellationToken: $token,
        );
    }

    private function startedTable(Participant $participant): GameTable
    {
        return $this->createGameTable($participant->gameTableId->value, startsAt: new DateTimeImmutable('-1 minute'));
    }

    /**
     * The same service with a lock that fails if taken: proves the start is checked first.
     */
    private function serviceWhoseLockMustNotRun(): RegistrationService
    {
        return new RegistrationService(
            $this->participantRepository,
            $this->gameTableRepository,
            $this->createStub(EligibilityServiceInterface::class),
            new class implements RegistrationLockInterface
            {
                public function forTable(string $gameTableId, Closure $callback): mixed
                {
                    throw new LogicException('The lock must not be taken');
                }

                public function forCampaign(string $campaignId, Closure $callback): mixed
                {
                    throw new LogicException('The lock must not be taken');
                }

                public function forParticipant(string $participantId, Closure $callback): mixed
                {
                    throw new LogicException('The lock must not be taken');
                }

                public function forCancellationToken(string $token, Closure $callback): mixed
                {
                    throw new LogicException('The lock must not be taken');
                }
            },
            new ParticipantActorContext(),
        );
    }

    private function assertRefusedBecauseStarted(Closure $cancel): void
    {
        try {
            $cancel();
            $this->fail('A participant cancelled after the start of the table');
        } catch (CannotCancelException $e) {
            $this->assertSame('table_started', $e->reason);
        }
    }

    // Ruling C3: an already cancelled registration reports the status error after the start too
    public function test_cancel_by_token_of_a_cancelled_registration_after_the_start_reports_not_allowed(): void
    {
        $participant = $this->participantWith(ParticipantStatus::Cancelled, token: 'gone-token');
        $this->participantRepository->expects($this->exactly(2))->method('findByCancellationToken')->willReturn($participant);
        $this->gameTableRepository->expects($this->never())->method($this->anything());

        try {
            $this->service->cancelByToken('gone-token');
            $this->fail('A cancelled registration was cancelled again');
        } catch (CannotCancelException $e) {
            $this->assertSame('not_allowed', $e->reason);
        }
    }

    public function test_cancel_by_user_of_a_cancelled_registration_after_the_start_reports_not_allowed(): void
    {
        $participant = $this->participantWith(ParticipantStatus::Cancelled);
        $this->participantRepository->expects($this->once())->method('findByTableAndUser')->willReturn($participant);
        $this->participantRepository->expects($this->once())->method('findOrFail')->willReturn($participant);
        $this->gameTableRepository->expects($this->never())->method($this->anything());

        try {
            $this->service->cancelByUser($participant->gameTableId->value, 'user-123');
            $this->fail('A cancelled registration was cancelled again');
        } catch (CannotCancelException $e) {
            $this->assertSame('not_allowed', $e->reason);
        }
    }

    public function test_cancel_of_a_cancelled_registration_after_the_start_reports_not_allowed(): void
    {
        $participant = $this->participantWith(ParticipantStatus::Cancelled);
        $this->participantRepository->expects($this->once())->method('find')->willReturn($participant);
        $this->participantRepository->expects($this->once())->method('findOrFail')->willReturn($participant);
        $this->gameTableRepository->expects($this->never())->method($this->anything());

        try {
            $this->service->cancel($participant->id->value);
            $this->fail('A cancelled registration was cancelled again');
        } catch (CannotCancelException $e) {
            $this->assertSame('not_allowed', $e->reason);
        }
    }

    // A guest without email gets no shortcut: the same eligibility rules apply
    #[DataProvider('tablesRefusingGuests')]
    public function test_register_guest_without_email_still_runs_the_eligibility_check(string $startsAt, RegistrationType $type, string $reason): void
    {
        $id = GameTableId::generate()->value;
        $table = $this->createGameTable($id, startsAt: new DateTimeImmutable($startsAt), registrationType: $type);
        // Published, so the refusal comes from the case under test and not from the visibility
        $table->isPublished = true;
        $this->gameTableRepository->method('find')->willReturn($table);
        $this->gameTableRepository->expects($this->never())->method('findOrFail');
        $this->participantRepository->expects($this->never())->method('save');

        $service = new RegistrationService(
            $this->participantRepository,
            $this->gameTableRepository,
            new \Modules\GameTables\Infrastructure\Services\EligibilityService($this->participantRepository, $this->gameTableRepository),
            new class implements RegistrationLockInterface
            {
                public function forTable(string $gameTableId, Closure $callback): mixed
                {
                    return $callback();
                }

                public function forCampaign(string $campaignId, Closure $callback): mixed
                {
                    return $callback();
                }

                public function forParticipant(string $participantId, Closure $callback): mixed
                {
                    return $callback();
                }

                public function forCancellationToken(string $token, Closure $callback): mixed
                {
                    return $callback();
                }
            },
            new ParticipantActorContext(),
        );

        try {
            $service->registerGuest(new RegisterParticipantDTO(
                gameTableId: $id,
                userId: null,
                role: ParticipantRole::Player,
                firstName: 'John',
                email: null,
            ));
            $this->fail('A guest without email skipped the eligibility check');
        } catch (\Modules\GameTables\Domain\Exceptions\RegistrationNotAllowedException $e) {
            $this->assertSame($reason, $e->reason);
        }
    }

    /**
     * @return array<string, array{string, RegistrationType, string}>
     */
    public static function tablesRefusingGuests(): array
    {
        return [
            'started table' => ['-1 hour', RegistrationType::Everyone, 'table_started'],
            'invite only table' => ['+1 week', RegistrationType::Invite, 'invitation_only'],
        ];
    }

    private function createGameTable(
        string $id,
        bool $autoConfirm = true,
        ?DateTimeImmutable $startsAt = null,
        RegistrationType $registrationType = RegistrationType::Everyone,
    ): GameTable {
        $timeSlot = new TimeSlot($startsAt ?? new DateTimeImmutable('+1 week'), 240);

        return new GameTable(
            id: new GameTableId($id),
            gameSystemId: GameSystemId::generate(),
            createdBy: 'test-user-123',
            title: 'Test Game Table',
            slug: 'test-game-table',
            timeSlot: $timeSlot,
            tableType: TableType::OneShot,
            tableFormat: TableFormat::InPerson,
            status: TableStatus::Scheduled,
            minPlayers: 2,
            maxPlayers: 6,
            registrationType: $registrationType,
            autoConfirm: $autoConfirm,
        );
    }
}
