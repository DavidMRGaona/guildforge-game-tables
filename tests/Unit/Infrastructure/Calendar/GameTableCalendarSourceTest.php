<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Unit\Infrastructure\Calendar;

use App\Application\Calendar\DTOs\CalendarEntryDTO;
use App\Domain\Calendar\Enums\CalendarSourceColor;
use DateTimeImmutable;
use Modules\GameTables\Application\DTOs\GameTableListDTO;
use Modules\GameTables\Application\Services\GameTableQueryServiceInterface;
use Modules\GameTables\Domain\Enums\TableFormat;
use Modules\GameTables\Domain\Enums\TableStatus;
use Modules\GameTables\Domain\Enums\TableType;
use Modules\GameTables\Infrastructure\Calendar\GameTableCalendarSource;
use Tests\TestCase;

final class GameTableCalendarSourceTest extends TestCase
{
    public function test_source_type_is_game_table(): void
    {
        $source = new GameTableCalendarSource($this->createStub(GameTableQueryServiceInterface::class));

        $this->assertSame('game-table', $source->sourceType());
    }

    public function test_color_is_accent(): void
    {
        $source = new GameTableCalendarSource($this->createStub(GameTableQueryServiceInterface::class));

        $this->assertSame(CalendarSourceColor::Accent, $source->color());
    }

    public function test_find_by_date_range_maps_dto_to_calendar_entry(): void
    {
        $dto = $this->makeListDTO([
            'id' => 'table-1',
            'title' => 'Epic Adventure',
            'slug' => 'epic-adventure',
            'startsAt' => new DateTimeImmutable('2026-09-15 10:00:00'),
            'durationMinutes' => 120,
        ]);

        $queryService = $this->createMock(GameTableQueryServiceInterface::class);
        $queryService->expects($this->once())
            ->method('getUpcomingTables')
            ->willReturn([$dto]);

        $source = new GameTableCalendarSource($queryService);

        $entries = $source->findByDateRange(
            new DateTimeImmutable('2026-09-01'),
            new DateTimeImmutable('2026-09-30'),
        );

        $this->assertCount(1, $entries);

        /** @var CalendarEntryDTO $entry */
        $entry = $entries[0];

        $this->assertSame('table-1', $entry->id);
        $this->assertSame('game-table', $entry->sourceType);
        $this->assertSame('Epic Adventure', $entry->title);
        $this->assertEquals(new DateTimeImmutable('2026-09-15 10:00:00'), $entry->start);
        $this->assertEquals(new DateTimeImmutable('2026-09-15 12:00:00'), $entry->end);
        $this->assertSame('/mesas/epic-adventure', $entry->url);
    }

    public function test_find_by_date_range_discards_entries_with_null_starts_at(): void
    {
        $dto = $this->makeListDTO([
            'slug' => 'no-start-date',
            'startsAt' => null,
        ]);

        $queryService = $this->createStub(GameTableQueryServiceInterface::class);
        $queryService->method('getUpcomingTables')->willReturn([$dto]);

        $source = new GameTableCalendarSource($queryService);

        $entries = $source->findByDateRange(
            new DateTimeImmutable('2026-09-01'),
            new DateTimeImmutable('2026-09-30'),
        );

        $this->assertCount(0, $entries);
    }

    public function test_find_by_date_range_discards_entries_with_null_slug(): void
    {
        $dto = $this->makeListDTO([
            'slug' => null,
            'startsAt' => new DateTimeImmutable('2026-09-15 10:00:00'),
        ]);

        $queryService = $this->createStub(GameTableQueryServiceInterface::class);
        $queryService->method('getUpcomingTables')->willReturn([$dto]);

        $source = new GameTableCalendarSource($queryService);

        $entries = $source->findByDateRange(
            new DateTimeImmutable('2026-09-01'),
            new DateTimeImmutable('2026-09-30'),
        );

        $this->assertCount(0, $entries);
    }

