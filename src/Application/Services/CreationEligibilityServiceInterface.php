<?php

declare(strict_types=1);

namespace Modules\GameTables\Application\Services;

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
     * Check if frontend table creation is enabled globally.
     */
    public function isTableCreationEnabled(): bool;

    /**
     * Check if frontend campaign creation is enabled globally.
     */
    public function isCampaignCreationEnabled(): bool;
}
