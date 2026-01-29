<?php

declare(strict_types=1);

namespace Modules\GameTables\Domain\Repositories;

use Modules\GameTables\Domain\Entities\Campaign;
use Modules\GameTables\Domain\Enums\CampaignStatus;
use Modules\GameTables\Domain\Exceptions\CampaignNotFoundException;
use Modules\GameTables\Domain\ValueObjects\CampaignId;
use Modules\GameTables\Domain\ValueObjects\GameSystemId;

interface CampaignRepositoryInterface
{
    /**
     * Save a campaign (create or update).
     */
    public function save(Campaign $campaign): void;

    /**
     * Find a campaign by ID.
     */
    public function find(CampaignId $id): ?Campaign;

    /**
     * Find a campaign by ID or throw an exception.
     *
     * @throws CampaignNotFoundException
     */
    public function findOrFail(CampaignId $id): Campaign;

    /**
     * Delete a campaign by ID.
     */
    public function delete(CampaignId $id): void;

    /**
     * Get all active campaigns.
     *
     * @return array<Campaign>
     */
    public function getActive(): array;

    /**
     * Get campaigns by status.
     *
     * @return array<Campaign>
     */
    public function getByStatus(CampaignStatus $status): array;

    /**
     * Get campaigns by game system.
     *
     * @return array<Campaign>
     */
    public function getByGameSystem(GameSystemId $gameSystemId): array;

    /**
     * Get campaigns created by a specific user.
     *
     * @return array<Campaign>
     */
    public function getByCreator(string $userId): array;

    /**
     * Get all campaigns.
     *
     * @return array<Campaign>
     */
    public function all(): array;

    /**
     * Find a published campaign model with all relations for detail view.
     * Returns the model (not entity) to support eager loading for read scenarios.
     */
    public function findPublishedModelWithRelations(string $id): ?object;

    /**
     * Find a published campaign model by slug with all relations for detail view.
     * Returns the model (not entity) to support eager loading for read scenarios.
     */
    public function findPublishedModelBySlugWithRelations(string $slug): ?object;
}
