<?php

declare(strict_types=1);

namespace Modules\GameTables\Infrastructure\Services;

use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use Modules\GameTables\Application\Services\EventWithTablesQueryInterface;
use Modules\GameTables\Domain\Enums\TableStatus;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameTableModel;

final class EventWithTablesQuery implements EventWithTablesQueryInterface
{
    /**
     * @return array<array{id: string, title: string, count: int}>
     */
    public function getEventsWithPublishedTables(): array
    {
        // A subquery rather than withCount() over the gameTables relation the provider adds to the
        // core's EventModel at runtime: the same single query, with types static analysis can read
        $tableCount = GameTableModel::query()
            ->selectRaw('count(*)')
            ->whereColumn((new GameTableModel)->qualifyColumn('event_id'), (new EventModel)->getQualifiedKeyName())
            ->where('is_published', true)
            ->whereNotIn('status', [TableStatus::Cancelled->value, TableStatus::Draft->value]);

        return EventModel::query()
            ->where('is_published', true)
            ->where('end_date', '>=', now())
            ->addSelect(['game_tables_count' => $tableCount])
            ->orderBy('start_date')
            ->get()
            ->filter(fn (EventModel $event): bool => (int) $event->getAttribute('game_tables_count') > 0)
            ->map(fn (EventModel $event): array => [
                'id' => $event->id,
                'title' => $event->title,
                'count' => (int) $event->getAttribute('game_tables_count'),
            ])
            ->values()
            ->all();
    }
}
