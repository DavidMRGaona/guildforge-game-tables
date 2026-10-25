<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Unit\Domain\Entities;

use DateTimeImmutable;
use Modules\GameTables\Domain\Entities\GameTable;
use Modules\GameTables\Domain\Enums\CharacterCreation;
use Modules\GameTables\Domain\Enums\ExperienceLevel;
use Modules\GameTables\Domain\Enums\FrontendCreationStatus;
use Modules\GameTables\Domain\Enums\Genre;
use Modules\GameTables\Domain\Enums\RegistrationType;
use Modules\GameTables\Domain\Enums\SafetyTool;
use Modules\GameTables\Domain\Enums\TableFormat;
use Modules\GameTables\Domain\Enums\TableStatus;
use Modules\GameTables\Domain\Enums\TableType;
use Modules\GameTables\Domain\Enums\Tone;
use Modules\GameTables\Domain\Exceptions\InvalidStatusTransitionException;
use Modules\GameTables\Domain\ValueObjects\GameSystemId;
use Modules\GameTables\Domain\ValueObjects\GameTableId;
use Modules\GameTables\Domain\ValueObjects\TimeSlot;
use PHPUnit\Framework\Attributes\DataProvider;
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
            slug: 'la-tumba-de-la-aniquilacion',
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
            slug: 'call-of-cthulhu-one-shot',
            timeSlot: $timeSlot,
            tableType: TableType::OneShot,
            tableFormat: TableFormat::Online,
            status: TableStatus::Scheduled,
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

        $this->assertEquals(TableStatus::Scheduled, $gameTable->status);
        $this->assertTrue($gameTable->isPublished);
        $this->assertNotNull($gameTable->publishedAt);
    }

    public function test_it_can_mark_as_full(): void
    {
        $gameTable = $this->createGameTable(status: TableStatus::Scheduled);

        $gameTable->markAsFull();

        $this->assertEquals(TableStatus::Full, $gameTable->status);
    }

    public function test_it_can_start(): void
    {
        $gameTable = $this->createGameTable(status: TableStatus::Scheduled);

        $gameTable->start();

        $this->assertEquals(TableStatus::InProgress, $gameTable->status);
    }

    public function test_it_can_complete(): void
    {
        $gameTable = $this->createGameTable(status: TableStatus::InProgress);

        $gameTable->complete();

        $this->assertEquals(TableStatus::Completed, $gameTable->status);
    }

    public function test_a_full_table_can_start(): void
    {
        $gameTable = $this->createGameTable(status: TableStatus::Full);

        $gameTable->start();

        $this->assertSame(TableStatus::InProgress, $gameTable->status);
    }

    /**
     * @return array<string, array{TableStatus}>
     */
    public static function statusesThatCannotStart(): array
    {
        return [
            'draft' => [TableStatus::Draft],
            'in progress' => [TableStatus::InProgress],
            'completed' => [TableStatus::Completed],
            'cancelled' => [TableStatus::Cancelled],
        ];
    }

    #[DataProvider('statusesThatCannotStart')]
    public function test_only_a_scheduled_or_full_table_starts(TableStatus $status): void
    {
        $gameTable = $this->createGameTable(status: $status);

        try {
            $gameTable->start();
            $this->fail("A {$status->value} table started");
        } catch (InvalidStatusTransitionException $e) {
            $this->assertSame($status->value, $e->from);
            $this->assertSame('in_progress', $e->to);
        }

        $this->assertSame($status, $gameTable->status);
    }

    /**
     * @return array<string, array{TableStatus}>
     */
    public static function statusesThatCannotComplete(): array
    {
        return [
            'draft' => [TableStatus::Draft],
            'scheduled' => [TableStatus::Scheduled],
            'full' => [TableStatus::Full],
            'completed' => [TableStatus::Completed],
            'cancelled' => [TableStatus::Cancelled],
        ];
    }

    #[DataProvider('statusesThatCannotComplete')]
    public function test_only_a_table_in_progress_completes(TableStatus $status): void
    {
        $gameTable = $this->createGameTable(status: $status);

        try {
            $gameTable->complete();
            $this->fail("A {$status->value} table completed");
        } catch (InvalidStatusTransitionException $e) {
            $this->assertSame($status->value, $e->from);
            $this->assertSame('completed', $e->to);
        }

        $this->assertSame($status, $gameTable->status);
    }

    /**
     * @return array<string, array{TableStatus}>
     */
    public static function statusesThatUnpublish(): array
    {
        return [
            'draft' => [TableStatus::Draft],
            'scheduled' => [TableStatus::Scheduled],
            'full' => [TableStatus::Full],
        ];
    }

    #[DataProvider('statusesThatUnpublish')]
    public function test_a_table_that_has_not_started_goes_back_to_draft_when_unpublished(TableStatus $status): void
    {
        $gameTable = $this->publishedTable(status: $status);
        $gameTable->publishedAt = new DateTimeImmutable('2026-01-10 10:00:00');

        $gameTable->unpublish();

        $this->assertSame(TableStatus::Draft, $gameTable->status);
        $this->assertFalse($gameTable->isPublished);
        $this->assertNull($gameTable->publishedAt);
    }

    /**
     * @return array<string, array{TableStatus}>
     */
    public static function statusesThatCannotUnpublish(): array
    {
        return [
            'in progress' => [TableStatus::InProgress],
            'completed' => [TableStatus::Completed],
            'cancelled' => [TableStatus::Cancelled],
        ];
    }

    // Back in draft a table that started could never be completed
    #[DataProvider('statusesThatCannotUnpublish')]
    public function test_a_table_that_started_or_ended_is_not_unpublished(TableStatus $status): void
    {
        $gameTable = $this->publishedTable(status: $status);

        try {
            $gameTable->unpublish();
            $this->fail("A {$status->value} table was unpublished");
        } catch (InvalidStatusTransitionException $e) {
            $this->assertSame($status->value, $e->from);
            $this->assertSame('draft', $e->to);
        }

        $this->assertSame($status, $gameTable->status);
        $this->assertTrue($gameTable->isPublished);
    }

    public function test_it_can_cancel(): void
    {
        $gameTable = $this->createGameTable(status: TableStatus::Scheduled);

        $gameTable->cancel();

        $this->assertEquals(TableStatus::Cancelled, $gameTable->status);
    }

    /**
     * @return array<string, array{TableStatus}>
     */
    public static function finalStatuses(): array
    {
        return [
            'completed' => [TableStatus::Completed],
            'cancelled' => [TableStatus::Cancelled],
        ];
    }

    #[DataProvider('finalStatuses')]
    public function test_a_completed_or_cancelled_table_cannot_be_cancelled(TableStatus $status): void
    {
        $gameTable = $this->createGameTable(status: $status);

        try {
            $gameTable->cancel();
            $this->fail("A {$status->value} table was cancelled");
        } catch (InvalidStatusTransitionException $e) {
            $this->assertSame($status->value, $e->from);
            $this->assertSame('cancelled', $e->to);
        }

        $this->assertSame($status, $gameTable->status);
    }

    #[DataProvider('finalStatuses')]
    public function test_a_completed_or_cancelled_table_never_changes_status(TableStatus $status): void
    {
        $gameTable = $this->createGameTable(status: $status);

        $this->expectException(InvalidStatusTransitionException::class);

        $gameTable->changeStatus(TableStatus::Scheduled);
    }

    public function test_changing_the_status_follows_the_allowed_transitions(): void
    {
        $gameTable = $this->createGameTable(status: TableStatus::Scheduled);

        $gameTable->changeStatus(TableStatus::Full);
        $this->assertSame(TableStatus::Full, $gameTable->status);

        $gameTable->changeStatus(TableStatus::Scheduled);
        $this->assertSame(TableStatus::Scheduled, $gameTable->status);

        // A draft is published, not started
        $draft = $this->createGameTable(status: TableStatus::Draft);
        $this->expectException(InvalidStatusTransitionException::class);
        $draft->changeStatus(TableStatus::InProgress);
    }

    public function test_only_a_scheduled_table_is_marked_as_full(): void
    {
        $gameTable = $this->createGameTable(status: TableStatus::InProgress);

        $this->expectException(InvalidStatusTransitionException::class);

        $gameTable->markAsFull();
    }

    public function test_it_checks_registration_availability_based_on_status(): void
    {
        $scheduledTable = $this->createGameTable(status: TableStatus::Scheduled);
        $this->assertTrue($scheduledTable->status->isRegistrable());

        $fullTable = $this->createGameTable(status: TableStatus::Full);
        $this->assertTrue($fullTable->status->isRegistrable());

        $draftTable = $this->createGameTable(status: TableStatus::Draft);
        $this->assertFalse($draftTable->status->isRegistrable());
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
            slug: 'test-table',
            timeSlot: new TimeSlot(new DateTimeImmutable('2026-02-01 18:00:00'), 240),
            tableType: TableType::OneShot,
            tableFormat: TableFormat::InPerson,
            status: TableStatus::Scheduled,
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

    public function test_the_waiting_list_is_open_while_the_table_admits_registrations(): void
    {
        $table = $this->createGameTable(TableStatus::Scheduled);
        $table->isPublished = true;

        $this->assertTrue($table->isWaitingListOpen(new DateTimeImmutable('2026-01-20 10:00:00')));
    }

    public function test_the_waiting_list_closes_with_the_registration_closing_date(): void
    {
        $table = $this->createGameTable(TableStatus::Scheduled);
        $table->isPublished = true;
        $table->registrationClosesAt = new DateTimeImmutable('2026-01-31 20:00:00');

        $this->assertTrue($table->isWaitingListOpen(new DateTimeImmutable('2026-01-31 20:00:00')));
        $this->assertFalse($table->isWaitingListOpen(new DateTimeImmutable('2026-01-31 20:00:01')));
    }

    public function test_the_waiting_list_closes_when_the_table_stops_admitting_registrations(): void
    {
        $unpublished = $this->createGameTable(TableStatus::Scheduled);
        $inProgress = $this->createGameTable(TableStatus::InProgress);
        $inProgress->isPublished = true;

        $this->assertFalse($unpublished->isWaitingListOpen(new DateTimeImmutable('2026-01-20 10:00:00')));
        $this->assertFalse($inProgress->isWaitingListOpen(new DateTimeImmutable('2026-01-20 10:00:00')));
    }

    // Members registered during the early access may already be waiting before the public opening
    public function test_the_waiting_list_does_not_wait_for_the_public_opening(): void
    {
        $table = $this->createGameTable(TableStatus::Scheduled);
        $table->isPublished = true;
        $table->registrationOpensAt = new DateTimeImmutable('2026-01-30 10:00:00');

        $this->assertTrue($table->isWaitingListOpen(new DateTimeImmutable('2026-01-28 10:00:00')));
    }

    public function test_registrations_close_at_the_start_of_the_table(): void
    {
        $table = $this->publishedTable();

        $this->assertTrue($table->isPublicRegistrationOpen(new DateTimeImmutable('2026-02-01 17:59:59')));
        $this->assertFalse($table->isPublicRegistrationOpen(new DateTimeImmutable('2026-02-01 18:00:00')));
        $this->assertTrue($table->canUserRegister(false, new DateTimeImmutable('2026-02-01 17:59:59')));
        $this->assertFalse($table->canUserRegister(false, new DateTimeImmutable('2026-02-01 18:00:00')));
    }

    public function test_a_closing_date_after_the_start_does_not_keep_registrations_open(): void
    {
        $table = $this->publishedTable();
        $table->registrationClosesAt = new DateTimeImmutable('2026-02-02 10:00:00');

        $this->assertTrue($table->isPastRegistrationDeadline(new DateTimeImmutable('2026-02-01 18:00:00')));
        $this->assertFalse($table->isPublicRegistrationOpen(new DateTimeImmutable('2026-02-01 18:00:00')));
    }

    // Guards the configured date: it keeps closing first, inclusive of its exact second
    public function test_a_closing_date_before_the_start_still_closes_them_first(): void
    {
        $table = $this->publishedTable();
        $table->registrationClosesAt = new DateTimeImmutable('2026-01-31 20:00:00');

        $this->assertTrue($table->isPublicRegistrationOpen(new DateTimeImmutable('2026-01-31 20:00:00')));
        $this->assertFalse($table->isPublicRegistrationOpen(new DateTimeImmutable('2026-01-31 20:00:01')));
    }

    public function test_a_table_admitting_registrations_in_play_closes_them_at_its_end(): void
    {
        $table = $this->publishedTable(inPlay: true);

        $this->assertFalse($table->isPastRegistrationDeadline(new DateTimeImmutable('2026-02-01 18:00:00')));
        $this->assertTrue($table->isPublicRegistrationOpen(new DateTimeImmutable('2026-02-01 21:59:59')));
        $this->assertTrue($table->isPastRegistrationDeadline(new DateTimeImmutable('2026-02-01 22:00:00')));
        $this->assertFalse($table->isPublicRegistrationOpen(new DateTimeImmutable('2026-02-01 22:00:00')));
    }

    public function test_in_play_a_configured_closing_date_still_closes_them_first(): void
    {
        $table = $this->publishedTable(inPlay: true);
        $table->registrationClosesAt = new DateTimeImmutable('2026-02-01 20:00:00');

        $this->assertTrue($table->isPublicRegistrationOpen(new DateTimeImmutable('2026-02-01 20:00:00')));
        $this->assertFalse($table->isPublicRegistrationOpen(new DateTimeImmutable('2026-02-01 20:00:01')));

        $table->registrationClosesAt = new DateTimeImmutable('2026-02-03 10:00:00');

        $this->assertFalse($table->isPublicRegistrationOpen(new DateTimeImmutable('2026-02-01 22:00:00')));
    }

    public function test_the_effective_closing_date_is_the_start_without_a_configured_date(): void
    {
        $table = $this->publishedTable();

        $this->assertEquals(new DateTimeImmutable('2026-02-01 18:00:00'), $table->effectiveRegistrationClosesAt());
    }

    public function test_the_effective_closing_date_is_the_configured_one_when_it_is_before_the_start(): void
    {
        $table = $this->publishedTable();
        $table->registrationClosesAt = new DateTimeImmutable('2026-01-31 20:00:00');

        $this->assertEquals(new DateTimeImmutable('2026-01-31 20:00:00'), $table->effectiveRegistrationClosesAt());
    }

    public function test_the_effective_closing_date_is_the_start_when_the_configured_one_is_after_it(): void
    {
        $table = $this->publishedTable();
        $table->registrationClosesAt = new DateTimeImmutable('2026-02-02 10:00:00');

        $this->assertEquals(new DateTimeImmutable('2026-02-01 18:00:00'), $table->effectiveRegistrationClosesAt());
    }

    public function test_in_play_the_effective_closing_date_is_the_end_or_an_earlier_configured_date(): void
    {
        $table = $this->publishedTable(inPlay: true);

        $this->assertEquals(new DateTimeImmutable('2026-02-01 22:00:00'), $table->effectiveRegistrationClosesAt());

        $table->registrationClosesAt = new DateTimeImmutable('2026-02-03 10:00:00');
        $this->assertEquals(new DateTimeImmutable('2026-02-01 22:00:00'), $table->effectiveRegistrationClosesAt());

        $table->registrationClosesAt = new DateTimeImmutable('2026-02-01 20:00:00');
        $this->assertEquals(new DateTimeImmutable('2026-02-01 20:00:00'), $table->effectiveRegistrationClosesAt());
    }

    /**
     * @return array<string, array{TableStatus, bool}>
     */
    public static function statusesDuringTheSession(): array
    {
        return [
            'scheduled' => [TableStatus::Scheduled, true],
            'full' => [TableStatus::Full, true],
            'in progress' => [TableStatus::InProgress, true],
            'completed' => [TableStatus::Completed, false],
            'cancelled' => [TableStatus::Cancelled, false],
            'draft' => [TableStatus::Draft, false],
        ];
    }

    // In play, the time opens the session to registrations, not the director pressing «Iniciar»
    #[DataProvider('statusesDuringTheSession')]
    public function test_in_play_the_time_decides_whatever_the_manual_status(TableStatus $status, bool $admits): void
    {
        $table = $this->publishedTable(inPlay: true, status: $status);

        $this->assertSame($admits, $table->canUserRegister(false, new DateTimeImmutable('2026-02-01 19:00:00')));
    }

    public function test_a_table_without_registrations_in_play_refuses_them_during_the_session(): void
    {
        $now = new DateTimeImmutable('2026-02-01 19:00:00');

        $this->assertFalse($this->publishedTable()->canUserRegister(false, $now));
        $this->assertFalse($this->publishedTable(status: TableStatus::InProgress)->canUserRegister(false, $now));
    }

    public function test_members_early_access_ends_at_the_start_of_the_table(): void
    {
        $table = $this->publishedTable();
        $table->registrationOpensAt = new DateTimeImmutable('2026-02-05 10:00:00');
        $table->membersEarlyAccessDays = 7;

        $this->assertTrue($table->canUserRegister(true, new DateTimeImmutable('2026-02-01 17:00:00')));
        $this->assertTrue($table->isMemberEarlyAccessActive(new DateTimeImmutable('2026-02-01 18:30:00')));
        $this->assertFalse($table->canUserRegister(true, new DateTimeImmutable('2026-02-01 18:30:00')));
    }

    public function test_the_waiting_list_closes_at_the_start_of_the_table(): void
    {
        $table = $this->publishedTable();

        $this->assertTrue($table->isWaitingListOpen(new DateTimeImmutable('2026-02-01 17:59:59')));
        $this->assertFalse($table->isWaitingListOpen(new DateTimeImmutable('2026-02-01 18:00:00')));
        $this->assertFalse($this->publishedTable(inPlay: true)->isWaitingListOpen(new DateTimeImmutable('2026-02-01 18:00:00')));
    }

    public function test_participants_withdraw_only_before_the_start(): void
    {
        foreach ([false, true] as $inPlay) {
            $table = $this->publishedTable(inPlay: $inPlay);

            $this->assertTrue($table->canParticipantWithdraw(new DateTimeImmutable('2026-02-01 17:59:59')));
            $this->assertFalse($table->canParticipantWithdraw(new DateTimeImmutable('2026-02-01 18:00:00')));
        }
    }

    public function test_roster_enrollment_ignores_the_opening_date(): void
    {
        $table = $this->publishedTable();
        $table->registrationOpensAt = new DateTimeImmutable('2026-01-31 12:00:00');

        $this->assertTrue($table->admitsRosterEnrollment(new DateTimeImmutable('2026-01-30 12:00:00')));
    }

    public function test_roster_enrollment_stops_at_the_effective_closing(): void
    {
        $table = $this->publishedTable();
        $table->registrationClosesAt = new DateTimeImmutable('2026-02-01 18:00:00');

        $this->assertTrue($table->admitsRosterEnrollment(new DateTimeImmutable('2026-02-01 17:59:00')));
        $this->assertFalse($table->admitsRosterEnrollment(new DateTimeImmutable('2026-02-01 18:00:00')));
    }

    public function test_unpublished_or_cancelled_tables_admit_no_roster(): void
    {
        $now = new DateTimeImmutable('2026-01-30 12:00:00');

        $unpublished = $this->createGameTable(TableStatus::Scheduled);
        $unpublished->isPublished = false;
        $cancelled = $this->publishedTable(status: TableStatus::Cancelled);

        $this->assertFalse($unpublished->admitsRosterEnrollment($now));
        $this->assertFalse($cancelled->admitsRosterEnrollment($now));
    }

    // The roster says why a session takes no one, as the table page does
    public function test_the_roster_refusal_says_whether_the_table_started_ended_or_is_closed(): void
    {
        $beforeStart = new DateTimeImmutable('2026-02-01 17:00:00');
        $afterStart = new DateTimeImmutable('2026-02-01 18:30:00');
        $afterEnd = new DateTimeImmutable('2026-02-01 22:30:00');

        $this->assertNull($this->publishedTable()->rosterEnrollmentRefusal($beforeStart));
        $this->assertSame('table_started', $this->publishedTable()->rosterEnrollmentRefusal($afterStart));
        $this->assertSame('table_started', $this->publishedTable(status: TableStatus::InProgress)->rosterEnrollmentRefusal($beforeStart));
        $this->assertSame('table_ended', $this->publishedTable(status: TableStatus::Completed)->rosterEnrollmentRefusal($beforeStart));

        // Admitting registrations in play: open until the end
        $this->assertNull($this->publishedTable(inPlay: true)->rosterEnrollmentRefusal($afterStart));
        $this->assertSame('table_ended', $this->publishedTable(inPlay: true)->rosterEnrollmentRefusal($afterEnd));

        // A closing date before the start, a cancelled or an unpublished table: just closed
        $closedEarly = $this->publishedTable();
        $closedEarly->registrationClosesAt = new DateTimeImmutable('2026-02-01 12:00:00');
        $this->assertSame('registration_closed', $closedEarly->rosterEnrollmentRefusal($beforeStart));
        $this->assertSame('registration_closed', $this->publishedTable(status: TableStatus::Cancelled)->rosterEnrollmentRefusal($afterStart));
        $unpublished = $this->createGameTable(TableStatus::Scheduled);
        $unpublished->isPublished = false;
        $this->assertSame('registration_closed', $unpublished->rosterEnrollmentRefusal($afterStart));
    }

    private function publishedTable(bool $inPlay = false, TableStatus $status = TableStatus::Scheduled): GameTable
    {
        $table = $this->createGameTable($status);
        $table->isPublished = true;
        $table->acceptsRegistrationsInProgress = $inPlay;

        return $table;
    }

    public function test_the_author_may_change_it_only_while_it_is_an_unpublished_draft_or_rejected(): void
    {
        $table = $this->createGameTable();
        $table->frontendCreationStatus = FrontendCreationStatus::Draft;
        $this->assertTrue($table->canBeChangedByAuthor());

        $table->frontendCreationStatus = FrontendCreationStatus::Rejected;
        $this->assertTrue($table->canBeChangedByAuthor());

        $table->frontendCreationStatus = FrontendCreationStatus::PendingReview;
        $this->assertFalse($table->canBeChangedByAuthor());

        $table->frontendCreationStatus = null;
        $this->assertFalse($table->canBeChangedByAuthor());

        // Published from the panel while still a draft: the author no longer changes it
        $table->frontendCreationStatus = FrontendCreationStatus::Draft;
        $table->isPublished = true;
        $this->assertFalse($table->canBeChangedByAuthor());
    }

    // Rejecting sends it back to the author as a draft that keeps the reason, not to a separate status
    public function test_rejecting_returns_it_to_an_unpublished_draft_with_the_reason(): void
    {
        $table = $this->createGameTable();
        $table->frontendCreationStatus = FrontendCreationStatus::PendingReview;
        $table->isPublished = true;

        $table->rejectFrontendCreation('Falta la sinopsis');

        $this->assertSame(FrontendCreationStatus::Draft, $table->frontendCreationStatus);
        $this->assertSame('Falta la sinopsis', $table->moderationNotes);
        $this->assertFalse($table->isPublished);
        $this->assertSame('Falta la sinopsis', $table->rejectionReason());
        $this->assertTrue($table->canBeChangedByAuthor());
    }

    // The reason shows until the author sends it again: submitting clears it
    public function test_submitting_for_review_clears_the_rejection_reason(): void
    {
        $table = $this->createGameTable();
        $table->frontendCreationStatus = FrontendCreationStatus::PendingReview;
        $table->rejectFrontendCreation('Falta la sinopsis');

        $table->submitForModeration();

        $this->assertSame(FrontendCreationStatus::PendingReview, $table->frontendCreationStatus);
        $this->assertNull($table->moderationNotes);
        $this->assertNull($table->rejectionReason());
    }

    public function test_only_a_draft_the_author_may_change_has_a_rejection_reason(): void
    {
        $table = $this->createGameTable();
        $table->moderationNotes = 'Falta la sinopsis';

        // Rejected: rows rejected before 2.10.0, until the deploy converts them
        foreach ([FrontendCreationStatus::Draft, FrontendCreationStatus::Rejected] as $status) {
            $table->frontendCreationStatus = $status;
            $this->assertSame('Falta la sinopsis', $table->rejectionReason(), $status->value);
        }

        // Approval notes are not a rejection
        $table->frontendCreationStatus = FrontendCreationStatus::Approved;
        $this->assertNull($table->rejectionReason());

        $table->frontendCreationStatus = FrontendCreationStatus::Draft;
        $table->moderationNotes = null;
        $this->assertNull($table->rejectionReason());
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
            slug: 'test-table',
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
