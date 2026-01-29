<?php

declare(strict_types=1);

namespace Modules\GameTables\Application\Services;

/**
 * Interface for querying events that have associated game tables.
 * This isolates the cross-module dependency on EventModel from the domain layer.
 */
interface EventWithTablesQueryInterface
{
    /**
     * Get upcoming/current published events that have published game tables.
     * Returns array of arrays with 'id', 'title', and 'count' keys.
     *
     * @return array<array{id: string, title: string, count: int}>
     */
    public function getEventsWithPublishedTables(): array;
}
