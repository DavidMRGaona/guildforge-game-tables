<?php

declare(strict_types=1);

namespace Modules\GameTables\Infrastructure\Services;

use Carbon\Carbon;
use Modules\GameTables\Application\DTOs\CampaignGameMasterResponseDTO;
use Modules\GameTables\Application\DTOs\CampaignResponseDTO;
use Modules\GameTables\Application\DTOs\GameTableListDTO;
use Modules\GameTables\Application\Services\CampaignQueryServiceInterface;
use Modules\GameTables\Domain\Enums\CampaignStatus;
use Modules\GameTables\Domain\Enums\TableStatus;
use Modules\GameTables\Domain\Repositories\CampaignRepositoryInterface;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\CampaignModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameMasterModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameTableModel;

final class CampaignQueryService implements CampaignQueryServiceInterface
{
    public function __construct(
        private readonly CampaignRepositoryInterface $campaignRepository,
    ) {}

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
        $campaign = $this->campaignRepository->findPublishedModelWithRelations($id);

        if ($campaign === null) {
            return null;
        }

        /** @var CampaignModel $campaign */
        return $this->toDetailDTO($campaign);
    }

    public function findPublishedBySlug(string $slug): ?CampaignResponseDTO
    {
        $campaign = $this->campaignRepository->findPublishedModelBySlugWithRelations($slug);

        if ($campaign === null) {
            return null;
        }

        /** @var CampaignModel $campaign */
        return $this->toDetailDTO($campaign);
    }

    /**
     * @param  array<string>|null  $gameSystemIds
     */
    private function buildQuery(
        ?array $gameSystemIds = null,
        ?string $status = null,
    ): \Illuminate\Database\Eloquent\Builder {
        $query = CampaignModel::query()
            ->with(['gameSystem', 'creator', 'gameMasters.user'])
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
        $gameMasters = $campaign->gameMasters->map(
            fn (GameMasterModel $gm) => CampaignGameMasterResponseDTO::fromModel($gm)
        )->all();

        return new CampaignResponseDTO(
            id: $campaign->id,
            title: $campaign->title,
            slug: $campaign->slug,
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
            acceptsNewPlayers: $campaign->accepts_new_players,
            sessionCount: $campaign->session_count,
            currentSession: $campaign->current_session,
            totalSessions: $campaign->game_tables_count ?? 0,
            imagePublicId: $campaign->image_public_id,
            gameMasters: $gameMasters,
            gameTables: [],
            hasActiveOrUpcomingTables: false,
            createdAt: $campaign->created_at,
            updatedAt: $campaign->updated_at,
        );
    }

    private function toDetailDTO(CampaignModel $campaign): CampaignResponseDTO
    {
        $gameMasters = $campaign->gameMasters->map(
            fn (GameMasterModel $gm) => CampaignGameMasterResponseDTO::fromModel($gm)
        )->all();

        $gameTables = $campaign->gameTables->map(
            fn (GameTableModel $table) => $this->toGameTableListDTO($table)
        )->all();

        $hasActiveOrUpcoming = $this->hasActiveOrUpcomingTables($campaign);

        return new CampaignResponseDTO(
            id: $campaign->id,
            title: $campaign->title,
            slug: $campaign->slug,
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
            acceptsNewPlayers: $campaign->accepts_new_players,
            sessionCount: $campaign->session_count,
            currentSession: $campaign->current_session,
            totalSessions: $campaign->gameTables->count(),
            imagePublicId: $campaign->image_public_id,
            gameMasters: $gameMasters,
            gameTables: $gameTables,
            hasActiveOrUpcomingTables: $hasActiveOrUpcoming,
            createdAt: $campaign->created_at,
            updatedAt: $campaign->updated_at,
        );
    }

    private function toGameTableListDTO(GameTableModel $table): GameTableListDTO
    {
        $confirmedPlayers = $table->participants
            ->where('status', 'confirmed')
            ->where('role', 'player')
            ->count();

        $mainGm = $table->gameMasters->firstWhere('is_main', true);
        $mainGameMasterName = '';
        if ($mainGm !== null) {
            $mainGameMasterName = $mainGm->is_name_public
                ? ($mainGm->user?->name ?? $mainGm->external_name ?? '')
                : '';
        }
        if ($mainGameMasterName === '') {
            $mainGameMasterName = $table->creator?->name ?? '';
        }

        return new GameTableListDTO(
            id: $table->id,
            title: $table->title,
            slug: $table->slug,
            gameSystemName: $table->gameSystem?->name ?? '',
            startsAt: $table->starts_at,
            durationMinutes: $table->duration_minutes,
            tableFormat: $table->table_format,
            tableType: $table->table_type,
            status: $table->status,
            location: $table->location,
            onlineUrl: $table->online_url,
            minPlayers: $table->min_players,
            maxPlayers: $table->max_players,
            currentPlayers: $confirmedPlayers,
            isPublished: $table->is_published,
            creatorName: $table->creator?->name ?? '',
            mainGameMasterName: $mainGameMasterName,
            eventId: $table->event_id,
            eventTitle: $table->event?->title,
            imagePublicId: $table->image_public_id,
        );
    }

    private function hasActiveOrUpcomingTables(CampaignModel $campaign): bool
    {
        $now = Carbon::now();

        return $campaign->gameTables->contains(function (GameTableModel $table) use ($now): bool {
            $isScheduled = $table->status === TableStatus::Scheduled;
            $isInProgress = $table->status === TableStatus::InProgress;
            $isFuture = $table->starts_at !== null && $table->starts_at >= $now;

            return ($isScheduled && $isFuture) || $isInProgress;
        });
    }
}
