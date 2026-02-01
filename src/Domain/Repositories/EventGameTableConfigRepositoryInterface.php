<?php

declare(strict_types=1);

namespace Modules\GameTables\Domain\Repositories;

use Modules\GameTables\Domain\Entities\EventGameTableConfig;

interface EventGameTableConfigRepositoryInterface
{
    /**
     * Save a game table config (create or update).
     */
    public function save(EventGameTableConfig $config): void;

    /**
     * Find a config by event ID.
     */
    public function findByEvent(string $eventId): ?EventGameTableConfig;

    /**
     * Find a config by event ID or return a default config.
     */
    public function findByEventOrDefault(string $eventId): EventGameTableConfig;

    /**
     * Delete a config.
     */
    public function delete(string $eventId): void;

    /**
     * Check if a config exists for an event.
     */
    public function exists(string $eventId): bool;
}
