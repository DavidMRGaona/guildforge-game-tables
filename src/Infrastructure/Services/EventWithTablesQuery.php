<?php

declare(strict_types=1);

namespace Modules\GameTables\Infrastructure\Services;

use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use Modules\GameTables\Application\Services\EventWithTablesQueryInterface;
use Modules\GameTables\Domain\Enums\TableStatus;

final class EventWithTablesQuery implements EventWithTablesQueryInterface
{
    /**
     * @return array<array{id: string, title: string, count: int}>
     */
    public function getEventsWithPublishedTables(): array
    {
        return EventModel::query()
            ->where('is_published', true)
            ->where('end_date', '>=', now())
            ->withCount(['gameTables' => function ($query): void {
                $query->where('is_published', true)
                    ->whereNotIn('status', [TableStatus::Cancelled->value, TableStatus::Draft->value]);
            }])
            ->orderBy('start_date')
            ->get()
            ->filter(fn (EventModel $event): bool => $event->game_tables_count > 0)
            ->map(fn (EventModel $event): array => [
                'id' => $event->id,
                'title' => $event->title,
                'count' => $event->game_tables_count,
            ])
            ->values()
            ->all();
    }
}
