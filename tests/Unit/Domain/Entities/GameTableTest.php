<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Unit\Domain\Entities;

use DateTimeImmutable;
use Modules\GameTables\Domain\Entities\GameTable;
use Modules\GameTables\Domain\Enums\CharacterCreation;
use Modules\GameTables\Domain\Enums\ExperienceLevel;
use Modules\GameTables\Domain\Enums\Genre;
use Modules\GameTables\Domain\Enums\RegistrationType;
use Modules\GameTables\Domain\Enums\SafetyTool;
use Modules\GameTables\Domain\Enums\TableFormat;
use Modules\GameTables\Domain\Enums\TableStatus;
use Modules\GameTables\Domain\Enums\TableType;
use Modules\GameTables\Domain\Enums\Tone;
use Modules\GameTables\Domain\ValueObjects\GameSystemId;
use Modules\GameTables\Domain\ValueObjects\GameTableId;
use Modules\GameTables\Domain\ValueObjects\TimeSlot;
use PHPUnit\Framework\TestCase;

final class GameTableTest extends TestCase
{
    public function test_it_creates_game_table_with_required_data(): void
    {
        $id = GameTableId::generate();
        $gameSystemId = GameSystemId::generate();
        $timeSlot = new TimeSlot(new DateTimeImmutable('2026-02-01 18:00:00'), 240);

        $gameTable = new GameTable(
            id: $id,
            gameSystemId: $gameSystemId,
            createdBy: 'user-uuid-123',
            title: 'La Tumba de la Aniquilacion',
            timeSlot: $timeSlot,
            tableType: TableType::OneShot,
            tableFormat: TableFormat::InPerson,
            status: TableStatus::Draft,
            minPlayers: 3,
            maxPlayers: 5,
        );

        $this->assertInstanceOf(GameTable::class, $gameTable);
        $this->assertTrue($id->equals($gameTable->id));
        $this->assertTrue($gameSystemId->equals($gameTable->gameSystemId));
        $this->assertEquals('user-uuid-123', $gameTable->createdBy);
        $this->assertEquals('La Tumba de la Aniquilacion', $gameTable->title);
        $this->assertEquals(TableType::OneShot, $gameTable->tableType);
        $this->assertEquals(TableFormat::InPerson, $gameTable->tableFormat);
        $this->assertEquals(TableStatus::Draft, $gameTable->status);
        $this->assertEquals(3, $gameTable->minPlayers);
        $this->assertEquals(5, $gameTable->maxPlayers);
        $this->assertNull($gameTable->campaignId);
        $this->assertNull($gameTable->eventId);
        $this->assertEquals(0, $gameTable->membersEarlyAccessDays);
    }

    public function test_it_creates_game_table_with_all_data(): void
    {
        $id = GameTableId::generate();
        $gameSystemId = GameSystemId::generate();
        $timeSlot = new TimeSlot(new DateTimeImmutable('2026-02-01 18:00:00'), 240);
        $registrationOpensAt = new DateTimeImmutable('2026-01-25 00:00:00');
        $registrationClosesAt = new DateTimeImmutable('2026-02-01 12:00:00');

        $gameTable = new GameTable(
            id: $id,
            gameSystemId: $gameSystemId,
            createdBy: 'user-uuid-123',
            title: 'Call of Cthulhu One-Shot',
            timeSlot: $timeSlot,
            tableType: TableType::OneShot,
            tableFormat: TableFormat::Online,
            status: TableStatus::Published,
            minPlayers: 3,
            maxPlayers: 5,
            maxSpectators: 2,
            synopsis: 'Una investigacion en Arkham',
            location: 'Discord',
            onlineUrl: 'https://discord.gg/xyz',
            minimumAge: 18,
            language: 'es',
            genres: [Genre::Horror, Genre::Mystery],
            tone: Tone::Dark,
            experienceLevel: ExperienceLevel::Beginner,
            characterCreation: CharacterCreation::PreGenerated,
            safetyTools: [SafetyTool::XCard, SafetyTool::LinesAndVeils],
            registrationType: RegistrationType::Everyone,
            membersEarlyAccessDays: 3,
            registrationOpensAt: $registrationOpensAt,
            registrationClosesAt: $registrationClosesAt,
            autoConfirm: true,
            isPublished: true,
        );

        $this->assertEquals('Call of Cthulhu One-Shot', $gameTable->title);
        $this->assertEquals('Una investigacion en Arkham', $gameTable->synopsis);
        $this->assertEquals('Discord', $gameTable->location);
        $this->assertEquals('https://discord.gg/xyz', $gameTable->onlineUrl);
        $this->assertEquals(18, $gameTable->minimumAge);
        $this->assertEquals('es', $gameTable->language);
        $this->assertEquals([Genre::Horror, Genre::Mystery], $gameTable->genres);
        $this->assertEquals(Tone::Dark, $gameTable->tone);
        $this->assertEquals(ExperienceLevel::Beginner, $gameTable->experienceLevel);
        $this->assertEquals(CharacterCreation::PreGenerated, $gameTable->characterCreation);
        $this->assertEquals([SafetyTool::XCard, SafetyTool::LinesAndVeils], $gameTable->safetyTools);
        $this->assertEquals(RegistrationType::Everyone, $gameTable->registrationType);
        $this->assertEquals(3, $gameTable->membersEarlyAccessDays);
        $this->assertEquals($registrationOpensAt, $gameTable->registrationOpensAt);
        $this->assertEquals($registrationClosesAt, $gameTable->registrationClosesAt);
        $this->assertTrue($gameTable->autoConfirm);
        $this->assertTrue($gameTable->isPublished);
    }

