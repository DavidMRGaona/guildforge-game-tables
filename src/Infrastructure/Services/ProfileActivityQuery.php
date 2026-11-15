<?php

declare(strict_types=1);

namespace Modules\GameTables\Infrastructure\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Modules\GameTables\Application\DTOs\ProfileCampaignItemDTO;
use Modules\GameTables\Application\DTOs\ProfileTableItemDTO;
use Modules\GameTables\Application\Services\CampaignDirectionInterface;
use Modules\GameTables\Application\Services\ProfileActivityQueryInterface;
use Modules\GameTables\Application\Services\TableDirectionInterface;
use Modules\GameTables\Domain\Enums\CampaignStatus;
use Modules\GameTables\Domain\Enums\FrontendCreationStatus;
use Modules\GameTables\Domain\Enums\ParticipantRole;
use Modules\GameTables\Domain\Enums\ParticipantStatus;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\CampaignModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameTableModel;

/**
 * Bound scoped: what each viewer may manage is read once per request or job, however many sections
 * and counters ask for it.
 */
final class ProfileActivityQuery implements ProfileActivityQueryInterface
{
    /** The campaign statuses the profile lists as going on */
    private const array ACTIVE_CAMPAIGN_STATUSES = [
        CampaignStatus::Recruiting->value,
        CampaignStatus::Active->value,
        CampaignStatus::OnHold->value,
    ];

    /** @var array<string, list<string>> */
    private array $delegatingTablesRunBy = [];

    /** @var array<string, list<string>> */
    private array $rostersManagedBy = [];

    public function __construct(
        private readonly TableDirectionInterface $tableDirection,
        private readonly CampaignDirectionInterface $campaignDirection,
    ) {}

    public function upcomingTablesRunBy(string $ownerId, int $limit): array
    {
        $query = $this->upcomingTables()->where(fn (Builder $query) => $this->runBy($query, $ownerId));

        return $this->tableItems($query, $limit);
    }

    public function upcomingTablesPlayedBy(string $ownerId, ?string $viewerId, int $limit): array
    {
        if ($viewerId === null) {
            return [];
        }

        $query = $this->upcomingTables()->whereHas('participants', fn (Builder $participant) => $participant
            ->where('user_id', $ownerId)
            ->where('role', ParticipantRole::Player->value)
            ->where('status', ParticipantStatus::Confirmed->value));

        if ($viewerId !== $ownerId) {
            $tableIds = $this->delegatingTablesRunBy($viewerId);
            $campaignIds = $this->rostersManagedBy($viewerId);

            if ($tableIds === [] && $campaignIds === []) {
                return [];
            }

            $query->where(fn (Builder $visible) => $visible
                // GameTablePolicy::manageRegistrations: «Gestionar inscritos» of a table of its own
                ->where(fn (Builder $table) => $table->whereNull('campaign_id')->whereIn('id', $tableIds))
                // CampaignPolicy::manageRoster: the roster page lists the sessions of its roster players
                ->orWhere(fn (Builder $session) => $session
                    ->whereIn('campaign_id', $campaignIds)
                    ->whereExists(fn (QueryBuilder $roster) => $this->rosterOf($roster, $ownerId, 'gametables_tables.campaign_id'))));
        }

        return $this->tableItems($query, $limit);
    }

    public function countTablesRunBy(string $ownerId): int
    {
        return GameTableModel::query()
            ->publiclyListed()
            ->where(fn (Builder $query) => $this->runBy($query, $ownerId))
            ->count();
    }

    public function activeCampaignsOf(string $ownerId, ?string $viewerId, int $limit): array
    {
        $campaigns = $this->campaignsOf($ownerId, $viewerId)
            ->whereIn('status', self::ACTIVE_CAMPAIGN_STATUSES)
            ->with('gameSystem')
            ->withExists(['gameMasters as directed_by_owner' => fn (Builder $gm) => $this->publicGameMaster($gm, $ownerId)])
            ->orderBy('title')
            ->orderBy('id')
            ->limit($limit)
            ->get();

        return array_values($campaigns->map(fn (CampaignModel $campaign): ProfileCampaignItemDTO => new ProfileCampaignItemDTO(
            id: $campaign->id,
            title: $campaign->title,
            url: route('campaigns.show', $campaign->slug ?? $campaign->id, false),
            gameSystemName: $campaign->gameSystem->name ?? '',
            status: $campaign->status,
            role: (bool) $campaign->getAttribute('directed_by_owner') ? ProfileCampaignItemDTO::ROLE_DIRECTOR : ProfileCampaignItemDTO::ROLE_PLAYER,
        ))->all());
    }

    public function countCampaignsOf(string $ownerId, ?string $viewerId): int
    {
        return $this->campaignsOf($ownerId, $viewerId)->count();
    }

    /**
     * @return Builder<GameTableModel>
     */
    private function upcomingTables(): Builder
    {
        return GameTableModel::query()->publiclyListed()->where('starts_at', '>=', now());
    }

