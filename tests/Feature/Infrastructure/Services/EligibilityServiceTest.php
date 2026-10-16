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
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\TestCase;

final class EligibilityServiceTest extends TestCase
{
    use RefreshDatabase;

    private ParticipantRepositoryInterface&MockObject $participantRepository;
    private GameTableRepositoryInterface&MockObject $gameTableRepository;
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
        $this->participantRepository->expects($this->never())->method($this->anything());

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
        $this->participantRepository->expects($this->never())->method($this->anything());

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
        $this->participantRepository->expects($this->never())->method($this->anything());

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
        $this->participantRepository->expects($this->never())->method($this->anything());

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

    public function test_a_member_registers_early_while_the_membership_integration_is_enabled(): void
    {
        config()->set('modules.settings.game-tables.membership_integration.enabled', true);
        $this->participantRepository->expects($this->once())->method('findByTableAndUser')->willReturn(null);
        $this->participantRepository->expects($this->once())->method('countConfirmedPlayers')->willReturn(0);

        $result = $this->service->canRegister($this->tableInMembersEarlyAccess(), 'member-1', true, null);

        $this->assertTrue($result['eligible']);
    }

    public function test_a_member_gets_no_early_access_when_the_membership_integration_is_disabled(): void
    {
        config()->set('modules.settings.game-tables.membership_integration.enabled', false);
        $this->participantRepository->expects($this->never())->method('findByTableAndUser');

        $result = $this->service->canRegister($this->tableInMembersEarlyAccess(), 'member-1', true, null);

        $this->assertFalse($result['eligible']);
        $this->assertSame('registration_not_open', $result['reason']);
    }

    /**
     * @return array<string, array{bool, ParticipantRole}>
     */
    public static function inviteOnlyApplicants(): array
    {
        return [
            'member as player' => [true, ParticipantRole::Player],
            'non-member as player' => [false, ParticipantRole::Player],
            'member as spectator' => [true, ParticipantRole::Spectator],
            'non-member as spectator' => [false, ParticipantRole::Spectator],
        ];
    }

    // Nobody signs up by themselves for an invite table: the organisation adds the players
    #[DataProvider('inviteOnlyApplicants')]
    public function test_an_invite_table_refuses_every_user_with_its_window_open(bool $isMember, ParticipantRole $role): void
    {
        $this->gameTableRepository->expects($this->never())->method($this->anything());
        $this->participantRepository->expects($this->never())->method($this->anything());
        $table = $this->createGameTable(id: GameTableId::generate()->value, registrationType: RegistrationType::Invite);

        $result = $this->service->canRegister($table, 'user-1', $isMember, null, null, $role);

        $this->assertFalse($result['eligible']);
        $this->assertSame('invitation_only', $result['reason']);
        $this->assertFalse($result['waitingList']);
    }

    // The refusal comes first: a full table with the waiting list on still says "solo por invitacion"
    public function test_an_invite_table_offers_no_waiting_list(): void
    {
        config()->set('modules.settings.game-tables.registration.waiting_list_enabled', true);
        $this->gameTableRepository->expects($this->never())->method($this->anything());
        $this->participantRepository->expects($this->never())->method($this->anything());
        $table = $this->createGameTable(id: GameTableId::generate()->value, registrationType: RegistrationType::Invite, maxPlayers: 1);

        $result = $this->service->canRegister($table, 'user-1', true, null);

        $this->assertSame('invitation_only', $result['reason']);
        $this->assertFalse($result['waitingList']);
    }

    public function test_registration_is_refused_from_the_start_of_the_table(): void
    {
        $this->gameTableRepository->expects($this->never())->method($this->anything());
        $this->participantRepository->expects($this->never())->method($this->anything());
        $startsAt = new DateTimeImmutable('2030-01-10 18:00:00');
        $table = $this->createGameTable(id: GameTableId::generate()->value, startsAt: $startsAt);

        $result = $this->service->canRegister($table, 'user-1', false, null, $startsAt);

        $this->assertFalse($result['eligible']);
        $this->assertSame('table_started', $result['reason']);
    }

    // Waiting never reopens a table that started or ended, so no opening date is announced for it
    #[AllowMockObjectsWithoutExpectations]
    #[DataProvider('closedForGoodTables')]
    public function test_no_opening_date_is_announced_for_a_table_that_can_never_reopen(
        RegistrationType $type,
        string $startsAt,
        bool $inPlay,
        string $reason,
    ): void {
        $id = GameTableId::generate()->value;
        $table = $this->createGameTable(
            id: $id,
            registrationType: $type,
            registrationOpensAt: new DateTimeImmutable('+1 day'),
            startsAt: new DateTimeImmutable($startsAt),
            acceptsRegistrationsInProgress: $inPlay,
        );
        $this->gameTableRepository->method('find')->willReturn($table);

        $result = $this->service->canRegisterById($id, 'user-1');

        $this->assertFalse($result['eligible']);
        $this->assertSame($reason, $result['reason']);
        $this->assertNull($result['canRegisterAt']);
    }

