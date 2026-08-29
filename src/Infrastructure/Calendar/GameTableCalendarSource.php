<?php

declare(strict_types=1);

namespace Modules\GameTables\Infrastructure\Calendar;

use App\Application\Calendar\Contracts\CalendarEntrySourceInterface;
use App\Application\Calendar\DTOs\CalendarEntryDTO;
use App\Domain\Calendar\Enums\CalendarSourceColor;
use DateTimeImmutable;
use Modules\GameTables\Application\DTOs\GameTableListDTO;
use Modules\GameTables\Application\Services\GameTableQueryServiceInterface;

final readonly class GameTableCalendarSource implements CalendarEntrySourceInterface
{
    public function __construct(
        private GameTableQueryServiceInterface $gameTableQueryService,
    ) {}

    public function sourceType(): string
    {
        return 'game-table';
    }

    public function sourceLabel(): string
    {
        return __('game-tables::messages.calendar.source_label');
    }

    public function color(): CalendarSourceColor
    {
        return CalendarSourceColor::Accent;
    }

    /**
     * @return array<CalendarEntryDTO>
     */
    public function findByDateRange(DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        $tables = $this->gameTableQueryService->getUpcomingTables($from, $to);

        $entries = [];

        foreach ($tables as $table) {
            $entry = $this->toEntry($table);

            if ($entry !== null) {
                $entries[] = $entry;
            }
        }

        return $entries;
    }

    private function toEntry(GameTableListDTO $table): ?CalendarEntryDTO
    {
        if ($table->startsAt === null || $table->slug === null) {
            return null;
        }

        $start = DateTimeImmutable::createFromInterface($table->startsAt);
        $end = $start->modify("+{$table->durationMinutes} minutes");

        return new CalendarEntryDTO(
            id: $table->id,
            sourceType: $this->sourceType(),
            sourceLabel: $this->sourceLabel(),
            color: $this->color(),
            title: $table->title,
            start: $start,
            end: $end,
            url: '/mesas/'.$table->slug,
            details: [
                'gameSystemName' => $table->gameSystemName,
                'currentPlayers' => $table->currentPlayers,
                'maxPlayers' => $table->maxPlayers,
                'isFull' => $table->currentPlayers >= $table->maxPlayers,
                'status' => $table->status->value,
                'tags' => [],
                'description' => '',
            ],
        );
    }
}
