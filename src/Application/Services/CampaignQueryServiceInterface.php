<?php

declare(strict_types=1);

namespace Modules\GameTables\Application\Services;

use Modules\GameTables\Application\DTOs\CampaignResponseDTO;

interface CampaignQueryServiceInterface
{
    /**
     * Get published campaigns paginated.
     *
     * @param  array<string>|null  $gameSystemIds
     * @return array<CampaignResponseDTO>
     */
    public function getPublishedCampaignsPaginated(
        int $page,
        int $perPage,
        ?array $gameSystemIds = null,
        ?string $status = null,
    ): array;

    /**
     * Get total count of published campaigns.
     *
     * @param  array<string>|null  $gameSystemIds
     */
    public function getPublishedCampaignsTotal(
        ?array $gameSystemIds = null,
        ?string $status = null,
    ): int;

    /**
     * Find a published campaign by ID.
     */
    public function findPublished(string $id): ?CampaignResponseDTO;
}
