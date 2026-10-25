<?php

declare(strict_types=1);

namespace Modules\GameTables\Infrastructure\Calendar;

use App\Application\Calendar\Contracts\CalendarEntrySourceInterface;
use App\Application\Calendar\DTOs\CalendarEntryDTO;
use App\Domain\Calendar\Enums\CalendarSourceColor;
use DateInterval;
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

            if ($entry instanceof \App\Application\Calendar\DTOs\CalendarEntryDTO) {
                $entries[] = $entry;
            }
        }

        return $entries;
    }

    private function toEntry(GameTableListDTO $table): ?CalendarEntryDTO
    {
        if (!$table->startsAt instanceof \DateTimeInterface || $table->slug === null) {
            return null;
        }

        $start = DateTimeImmutable::createFromInterface($table->startsAt);
        $end = $start->add(new DateInterval("PT{$table->durationMinutes}M"));

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
                'description' => $table->synopsis ?? '',
                'location' => $table->location,
                'imagePublicId' => $table->imagePublicId,
                'meta' => $this->meta($table),
            ],
        );
    }

    /**
     * Rows the core detail panel renders generically, in display order.
     * Labels are translated here so the frontend stays agnostic of this module.
     *
     * @return array<int, array{icon: string, label: string, value: string}>
     */
    private function meta(GameTableListDTO $table): array
    {
        $isFull = $table->currentPlayers >= $table->maxPlayers;
        $players = "{$table->currentPlayers}/{$table->maxPlayers}";

        if ($isFull) {
            $players .= ' · '.__('game-tables::messages.enums.table_status.full');
        }

        $rows = [
            ['icon' => 'dice', 'label' => __('game-tables::messages.calendar.meta.game_system'), 'value' => $table->gameSystemName],
            ['icon' => 'user', 'label' => __('game-tables::messages.calendar.meta.game_master'), 'value' => $table->mainGameMasterName],
            ['icon' => 'users', 'label' => __('game-tables::messages.calendar.meta.players'), 'value' => $players],
            ['icon' => 'monitor', 'label' => __('game-tables::messages.calendar.meta.format'), 'value' => $table->tableFormat->label()],
        ];

        return array_values(array_filter($rows, fn (array $row): bool => $row['value'] !== ''));
    }
}
