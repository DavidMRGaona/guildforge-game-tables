<?php

declare(strict_types=1);

namespace Modules\GameTables\Application\Services;

use Modules\GameTables\Application\DTOs\CampaignResponseDTO;
use Modules\GameTables\Application\DTOs\FrontendCreateCampaignDTO;
use Modules\GameTables\Application\DTOs\FrontendCreateGameTableDTO;
use Modules\GameTables\Application\DTOs\GameTableResponseDTO;

interface FrontendCreationServiceInterface
{
    /**
     * Create a new game table from the frontend.
     * The table will be created in Draft or PendingReview status depending on config.
     *
     * @throws \Modules\GameTables\Domain\Exceptions\NotEligibleToCreateException
     */
    public function createGameTable(FrontendCreateGameTableDTO $dto): GameTableResponseDTO;

    /**
     * Create a new campaign from the frontend.
     *
     * @throws \Modules\GameTables\Domain\Exceptions\NotEligibleToCreateException
     */
    public function createCampaign(FrontendCreateCampaignDTO $dto): CampaignResponseDTO;

    /**
     * Get data needed to render the creation form (game systems, formats, etc.).
     *
     * @return array{
     *     game_systems: array,
     *     table_types: array,
     *     table_formats: array,
     *     experience_levels: array,
     *     tones: array,
     *     character_creation: array,
     *     safety_tools: array,
     *     content_warnings: array,
     *     genres: array
     * }
     */
    public function getCreateFormData(): array;

    /**
     * Get all drafts created by a user.
     *
     * @return array<GameTableResponseDTO>
     */
    public function getUserDrafts(string $userId): array;

    /**
     * Submit a draft table for review.
     *
     * @throws \Modules\GameTables\Domain\Exceptions\TableNotFoundException
     * @throws \Modules\GameTables\Domain\Exceptions\InvalidStatusTransitionException
     */
    public function submitForReview(string $tableId, string $userId): void;

    /**
     * Update a draft table.
     *
     * @throws \Modules\GameTables\Domain\Exceptions\TableNotFoundException
     * @throws \Modules\GameTables\Domain\Exceptions\NotEditableException
     */
    public function updateDraft(string $tableId, FrontendCreateGameTableDTO $dto): GameTableResponseDTO;

    /**
     * Delete a draft table.
     *
     * @throws \Modules\GameTables\Domain\Exceptions\TableNotFoundException
     * @throws \Modules\GameTables\Domain\Exceptions\NotEditableException
     */
    public function deleteDraft(string $tableId, string $userId): void;

    /**
     * Approve a frontend-created table for publication.
     *
     * @throws \Modules\GameTables\Domain\Exceptions\GameTableNotFoundException
     */
    public function approveFrontendCreation(string $tableId, ?string $notes = null): void;

    /**
     * Reject a frontend-created table.
     *
     * @throws \Modules\GameTables\Domain\Exceptions\GameTableNotFoundException
     */
    public function rejectFrontendCreation(string $tableId, string $reason): void;

    /**
     * Get all campaign drafts created by a user.
     *
     * @return array<CampaignResponseDTO>
     */
    public function getUserCampaignDrafts(string $userId): array;

    /**
     * Update a draft campaign.
     *
     * @throws \Modules\GameTables\Domain\Exceptions\CampaignNotFoundException
     * @throws \Modules\GameTables\Domain\Exceptions\NotEditableException
     */
    public function updateCampaignDraft(string $campaignId, FrontendCreateCampaignDTO $dto): CampaignResponseDTO;

    /**
     * Submit a draft campaign for review.
     *
     * @throws \Modules\GameTables\Domain\Exceptions\CampaignNotFoundException
     * @throws \Modules\GameTables\Domain\Exceptions\NotEditableException
     */
    public function submitCampaignForReview(string $campaignId, string $userId): void;

    /**
     * Delete a draft campaign.
     *
     * @throws \Modules\GameTables\Domain\Exceptions\CampaignNotFoundException
     * @throws \Modules\GameTables\Domain\Exceptions\NotEditableException
     */
    public function deleteCampaignDraft(string $campaignId, string $userId): void;
}