    public function test_it_can_publish(): void
    {
        $gameTable = $this->createGameTable(status: TableStatus::Draft);

        $gameTable->publish();

        $this->assertEquals(TableStatus::Published, $gameTable->status);
        $this->assertTrue($gameTable->isPublished);
        $this->assertNotNull($gameTable->publishedAt);
    }

    public function test_it_can_open_registration(): void
    {
        $gameTable = $this->createGameTable(status: TableStatus::Published);

        $gameTable->openRegistration();

        $this->assertEquals(TableStatus::Open, $gameTable->status);
    }

    public function test_it_can_mark_as_full(): void
    {
        $gameTable = $this->createGameTable(status: TableStatus::Open);

        $gameTable->markAsFull();

        $this->assertEquals(TableStatus::Full, $gameTable->status);
    }

    public function test_it_can_start(): void
    {
        $gameTable = $this->createGameTable(status: TableStatus::Open);

        $gameTable->start();

        $this->assertEquals(TableStatus::InProgress, $gameTable->status);
    }

    public function test_it_can_complete(): void
    {
        $gameTable = $this->createGameTable(status: TableStatus::InProgress);

        $gameTable->complete();

        $this->assertEquals(TableStatus::Completed, $gameTable->status);
    }

    public function test_it_can_cancel(): void
    {
        $gameTable = $this->createGameTable(status: TableStatus::Open);

        $gameTable->cancel();

        $this->assertEquals(TableStatus::Cancelled, $gameTable->status);
    }

    public function test_it_checks_registration_availability(): void
    {
        $gameTable = $this->createGameTable(status: TableStatus::Open);

        $this->assertTrue($gameTable->canRegister());

        $fullTable = $this->createGameTable(status: TableStatus::Full);
        $this->assertFalse($fullTable->canRegister());
    }

    public function test_it_checks_if_requires_membership(): void
    {
        $publicTable = $this->createGameTable(registrationType: RegistrationType::Everyone);
        $membersOnlyTable = $this->createGameTable(registrationType: RegistrationType::MembersOnly);

        $this->assertFalse($publicTable->requiresMembership());
        $this->assertTrue($membersOnlyTable->requiresMembership());
    }

    public function test_it_calculates_available_player_slots(): void
    {
        $gameTable = $this->createGameTable(minPlayers: 3, maxPlayers: 5);

        // No confirmed players yet
        $this->assertEquals(5, $gameTable->availablePlayerSlots(0));
        $this->assertEquals(3, $gameTable->availablePlayerSlots(2));
        $this->assertEquals(0, $gameTable->availablePlayerSlots(5));
    }

    public function test_it_checks_member_early_access(): void
    {
        $now = new DateTimeImmutable('2026-01-28 10:00:00');
        $registrationOpensAt = new DateTimeImmutable('2026-01-30 00:00:00');

        $gameTable = new GameTable(
            id: GameTableId::generate(),
            gameSystemId: GameSystemId::generate(),
            createdBy: 'user-uuid-123',
            title: 'Test Table',
            timeSlot: new TimeSlot(new DateTimeImmutable('2026-02-01 18:00:00'), 240),
            tableType: TableType::OneShot,
            tableFormat: TableFormat::InPerson,
            status: TableStatus::Open,
            minPlayers: 3,
            maxPlayers: 5,
            registrationType: RegistrationType::Everyone,
            membersEarlyAccessDays: 3, // Members can register 3 days before
            registrationOpensAt: $registrationOpensAt,
        );

        // Member early access should be active (3 days before = Jan 27)
        $this->assertTrue($gameTable->isMemberEarlyAccessActive($now));

        // Non-members can't register yet (opens Jan 30)
        $this->assertFalse($gameTable->isPublicRegistrationOpen($now));
    }

    private function createGameTable(
        TableStatus $status = TableStatus::Draft,
        RegistrationType $registrationType = RegistrationType::Everyone,
        int $minPlayers = 3,
        int $maxPlayers = 5,
    ): GameTable {
        return new GameTable(
            id: GameTableId::generate(),
            gameSystemId: GameSystemId::generate(),
            createdBy: 'user-uuid-123',
            title: 'Test Table',
            timeSlot: new TimeSlot(new DateTimeImmutable('2026-02-01 18:00:00'), 240),
            tableType: TableType::OneShot,
            tableFormat: TableFormat::InPerson,
            status: $status,
            minPlayers: $minPlayers,
            maxPlayers: $maxPlayers,
            registrationType: $registrationType,
        );
    }
}