    public function test_find_by_date_range_details_include_expected_keys(): void
    {
        $dto = $this->makeListDTO([
            'slug' => 'pathfinder-session',
            'gameSystemName' => 'Pathfinder',
            'currentPlayers' => 3,
            'maxPlayers' => 6,
            'status' => TableStatus::Scheduled,
            'startsAt' => new DateTimeImmutable('2026-09-15 10:00:00'),
        ]);

        $queryService = $this->createStub(GameTableQueryServiceInterface::class);
        $queryService->method('getUpcomingTables')->willReturn([$dto]);

        $source = new GameTableCalendarSource($queryService);

        $entries = $source->findByDateRange(
            new DateTimeImmutable('2026-09-01'),
            new DateTimeImmutable('2026-09-30'),
        );

        $details = $entries[0]->details;

        $this->assertSame('Pathfinder', $details['gameSystemName']);
        $this->assertSame(3, $details['currentPlayers']);
        $this->assertSame(6, $details['maxPlayers']);
        $this->assertFalse($details['isFull']);
        $this->assertSame('scheduled', $details['status']);
        $this->assertSame([], $details['tags']);
        $this->assertSame('', $details['description']);
        $this->assertArrayNotHasKey('memberPrice', $details);
        $this->assertArrayNotHasKey('nonMemberPrice', $details);
    }

    public function test_find_by_date_range_marks_details_as_full_when_current_players_reach_max(): void
    {
        $dto = $this->makeListDTO([
            'slug' => 'full-table',
            'currentPlayers' => 6,
            'maxPlayers' => 6,
            'startsAt' => new DateTimeImmutable('2026-09-15 10:00:00'),
        ]);

        $queryService = $this->createStub(GameTableQueryServiceInterface::class);
        $queryService->method('getUpcomingTables')->willReturn([$dto]);

        $source = new GameTableCalendarSource($queryService);

        $entries = $source->findByDateRange(
            new DateTimeImmutable('2026-09-01'),
            new DateTimeImmutable('2026-09-30'),
        );

        $this->assertTrue($entries[0]->details['isFull']);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeListDTO(array $overrides = []): GameTableListDTO
    {
        $defaults = [
            'id' => 'table-1',
            'title' => 'Default Table',
            'slug' => 'default-table',
            'gameSystemName' => 'Dungeons & Dragons',
            'startsAt' => new DateTimeImmutable('2026-09-15 10:00:00'),
            'durationMinutes' => 180,
            'tableFormat' => TableFormat::InPerson,
            'tableType' => TableType::OneShot,
            'status' => TableStatus::Scheduled,
            'location' => 'Sala 1',
            'onlineUrl' => null,
            'minPlayers' => 2,
            'maxPlayers' => 6,
            'currentPlayers' => 2,
            'isPublished' => true,
            'creatorName' => 'Creator',
            'mainGameMasterName' => 'Game Master',
            'eventId' => null,
            'eventTitle' => null,
            'imagePublicId' => null,
        ];

        $data = array_merge($defaults, $overrides);

        return new GameTableListDTO(
            id: $data['id'],
            title: $data['title'],
            slug: $data['slug'],
            gameSystemName: $data['gameSystemName'],
            startsAt: $data['startsAt'],
            durationMinutes: $data['durationMinutes'],
            tableFormat: $data['tableFormat'],
            tableType: $data['tableType'],
            status: $data['status'],
            location: $data['location'],
            onlineUrl: $data['onlineUrl'],
            minPlayers: $data['minPlayers'],
            maxPlayers: $data['maxPlayers'],
            currentPlayers: $data['currentPlayers'],
            isPublished: $data['isPublished'],
            creatorName: $data['creatorName'],
            mainGameMasterName: $data['mainGameMasterName'],
            eventId: $data['eventId'],
            eventTitle: $data['eventTitle'],
            imagePublicId: $data['imagePublicId'],
        );
    }
}
