<?php

declare(strict_types=1);

namespace Modules\GameTables\Infrastructure\Services;

use Illuminate\Support\Str;
use Modules\GameTables\Application\Services\GameMasterServiceInterface;
use Modules\GameTables\Domain\Enums\GameMasterRole;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\CampaignModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameMasterModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameTableModel;

/**
 * Service for managing game masters with unified inheritance support.
 */
final class GameMasterService implements GameMasterServiceInterface
{
    public function addToCampaign(string $campaignId, array $data, int $sortOrder = 0): GameMasterModel
    {
        $gameMaster = $this->findOrCreateGameMaster($data);

        $campaign = CampaignModel::findOrFail($campaignId);

        // Attach to campaign if not already attached
        if (! $campaign->gameMasters()->where('gametables_game_masters.id', $gameMaster->id)->exists()) {
            $campaign->gameMasters()->attach($gameMaster->id, [
                'sort_order' => $sortOrder,
            ]);
        } else {
            // Update sort order if already attached
            $campaign->gameMasters()->updateExistingPivot($gameMaster->id, [
                'sort_order' => $sortOrder,
            ]);
        }

        return $gameMaster;
    }

    public function addToTable(string $gameTableId, array $data, int $sortOrder = 0): GameMasterModel
    {
        $gameMaster = $this->findOrCreateGameMaster($data);

        $gameTable = GameTableModel::findOrFail($gameTableId);

        // Attach as local GM if not already attached
        if (! $gameTable->gameMasters()->where('gametables_game_masters.id', $gameMaster->id)->exists()) {
            $gameTable->gameMasters()->attach($gameMaster->id, [
                'source' => 'local',
                'excluded' => false,
                'sort_order' => $sortOrder,
            ]);
        } else {
            // Update if already attached
            $gameTable->gameMasters()->updateExistingPivot($gameMaster->id, [
                'source' => 'local',
                'excluded' => false,
                'sort_order' => $sortOrder,
            ]);
        }

        return $gameMaster;
    }

    public function excludeFromTable(string $gameTableId, string $gameMasterId): void
    {
        $gameTable = GameTableModel::findOrFail($gameTableId);
        $gameMaster = GameMasterModel::findOrFail($gameMasterId);

        // Check if already in pivot table
        if ($gameTable->gameMasters()->where('gametables_game_masters.id', $gameMasterId)->exists()) {
            $gameTable->gameMasters()->updateExistingPivot($gameMasterId, [
                'excluded' => true,
            ]);
        } else {
            // Add to pivot with excluded = true
            $gameTable->gameMasters()->attach($gameMasterId, [
                'source' => 'inherited',
                'excluded' => true,
                'sort_order' => 0,
            ]);
        }
    }

    public function includeInTable(string $gameTableId, string $gameMasterId): void
    {
        $gameTable = GameTableModel::findOrFail($gameTableId);

        // Update excluded flag to false
        if ($gameTable->gameMasters()->where('gametables_game_masters.id', $gameMasterId)->exists()) {
            $gameTable->gameMasters()->updateExistingPivot($gameMasterId, [
                'excluded' => false,
            ]);
        }
    }

    public function isInherited(string $gameTableId, string $gameMasterId): bool
    {
        $gameTable = GameTableModel::findOrFail($gameTableId);

        // If the table has no campaign, nothing can be inherited
        if ($gameTable->campaign_id === null) {
            return false;
        }

        // Check if the GM is associated with the campaign
        $campaign = $gameTable->campaign;

        return $campaign !== null &&
            $campaign->gameMasters()->where('gametables_game_masters.id', $gameMasterId)->exists();
    }

    public function isExcluded(string $gameTableId, string $gameMasterId): bool
    {
        $gameTable = GameTableModel::findOrFail($gameTableId);

        $pivot = $gameTable->gameMasters()
            ->where('gametables_game_masters.id', $gameMasterId)
            ->first();

        return $pivot !== null && $pivot->pivot->excluded;
    }

    public function removeFromCampaign(string $campaignId, string $gameMasterId): void
    {
        $campaign = CampaignModel::findOrFail($campaignId);
        $campaign->gameMasters()->detach($gameMasterId);

        $this->cleanupIfOrphaned($gameMasterId);
    }

    public function removeFromTable(string $gameTableId, string $gameMasterId): void
    {
        $gameTable = GameTableModel::findOrFail($gameTableId);
        $gameTable->gameMasters()->detach($gameMasterId);

        $this->cleanupIfOrphaned($gameMasterId);
    }

    public function update(string $gameMasterId, array $data): GameMasterModel
    {
        $gameMaster = GameMasterModel::findOrFail($gameMasterId);

        $fillableData = array_filter($data, fn ($key) => in_array($key, [
            'user_id',
            'first_name',
            'last_name',
            'email',
            'phone',
            'role',
            'custom_title',
            'notify_by_email',
            'is_name_public',
            'notes',
        ]), ARRAY_FILTER_USE_KEY);

        // Handle role enum
        if (isset($fillableData['role']) && is_string($fillableData['role'])) {
            $fillableData['role'] = GameMasterRole::from($fillableData['role']);
        }

        $gameMaster->fill($fillableData);
        $gameMaster->save();

        return $gameMaster->fresh();
    }

