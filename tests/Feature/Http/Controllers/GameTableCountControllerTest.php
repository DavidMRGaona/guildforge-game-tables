<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Feature\Http\Controllers;

use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Modules\GameTables\Domain\Entities\GameTable;
use Modules\GameTables\Domain\Enums\RegistrationType;
use Modules\GameTables\Domain\Enums\TableFormat;
use Modules\GameTables\Domain\Enums\TableStatus;
use Modules\GameTables\Domain\Enums\TableType;
use Modules\GameTables\Domain\Repositories\GameTableRepositoryInterface;
use Modules\GameTables\Domain\ValueObjects\GameSystemId;
use Modules\GameTables\Domain\ValueObjects\GameTableId;
use Modules\GameTables\Domain\ValueObjects\TimeSlot;
use Tests\TestCase;

final class GameTableCountControllerTest extends TestCase
{
    use RefreshDatabase;

    private GameTableRepositoryInterface $gameTableRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gameTableRepository = app(GameTableRepositoryInterface::class);
    }

    public function test_it_returns_count_of_published_tables_for_event(): void
    {
        $eventId = (string) Str::uuid();

        // Create 3 published tables linked to the event
        for ($i = 0; $i < 3; $i++) {
            $gameTable = $this->createGameTable(
                status: TableStatus::Published,
                title: "Table {$i}"
            );
            $gameTable->linkToEvent($eventId);
            $this->gameTableRepository->save($gameTable);
        }

        // Create a table for a different event (should not be counted)
        $otherEventId = (string) Str::uuid();
        $otherTable = $this->createGameTable();
        $otherTable->linkToEvent($otherEventId);
        $this->gameTableRepository->save($otherTable);

        $response = $this->getJson(route('gametables.api.count', ['event' => $eventId]));

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/json');
        $response->assertJson(['count' => 3]);
    }

    public function test_it_returns_zero_when_event_has_no_tables(): void
    {
        $eventId = (string) Str::uuid();

        $response = $this->getJson(route('gametables.api.count', ['event' => $eventId]));

        $response->assertStatus(200);
        $response->assertJson(['count' => 0]);
    }

    public function test_it_requires_event_query_parameter(): void
    {
        $response = $this->getJson(route('gametables.api.count'));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['event']);
    }

    public function test_it_requires_valid_uuid_for_event_parameter(): void
    {
        $response = $this->getJson(route('gametables.api.count', ['event' => 'not-a-uuid']));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['event']);
    }

    public function test_it_does_not_count_draft_tables(): void
    {
        $eventId = (string) Str::uuid();

        // Create 2 published tables
        for ($i = 0; $i < 2; $i++) {
            $gameTable = $this->createGameTable(
                status: TableStatus::Published,
                title: "Published Table {$i}"
            );
            $gameTable->linkToEvent($eventId);
            $this->gameTableRepository->save($gameTable);
        }

        // Create 3 draft tables (should not be counted)
        for ($i = 0; $i < 3; $i++) {
            $gameTable = $this->createGameTable(
                status: TableStatus::Draft,
                title: "Draft Table {$i}"
            );
            $gameTable->linkToEvent($eventId);
            $this->gameTableRepository->save($gameTable);
        }

        $response = $this->getJson(route('gametables.api.count', ['event' => $eventId]));

        $response->assertStatus(200);
        $response->assertJson(['count' => 2]);
    }

    public function test_it_only_counts_tables_linked_to_specified_event(): void
    {
        $eventId = (string) Str::uuid();
        $otherEventId = (string) Str::uuid();

        // Create 2 tables for the target event
        for ($i = 0; $i < 2; $i++) {
            $gameTable = $this->createGameTable(title: "Event 1 Table {$i}");
            $gameTable->linkToEvent($eventId);
            $this->gameTableRepository->save($gameTable);
        }

        // Create 5 tables for a different event
        for ($i = 0; $i < 5; $i++) {
            $gameTable = $this->createGameTable(title: "Event 2 Table {$i}");
            $gameTable->linkToEvent($otherEventId);
            $this->gameTableRepository->save($gameTable);
        }

        // Create 1 table not linked to any event
        $unlinkedTable = $this->createGameTable(title: "Unlinked Table");
        $this->gameTableRepository->save($unlinkedTable);

        $response = $this->getJson(route('gametables.api.count', ['event' => $eventId]));

        $response->assertStatus(200);
        $response->assertJson(['count' => 2]);
    }

    public function test_it_counts_tables_with_past_starts_at_when_filtered_by_event(): void
    {
        $eventId = (string) Str::uuid();

        // Table with starts_at in the past (already started)
        $pastTable = $this->createGameTable(
            startsAt: new DateTimeImmutable('-1 hour'),
            title: 'Mesa en curso',
        );
        $pastTable->linkToEvent($eventId);
        $this->gameTableRepository->save($pastTable);

        // Table with starts_at in the future
        $futureTable = $this->createGameTable(title: 'Mesa futura');
        $futureTable->linkToEvent($eventId);
        $this->gameTableRepository->save($futureTable);

        $response = $this->getJson(route('gametables.api.count', ['event' => $eventId]));

        $response->assertStatus(200);
        $response->assertJson(['count' => 2]);
    }

    private function createGameTable(
        ?GameTableId $id = null,
        TableStatus $status = TableStatus::Published,
        string $title = 'Test Game Table',
        ?DateTimeImmutable $startsAt = null,
    ): GameTable {
        $startsAt = $startsAt ?? new DateTimeImmutable('+1 week');
        $timeSlot = new TimeSlot($startsAt, 240);

        return new GameTable(
            id: $id ?? GameTableId::generate(),
            gameSystemId: GameSystemId::generate(),
            createdBy: 'test-user-123',
            title: $title,
            timeSlot: $timeSlot,
            tableType: TableType::OneShot,
            tableFormat: TableFormat::InPerson,
            status: $status,
            minPlayers: 2,
            maxPlayers: 6,
            registrationType: RegistrationType::Everyone,
            autoConfirm: true,
        );
    }
}