    /**
     * @return array<string, array{RegistrationType, string, bool, string}>
     */
    public static function closedForGoodTables(): array
    {
        return [
            'invite only' => [RegistrationType::Invite, '+1 week', false, 'invitation_only'],
            'started' => [RegistrationType::Everyone, '-1 hour', false, 'table_started'],
            'ended in play' => [RegistrationType::Everyone, '-1 week', true, 'table_ended'],
        ];
    }

    public function test_registration_is_admitted_until_the_start(): void
    {
        $this->gameTableRepository->expects($this->never())->method($this->anything());
        $this->participantRepository->expects($this->once())->method('findByTableAndUser')->willReturn(null);
        $startsAt = new DateTimeImmutable('2030-01-10 18:00:00');
        $table = $this->createGameTable(id: GameTableId::generate()->value, startsAt: $startsAt);

        $result = $this->service->canRegister($table, 'user-1', false, null, $startsAt->modify('-1 second'));

        $this->assertTrue($result['eligible']);
    }

    public function test_a_table_admitting_registrations_in_play_refuses_them_from_its_end(): void
    {
        $this->gameTableRepository->expects($this->never())->method($this->anything());
        $this->participantRepository->expects($this->never())->method($this->anything());
        $startsAt = new DateTimeImmutable('2030-01-10 18:00:00');
        $table = $this->createGameTable(id: GameTableId::generate()->value, startsAt: $startsAt, acceptsRegistrationsInProgress: true);

        $result = $this->service->canRegister($table, 'user-1', false, null, $startsAt->modify('+240 minutes'));

        $this->assertFalse($result['eligible']);
        $this->assertSame('table_ended', $result['reason']);
    }

    public function test_during_the_session_a_full_table_offers_no_waiting_list(): void
    {
        config()->set('modules.settings.game-tables.registration.waiting_list_enabled', true);
        $this->gameTableRepository->expects($this->never())->method($this->anything());
        $this->participantRepository->expects($this->once())->method('findByTableAndUser')->willReturn(null);
        $startsAt = new DateTimeImmutable('2030-01-10 18:00:00');
        $table = $this->createGameTable(id: GameTableId::generate()->value, startsAt: $startsAt, maxPlayers: 0, acceptsRegistrationsInProgress: true);

        $result = $this->service->canRegister($table, 'user-1', false, null, $startsAt->modify('+1 hour'));

        $this->assertFalse($result['eligible']);
        $this->assertSame('table_full', $result['reason']);
        $this->assertFalse($result['waitingList']);
    }

    public function test_before_the_start_a_full_table_offers_the_waiting_list(): void
    {
        config()->set('modules.settings.game-tables.registration.waiting_list_enabled', true);
        $this->gameTableRepository->expects($this->never())->method($this->anything());
        $this->participantRepository->expects($this->once())->method('findByTableAndUser')->willReturn(null);
        $startsAt = new DateTimeImmutable('2030-01-10 18:00:00');
        $table = $this->createGameTable(id: GameTableId::generate()->value, startsAt: $startsAt, maxPlayers: 0, acceptsRegistrationsInProgress: true);

        $result = $this->service->canRegister($table, 'user-1', false, null, $startsAt->modify('-1 hour'));

        $this->assertTrue($result['eligible']);
        $this->assertTrue($result['waitingList']);
    }

    private function tableInMembersEarlyAccess(): GameTable
    {
        $this->gameTableRepository->expects($this->never())->method('find');

        return $this->createGameTable(
            id: GameTableId::generate()->value,
            registrationOpensAt: new DateTimeImmutable('+1 day'),
            membersEarlyAccessDays: 3,
        );
    }

    private function createGameTable(
        string $id,
        RegistrationType $registrationType = RegistrationType::Everyone,
        TableStatus $status = TableStatus::Scheduled,
        int $maxPlayers = 6,
        ?DateTimeImmutable $registrationOpensAt = null,
        int $membersEarlyAccessDays = 0,
        ?DateTimeImmutable $startsAt = null,
        bool $acceptsRegistrationsInProgress = false,
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
            status: $status,
            minPlayers: 2,
            maxPlayers: $maxPlayers,
            registrationType: $registrationType,
            membersEarlyAccessDays: $membersEarlyAccessDays,
            registrationOpensAt: $registrationOpensAt,
            autoConfirm: true,
            acceptsRegistrationsInProgress: $acceptsRegistrationsInProgress,
            isPublished: true,
        );
    }
}