    /**
     * @param  Builder<GameTableModel>  $query
     * @return list<ProfileTableItemDTO>
     */
    private function tableItems(Builder $query, int $limit): array
    {
        $tables = $query->with('gameSystem')->orderedByStart()->limit($limit)->get();

        return array_values($tables->map(fn (GameTableModel $table): ProfileTableItemDTO => new ProfileTableItemDTO(
            id: $table->id,
            title: $table->title,
            url: route('gametables.show', $table->slug ?? $table->id, false),
            startsAt: $table->starts_at->toDateTimeImmutable(),
            gameSystemName: $table->gameSystem->name ?? '',
        ))->all());
    }

    /**
     * The tables the owner directs as TableDirection reads it (local, or inherited from the campaign and
     * not excluded), as far as the page names them: a game master whose name is not public is never
     * tied to their account there.
     *
     * @param  Builder<GameTableModel>  $query
     */
    private function runBy(Builder $query, string $ownerId): void
    {
        $query
            ->whereHas('localGameMasters', fn (Builder $gm) => $this->publicGameMaster($gm, $ownerId))
            ->orWhere(fn (Builder $session) => $session
                ->whereNotNull('campaign_id')
                ->whereHas('campaign.gameMasters', fn (Builder $gm) => $this->publicGameMaster($gm, $ownerId)
                    ->whereNotIn('gametables_game_masters.id', fn (QueryBuilder $excluded) => $excluded
                        ->select('gametables_table_gm.game_master_id')
                        ->from('gametables_table_gm')
                        ->whereColumn('gametables_table_gm.game_table_id', 'gametables_tables.id')
                        ->where('gametables_table_gm.excluded', true))));
    }

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $gm
     * @return Builder<TModel>
     */
    private function publicGameMaster(Builder $gm, string $ownerId): Builder
    {
        return $gm
            ->where('gametables_game_masters.user_id', $ownerId)
            ->where('gametables_game_masters.is_name_public', true);
    }

    /**
     * The owner's listed campaigns (any status but cancelled) they direct under a public name or, as
     * far as the viewer may see the roster, play in.
     *
     * @return Builder<CampaignModel>
     */
    private function campaignsOf(string $ownerId, ?string $viewerId): Builder
    {
        $rosterCampaignIds = match (true) {
            $viewerId === null => [],
            $viewerId === $ownerId => null,
            default => $this->rostersManagedBy($viewerId),
        };

        return CampaignModel::query()
            ->publiclyListed()
            ->where(function (Builder $query) use ($ownerId, $rosterCampaignIds): void {
                $query->whereHas('gameMasters', fn (Builder $gm) => $this->publicGameMaster($gm, $ownerId));

                // The owner sees their own roster places; anybody else only those of the rosters they manage
                if ($rosterCampaignIds === null || $rosterCampaignIds !== []) {
                    $query->orWhere(function (Builder $played) use ($ownerId, $rosterCampaignIds): void {
                        if ($rosterCampaignIds !== null) {
                            $played->whereIn('id', $rosterCampaignIds);
                        }

                        $played->whereExists(fn (QueryBuilder $roster) => $this->rosterOf($roster, $ownerId, 'gametables_campaigns.id'));
                    });
                }
            });
    }

    private function rosterOf(QueryBuilder $roster, string $ownerId, string $campaignColumn): void
    {
        $roster->selectRaw('1')
            ->from('gametables_campaign_players')
            ->whereColumn('gametables_campaign_players.campaign_id', $campaignColumn)
            ->where('gametables_campaign_players.user_id', $ownerId);
    }

    /**
     * The tables of their own (not campaign sessions) whose registrations the viewer manages: published,
     * delegating them and run by the viewer (GameTablePolicy::manageRegistrations).
     *
     * @return list<string>
     */
    private function delegatingTablesRunBy(string $viewerId): array
    {
        if (! array_key_exists($viewerId, $this->delegatingTablesRunBy)) {
            $directed = $this->tableDirection->tableIdsDirectedBy($viewerId);

            $this->delegatingTablesRunBy[$viewerId] = $directed === [] ? [] : array_values(GameTableModel::query()
                ->whereIn('id', $directed)
                ->whereNull('campaign_id')
                ->where('delegates_registrations', true)
                ->where('is_published', true)
                ->pluck('id')
                ->map(fn (mixed $id): string => (string) $id)
                ->all());
        }

        return $this->delegatingTablesRunBy[$viewerId];
    }

    /**
     * The campaigns whose roster page the viewer opens: approved or published and directed by them
     * (CampaignPolicy::manageRoster).
     *
     * @return list<string>
     */
    private function rostersManagedBy(string $viewerId): array
    {
        if (! array_key_exists($viewerId, $this->rostersManagedBy)) {
            $directed = $this->campaignDirection->campaignIdsDirectedBy($viewerId);

            $this->rostersManagedBy[$viewerId] = $directed === [] ? [] : array_values(CampaignModel::query()
                ->whereIn('id', $directed)
                ->where(fn (Builder $open) => $open
                    ->where('frontend_creation_status', FrontendCreationStatus::Approved->value)
                    ->orWhere('is_published', true))
                ->pluck('id')
                ->map(fn (mixed $id): string => (string) $id)
                ->all());
        }

        return $this->rostersManagedBy[$viewerId];
    }
}
