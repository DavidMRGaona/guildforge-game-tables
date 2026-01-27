<?php

declare(strict_types=1);

namespace Modules\GameTables\Infrastructure\Services;

use Modules\GameTables\Application\DTOs\CampaignResponseDTO;
use Modules\GameTables\Application\Services\CampaignQueryServiceInterface;
use Modules\GameTables\Domain\Enums\CampaignStatus;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\CampaignModel;

final class CampaignQueryService implements CampaignQueryServiceInterface
{
    public function getPublishedCampaignsPaginated(
        int $page,
        int $perPage,
        ?array $gameSystemIds = null,
        ?string $status = null,
    ): array {
        $query = $this->buildQuery($gameSystemIds, $status);

        $campaigns = $query
            ->orderBy('created_at', 'desc')
            ->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->get();

        return $campaigns->map(fn (CampaignModel $campaign) => $this->toDTO($campaign))->all();
    }

    public function getPublishedCampaignsTotal(
        ?array $gameSystemIds = null,
        ?string $status = null,
    ): int {
        return $this->buildQuery($gameSystemIds, $status)->count();
    }

    public function findPublished(string $id): ?CampaignResponseDTO
    {
        $campaign = CampaignModel::query()
            ->with(['gameSystem', 'creator', 'gameTables'])
            ->where('id', $id)
            ->where('is_published', true)
            ->first();

        if ($campaign === null) {
            return null;
        }

        return $this->toDTO($campaign);
    }

    /**
     * @param  array<string>|null  $gameSystemIds
     */
    private function buildQuery(
        ?array $gameSystemIds = null,
        ?string $status = null,
    ): \Illuminate\Database\Eloquent\Builder {
        $query = CampaignModel::query()
            ->with(['gameSystem', 'creator'])
            ->withCount(['gameTables', 'players'])
            ->where('is_published', true)
            ->whereNotIn('status', [CampaignStatus::Cancelled->value]);

        if ($gameSystemIds !== null && count($gameSystemIds) > 0) {
            $query->whereIn('game_system_id', $gameSystemIds);
        }

        if ($status !== null) {
            $query->where('status', $status);
        }

        return $query;
    }

    private function toDTO(CampaignModel $campaign): CampaignResponseDTO
    {
        return new CampaignResponseDTO(
            id: $campaign->id,
            title: $campaign->title,
            description: $campaign->description,
            gameSystemId: $campaign->game_system_id,
            gameSystemName: $campaign->gameSystem?->name ?? '',
            createdBy: $campaign->created_by,
            creatorName: $campaign->creator?->name ?? '',
            status: $campaign->status,
            frequency: $campaign->frequency,
            maxPlayers: $campaign->max_players,
            currentPlayers: $campaign->players_count ?? 0,
            isPublished: $campaign->is_published,
            isRecruiting: $campaign->status === CampaignStatus::Recruiting,
            totalSessions: $campaign->game_tables_count ?? 0,
            createdAt: $campaign->created_at,
            updatedAt: $campaign->updated_at,
        );
    }
}
