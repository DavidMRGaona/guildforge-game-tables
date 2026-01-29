<?php

declare(strict_types=1);

namespace Modules\GameTables\Application\Services;

use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameMasterModel;

/**
 * Service for managing game masters with unified inheritance support.
 *
 * Game masters can be:
 * - Associated directly with campaigns
 * - Associated directly with game tables (local)
 * - Inherited by game tables from their linked campaign
 */
interface GameMasterServiceInterface
{
    /**
     * Create or find a game master and associate them with a campaign.
     *
     * @param  array<string, mixed>  $data  The game master attributes
     * @return GameMasterModel The game master model
     */
    public function addToCampaign(string $campaignId, array $data, int $sortOrder = 0): GameMasterModel;

    /**
     * Create or find a game master and associate them directly with a table (local GM).
     *
     * @param  array<string, mixed>  $data  The game master attributes
     * @return GameMasterModel The game master model
     */
    public function addToTable(string $gameTableId, array $data, int $sortOrder = 0): GameMasterModel;

    /**
     * Mark an inherited GM as excluded from a specific table.
     * The GM remains on the campaign but won't appear on this table.
     */
    public function excludeFromTable(string $gameTableId, string $gameMasterId): void;

    /**
     * Include a previously excluded GM back into a table.
     */
    public function includeInTable(string $gameTableId, string $gameMasterId): void;

    /**
     * Check if a game master is inherited from a campaign for a specific table.
     */
    public function isInherited(string $gameTableId, string $gameMasterId): bool;

    /**
     * Check if a game master is excluded from a specific table.
     */
    public function isExcluded(string $gameTableId, string $gameMasterId): bool;

    /**
     * Remove a game master from a campaign.
     * If the GM is not used anywhere else, they will be deleted.
     */
    public function removeFromCampaign(string $campaignId, string $gameMasterId): void;

    /**
     * Remove a local game master from a table.
     * If the GM is not used anywhere else, they will be deleted.
     */
    public function removeFromTable(string $gameTableId, string $gameMasterId): void;

    /**
     * Update a game master's data.
     * Changes will be reflected everywhere the GM is used.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(string $gameMasterId, array $data): GameMasterModel;

    /**
     * Sync all game masters for a campaign from form data.
     *
     * @param  array<int, array<string, mixed>>  $gameMastersData
     */
    public function syncCampaignGameMasters(string $campaignId, array $gameMastersData): void;

    /**
     * Sync game masters for a game table from form data.
     * Handles both local GMs and inherited GM exclusions.
     *
     * @param  array<int, array<string, mixed>>  $gameMastersData
     */
    public function syncTableGameMasters(string $gameTableId, array $gameMastersData): void;
}