    public function syncCampaignGameMasters(string $campaignId, array $gameMastersData): void
    {
        $campaign = CampaignModel::findOrFail($campaignId);
        $existingIds = $campaign->gameMasters()->pluck('gametables_game_masters.id')->toArray();
        $newIds = [];

        foreach ($gameMastersData as $index => $gmData) {
            // Skip if no user_id or external name
            if (empty($gmData['user_id']) && empty($gmData['first_name']) && empty($gmData['email'])) {
                continue;
            }

            // If GM has an existing ID, update it
            if (! empty($gmData['id']) && in_array($gmData['id'], $existingIds, true)) {
                $this->update($gmData['id'], $gmData);
                $campaign->gameMasters()->updateExistingPivot($gmData['id'], ['sort_order' => $index]);
                $newIds[] = $gmData['id'];
            } else {
                // Create new GM
                $gameMaster = $this->addToCampaign($campaignId, $gmData, $index);
                $newIds[] = $gameMaster->id;
            }
        }

        // Remove GMs that are no longer in the list
        $toRemove = array_diff($existingIds, $newIds);
        foreach ($toRemove as $gmId) {
            $this->removeFromCampaign($campaignId, $gmId);
        }
    }

    public function syncTableGameMasters(string $gameTableId, array $gameMastersData): void
    {
        $gameTable = GameTableModel::findOrFail($gameTableId);

        // Get existing local GMs for this table
        $existingLocalGms = $gameTable->gameMasters()
            ->wherePivot('source', 'local')
            ->pluck('gametables_game_masters.id')
            ->toArray();

        // Get campaign GM IDs if linked to campaign
        $campaignGmIds = [];
        if ($gameTable->campaign_id !== null && $gameTable->campaign !== null) {
            $campaignGmIds = $gameTable->campaign->gameMasters()
                ->pluck('gametables_game_masters.id')
                ->toArray();
        }

        $newLocalIds = [];
        $includedInheritedIds = [];

        foreach ($gameMastersData as $index => $gmData) {
            // Skip if marked as excluded
            if (! empty($gmData['excluded'])) {
                if (! empty($gmData['id']) && in_array($gmData['id'], $campaignGmIds, true)) {
                    $this->excludeFromTable($gameTableId, $gmData['id']);
                }

                continue;
            }

            // Skip if no user_id or external name
            if (empty($gmData['user_id']) && empty($gmData['first_name']) && empty($gmData['email'])) {
                continue;
            }

            // Check if this is an inherited GM (from campaign)
            if (! empty($gmData['id']) && in_array($gmData['id'], $campaignGmIds, true)) {
                // Inherited GM - just make sure it's not excluded
                $this->includeInTable($gameTableId, $gmData['id']);
                $includedInheritedIds[] = $gmData['id'];

                continue;
            }

            // Check if it's an existing local GM
            if (! empty($gmData['id']) && in_array($gmData['id'], $existingLocalGms, true)) {
                // Update existing local GM
                $this->update($gmData['id'], $gmData);
                $gameTable->gameMasters()->updateExistingPivot($gmData['id'], ['sort_order' => $index]);
                $newLocalIds[] = $gmData['id'];
            } else {
                // Create new local GM
                $gameMaster = $this->addToTable($gameTableId, $gmData, $index);
                $newLocalIds[] = $gameMaster->id;
            }
        }

        // Remove local GMs that are no longer in the list
        $toRemove = array_diff($existingLocalGms, $newLocalIds);
        foreach ($toRemove as $gmId) {
            $this->removeFromTable($gameTableId, $gmId);
        }

        // Mark inherited GMs as excluded if they were removed from the form data
        foreach ($campaignGmIds as $campaignGmId) {
            if (! in_array($campaignGmId, $includedInheritedIds, true)) {
                // Check if it was explicitly passed as excluded in the data
                $wasExplicitlyExcluded = false;
                foreach ($gameMastersData as $gmData) {
                    if (($gmData['id'] ?? null) === $campaignGmId && ! empty($gmData['excluded'])) {
                        $wasExplicitlyExcluded = true;

                        break;
                    }
                }

                // If not in included list and not explicitly marked, it might have been removed
                // Only exclude if it was in the original form but removed
                if (! $wasExplicitlyExcluded) {
                    // Don't auto-exclude - let the form handle explicit exclusions
                    // This preserves inherited GMs by default
                }
            }
        }
    }

    /**
     * Find an existing game master or create a new one.
     *
     * @param  array<string, mixed>  $data
     */
    private function findOrCreateGameMaster(array $data): GameMasterModel
    {
        // Prepare attributes
        $role = $data['role'] ?? GameMasterRole::Main->value;
        if (is_string($role)) {
            $role = GameMasterRole::from($role);
        }

        $attributes = [
            'user_id' => $data['user_id'] ?? null,
            'first_name' => $data['first_name'] ?? null,
            'last_name' => $data['last_name'] ?? null,
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'role' => $role,
            'custom_title' => $data['custom_title'] ?? null,
            'notify_by_email' => $data['notify_by_email'] ?? true,
            'is_name_public' => $data['is_name_public'] ?? true,
            'notes' => $data['notes'] ?? null,
        ];

        // If an ID is provided and exists, return that GM
        if (! empty($data['id'])) {
            $existing = GameMasterModel::find($data['id']);
            if ($existing !== null) {
                return $existing;
            }
        }

        // Try to find existing GM by user_id if provided
        if (! empty($attributes['user_id'])) {
            $existing = GameMasterModel::where('user_id', $attributes['user_id'])
                ->where('role', $attributes['role'])
                ->first();

            if ($existing !== null) {
                return $existing;
            }
        }

        // Create new GM
        return GameMasterModel::create(array_merge(['id' => Str::uuid()->toString()], $attributes));
    }

    /**
     * Delete a game master if they have no campaign or table associations.
     */
    private function cleanupIfOrphaned(string $gameMasterId): void
    {
        $gameMaster = GameMasterModel::find($gameMasterId);
        if ($gameMaster === null) {
            return;
        }

        $hasCampaigns = $gameMaster->campaigns()->exists();
        $hasTables = $gameMaster->gameTables()->exists();

        if (! $hasCampaigns && ! $hasTables) {
            $gameMaster->delete();
        }
    }
}
