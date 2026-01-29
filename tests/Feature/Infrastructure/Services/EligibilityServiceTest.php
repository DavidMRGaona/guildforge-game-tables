<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Feature\Infrastructure\Services;

use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
use Modules\GameTables\Domain\Repositories\GameTableRepositoryInterface;
use Modules\GameTables\Domain\Repositories\ParticipantRepositoryInterface;
use Modules\GameTables\Domain\ValueObjects\GameTableId;
use Modules\GameTables\Domain\ValueObjects\ParticipantId;
use Modules\GameTables\Infrastructure\Services\EligibilityService;
use Tests\TestCase;

final class EligibilityServiceTest extends TestCase
{
    use RefreshDatabase;

    private ParticipantRepositoryInterface $participantRepository;
    private GameTableRepositoryInterface $gameTableRepository;
    private EligibilityService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->participantRepository = $this->createMock(ParticipantRepositoryInterface::class);
        $this->gameTableRepository = $this->createMock(GameTableRepositoryInterface::class);
        $this->service = new EligibilityService(
            $this->participantRepository,
            $this->gameTableRepository,
        );
    }

    public function test_can_guest_register_returns_true_for_open_table(): void
    {
        $gameTableId = GameTableId::generate()->value;
        $email = 'guest@example.com';

        $gameTable = $this->createGameTable(
            id: $gameTableId,
            registrationType: RegistrationType::Everyone,
            status: TableStatus::Scheduled,
            maxPlayers: 5,
        );

        $this->gameTableRepository
            ->expects($this->once())
            ->method('find')
            ->with($this->callback(fn($id) => $id->value === $gameTableId))
            ->willReturn($gameTable);

        $this->participantRepository
            ->expects($this->once())
            ->method('findByTableAndEmail')
            ->with(
                $this->callback(fn($id) => $id->value === $gameTableId),
                $email
            )
            ->willReturn(null);

        $this->participantRepository
            ->expects($this->once())
            ->method('countConfirmedPlayers')
            ->willReturn(2);

        $result = $this->service->canGuestRegister($gameTableId, $email);

        $this->assertTrue($result['eligible']);
        $this->assertNull($result['reason']);
    }

    public function test_can_guest_register_returns_false_for_members_only_table(): void
    {
        $gameTableId = GameTableId::generate()->value;
        $email = 'guest@example.com';

        $gameTable = $this->createGameTable(
            id: $gameTableId,
            registrationType: RegistrationType::MembersOnly,
            status: TableStatus::Scheduled,
        );

        $this->gameTableRepository
            ->expects($this->once())
            ->method('find')
            ->with($this->callback(fn($id) => $id->value === $gameTableId))
            ->willReturn($gameTable);

        $result = $this->service->canGuestRegister($gameTableId, $email);

        $this->assertFalse($result['eligible']);
        $this->assertEquals('guests_not_allowed', $result['reason']);
    }

    public function test_can_guest_register_returns_false_when_already_registered_with_email(): void
    {
        $gameTableId = GameTableId::generate()->value;
        $email = 'existing@example.com';

        $gameTable = $this->createGameTable(
            id: $gameTableId,
            registrationType: RegistrationType::Everyone,
            status: TableStatus::Scheduled,
        );

        $existingParticipant = new Participant(
            id: ParticipantId::generate(),
            gameTableId: new GameTableId($gameTableId),
            userId: null,
            role: ParticipantRole::Player,
            status: ParticipantStatus::Confirmed,
            email: $email,
            firstName: 'Existing',
        );

        $this->gameTableRepository
            ->expects($this->once())
            ->method('find')
            ->willReturn($gameTable);

        $this->participantRepository
            ->expects($this->once())
            ->method('findByTableAndEmail')
            ->with(
                $this->callback(fn($id) => $id->value === $gameTableId),
                $email
            )
            ->willReturn($existingParticipant);

        $result = $this->service->canGuestRegister($gameTableId, $email);

        $this->assertFalse($result['eligible']);
        $this->assertEquals('guest_already_registered', $result['reason']);
    }

    public function test_can_guest_register_returns_false_when_table_is_full(): void
    {
        $gameTableId = GameTableId::generate()->value;
        $email = 'guest@example.com';

        $gameTable = $this->createGameTable(
            id: $gameTableId,
            registrationType: RegistrationType::Everyone,
            status: TableStatus::Scheduled,
            maxPlayers: 4,
        );

        $this->gameTableRepository
            ->expects($this->once())
            ->method('find')
            ->willReturn($gameTable);

        $this->participantRepository
            ->expects($this->once())
            ->method('findByTableAndEmail')
            ->willReturn(null);

        $this->participantRepository
            ->expects($this->once())
            ->method('countConfirmedPlayers')
            ->willReturn(4);

        $result = $this->service->canGuestRegister($gameTableId, $email);

        $this->assertFalse($result['eligible']);
        $this->assertEquals('table_full', $result['reason']);
    }

    public function test_can_guest_register_returns_false_when_registration_closed(): void
    {
        $gameTableId = GameTableId::generate()->value;
        $email = 'guest@example.com';

        $gameTable = $this->createGameTable(
            id: $gameTableId,
            registrationType: RegistrationType::Everyone,
            status: TableStatus::Draft,
        );

        $this->gameTableRepository
            ->expects($this->once())
            ->method('find')
            ->willReturn($gameTable);

        $result = $this->service->canGuestRegister($gameTableId, $email);

        $this->assertFalse($result['eligible']);
        $this->assertEquals('registration_closed', $result['reason']);
    }

    public function test_can_guest_register_returns_false_for_invite_only_table(): void
    {
        $gameTableId = GameTableId::generate()->value;
        $email = 'guest@example.com';

        $gameTable = $this->createGameTable(
            id: $gameTableId,
            registrationType: RegistrationType::Invite,
            status: TableStatus::Scheduled,
        );

        $this->gameTableRepository
            ->expects($this->once())
            ->method('find')
            ->willReturn($gameTable);

        $result = $this->service->canGuestRegister($gameTableId, $email);

        $this->assertFalse($result['eligible']);
        $this->assertEquals('guests_not_allowed', $result['reason']);
    }

    public function test_can_guest_register_returns_false_when_registration_not_yet_open(): void
    {
        $gameTableId = GameTableId::generate()->value;
        $email = 'guest@example.com';

        $futureDate = (new DateTimeImmutable())->modify('+5 days');
        $gameTable = $this->createGameTable(
            id: $gameTableId,
            registrationType: RegistrationType::Everyone,
            status: TableStatus::Scheduled,
            registrationOpensAt: $futureDate,
        );

        $this->gameTableRepository
            ->expects($this->once())
            ->method('find')
            ->willReturn($gameTable);

        $result = $this->service->canGuestRegister($gameTableId, $email);

        $this->assertFalse($result['eligible']);
        $this->assertEquals('registration_not_open', $result['reason']);
    }

    private function createGameTable(
        string $id,
        RegistrationType $registrationType = RegistrationType::Everyone,
        TableStatus $status = TableStatus::Scheduled,
        int $maxPlayers = 6,
        ?DateTimeImmutable $registrationOpensAt = null,
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
            status: $status,
            minPlayers: 2,
            maxPlayers: $maxPlayers,
            registrationType: $registrationType,
            registrationOpensAt: $registrationOpensAt,
            autoConfirm: true,
        );
    }
}
