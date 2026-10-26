<?php

declare(strict_types=1);

namespace Modules\GameTables\Application\Services;

use DateTimeImmutable;
use Modules\GameTables\Application\DTOs\EventCreationContextDTO;
use Modules\GameTables\Application\DTOs\EventGameTableConfigDTO;
use Modules\GameTables\Application\DTOs\UpdateEventGameTableConfigDTO;
use Modules\GameTables\Domain\Exceptions\TimeSlotUnavailableException;

interface EventGameTableConfigServiceInterface
{
    /**
     * Get the game table configuration for an event.
     */
    public function getConfigForEvent(string $eventId): EventGameTableConfigDTO;

    /**
     * Update the game table configuration for an event.
     */
    public function updateConfig(UpdateEventGameTableConfigDTO $dto): void;

    /**
     * Get the creation context for creating a game table within an event.
     * This includes restrictions and constraints from the event's config, and the tables each
     * time slot still takes, leaving out $exceptTableId (the table being edited).
     */
    public function getCreationContext(string $eventId, ?string $exceptTableId = null): EventCreationContextDTO;

    /**
     * On an event scheduled by time slots, a table starts at one of its slots (compared as an
     * instant) that still has room for it, leaving out $exceptTableId; any start does otherwise.
     *
     * @throws TimeSlotUnavailableException
     */
    public function ensureSlotTakes(string $eventId, DateTimeImmutable $startsAt, ?string $exceptTableId = null): void;

    /**
     * Check if game tables are enabled for an event.
     */
    public function isEnabledForEvent(string $eventId): bool;
}
