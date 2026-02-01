<?php

declare(strict_types=1);

namespace Modules\GameTables\Application\Services;

use Modules\GameTables\Application\DTOs\CreationEligibilityDTO;

/**
 * Service for checking eligibility to create game tables within a specific event.
 * This service considers event-level configuration overrides in addition to global settings.
 */
interface EventCreationEligibilityServiceInterface
{
    /**
     * Check if a user can create a game table for a specific event.
     * Takes into account both global eligibility and event-specific overrides.
     */
    public function canCreateTableForEvent(string $eventId, ?string $userId): CreationEligibilityDTO;
}
