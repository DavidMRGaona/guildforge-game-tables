<?php

declare(strict_types=1);

namespace Modules\GameTables\Application\Services;

use DateTimeImmutable;
use Modules\GameTables\Application\DTOs\CreationEligibilityDTO;

interface CreationEligibilityServiceInterface
{
    /**
     * Check if a user can create game tables from the frontend.
     */
    public function canCreateTable(?string $userId): CreationEligibilityDTO;

    /**
     * Check if a user can create campaigns from the frontend.
     */
    public function canCreateCampaign(?string $userId): CreationEligibilityDTO;

    /**
     * Get the date when creation opens for a user based on an event's start date.
     * Returns null if the user cannot create for this event.
     */
    public function getCreationOpenDate(?string $userId, DateTimeImmutable $eventStartDate): ?DateTimeImmutable;

    /**
     * Get the user's priority tier number (lower = higher priority).
     * Returns null if user is not in any priority tier.
     */
    public function getUserPriorityTier(?string $userId): ?int;

    /**
     * Check if frontend table creation is enabled globally.
     */
    public function isTableCreationEnabled(): bool;

    /**
     * Check if frontend campaign creation is enabled globally.
     */
    public function isCampaignCreationEnabled(): bool;
}
