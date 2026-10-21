<?php

declare(strict_types=1);

namespace Modules\GameTables\Infrastructure\Services;

use Modules\GameTables\Application\DTOs\CampaignResponseDTO;
use Modules\GameTables\Application\Services\CreationEligibilityServiceInterface;
use Modules\GameTables\Domain\Entities\Campaign;
use Modules\GameTables\Domain\Repositories\CampaignRepositoryInterface;
use Modules\GameTables\Domain\Repositories\CampaignRosterRepositoryInterface;
use Modules\GameTables\Domain\ValueObjects\CampaignId;

final readonly class ProfileCreatedCampaignsDataProvider
{
    public function __construct(
        private CampaignRepositoryInterface $campaignRepository,
        private CreationEligibilityServiceInterface $eligibilityService,
        private CampaignRosterRepositoryInterface $roster,
    ) {}

    /**
     * Get created campaigns data for a user's profile page.
     *
     * @return array{campaigns: array<array<string, mixed>>, drafts: array<array<string, mixed>>, total: int, canCreate: bool}|null
     */
    public function getDataForUser(?string $userId): ?array
    {
        if ($userId === null) {
            return null;
        }

        $eligibility = $this->eligibilityService->canCreateCampaign($userId);
        $canCreate = $eligibility->eligible && $eligibility->canCreateCampaigns;

        $campaigns = $this->campaignRepository->getByCreator($userId);

        if (count($campaigns) === 0) {
            return [
                'campaigns' => [],
                'drafts' => [],
                'total' => 0,
                'canCreate' => $canCreate,
            ];
        }

        $players = $this->roster->countsFor(
            array_values(array_map(static fn (Campaign $campaign): CampaignId => $campaign->id, $campaigns)),
        );

        $published = [];
        $drafts = [];

        foreach ($campaigns as $campaign) {
            $dto = CampaignResponseDTO::fromEntity($campaign, currentPlayers: $players[$campaign->id->value] ?? 0);
            $data = $this->formatCampaignForProfile($dto);

            if ($campaign->isPublished) {
                $published[] = $data;
            } else {
                $drafts[] = $data;
            }
        }

        return [
            'campaigns' => $published,
            'drafts' => $drafts,
            'total' => count($campaigns),
            'canCreate' => $canCreate,
        ];
    }

    /**
     * Format a campaign DTO for the profile display.
     *
     * @return array<string, mixed>
     */
    private function formatCampaignForProfile(CampaignResponseDTO $dto): array
    {
        return [
            'id' => $dto->id,
            'title' => $dto->title,
            'slug' => $dto->slug,
            'gameSystemName' => $dto->gameSystemName,
            'status' => $dto->status->value,
            'statusLabel' => $dto->status->label(),
            'statusColor' => $dto->status->color(),
            'frequency' => $dto->frequency?->value,
            'frequencyLabel' => $dto->frequency?->label(),
            'isPublished' => $dto->isPublished,
            'isRecruiting' => $dto->isRecruiting,
            'maxPlayers' => $dto->maxPlayers,
            'currentPlayers' => $dto->currentPlayers,
        ];
    }
}
