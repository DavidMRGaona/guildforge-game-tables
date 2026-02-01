<?php

declare(strict_types=1);

namespace Modules\GameTables\Application\Services;

use Modules\GameTables\Application\DTOs\EventCreationContextDTO;
use Modules\GameTables\Application\DTOs\EventGameTableConfigDTO;
use Modules\GameTables\Application\DTOs\UpdateEventGameTableConfigDTO;

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
     * This includes restrictions and constraints from the event's config.
     */
    public function getCreationContext(string $eventId): EventCreationContextDTO;

    /**
     * Check if game tables are enabled for an event.
     */
    public function isEnabledForEvent(string $eventId): bool;
}
