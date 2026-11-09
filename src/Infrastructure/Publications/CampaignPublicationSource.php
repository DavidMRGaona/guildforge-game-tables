<?php

declare(strict_types=1);

namespace Modules\GameTables\Infrastructure\Publications;

use App\Application\Publications\Contracts\PublicationRosterInterface;
use App\Application\Publications\Contracts\PublicationSourceInterface;
use App\Application\Publications\DTOs\PublicationDTO;
use App\Application\Publications\DTOs\PublicationMembershipDTO;
use App\Application\Publications\DTOs\PublicationRosterDTO;
use App\Application\Publications\Enums\PublicationState;
use App\Infrastructure\Search\Support\PlainText;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\GameTables\Domain\Entities\Campaign;
use Modules\GameTables\Domain\Enums\CampaignStatus;
use Modules\GameTables\Domain\Repositories\CampaignRepositoryInterface;
use Modules\GameTables\Domain\Repositories\CampaignRosterRepositoryInterface;
use Modules\GameTables\Domain\ValueObjects\CampaignId;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\CampaignModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Pivots\CampaignGameMasterPivot;

/**
 * Published campaigns as publications (core contract). A campaign has no dates of its own (its
 * sessions do), so it carries none. Open means it takes new players; full means its roster
 * reached its limit.
 */
final readonly class CampaignPublicationSource implements PublicationRosterInterface, PublicationSourceInterface
{
    /** Stored by whoever announces the campaigns: never changes. */
    public const string TYPE = 'campaign';

    /** The campaign roster, written by query builder (CampaignRosterRepositoryInterface) */
    private const string PLAYERS_TABLE = 'gametables_campaign_players';

    public function __construct(
        private CampaignRepositoryInterface $campaigns,
        private CampaignRosterRepositoryInterface $roster,
        private GameSystemPublicationTopic $gameSystem,
    ) {}

    public function type(): string
    {
        return self::TYPE;
    }

    public function find(string $id): ?PublicationDTO
    {
        $campaign = $this->campaign($id);

        if (! $campaign instanceof Campaign) {
            return null;
        }

        return new PublicationDTO(
            type: self::TYPE,
            id: $campaign->id->value,
            title: $campaign->title,
            summary: PlainText::from((string) $campaign->description),
            url: route('campaigns.show', $campaign->slug),
            state: match ($campaign->status) {
                CampaignStatus::Completed => PublicationState::Ended,
                CampaignStatus::Cancelled => PublicationState::Cancelled,
                default => PublicationState::Active,
            },
            topics: $this->gameSystem->of($campaign->gameSystemId),
            isFull: ! $campaign->hasRoomFor($this->roster->count($campaign->id)),
            registrationOpen: $campaign->canAcceptNewPlayers(),
        );
    }

    /**
     * Published campaigns that are neither completed nor cancelled.
     */
    public function publishedIds(): iterable
    {
        $campaigns = CampaignModel::query()
            ->select('id')
            ->where('is_published', true)
            ->whereNotIn('status', [CampaignStatus::Completed->value, CampaignStatus::Cancelled->value])
            ->lazyById(200);

        foreach ($campaigns as $campaign) {
            yield (string) $campaign->getKey();
        }
    }

    /**
     * The players, and the game masters (main and co-GMs) who have an account.
     */
    public function roster(string $id): ?PublicationRosterDTO
    {
        $campaign = $this->campaign($id);

        if (! $campaign instanceof Campaign) {
            return null;
        }

        return new PublicationRosterDTO(
            memberIds: $this->ids(DB::table(self::PLAYERS_TABLE)->where('campaign_id', $campaign->id->value)->pluck('user_id')->all()),
            leaderIds: $this->ids($this->directors()->where('cgm.campaign_id', $campaign->id->value)->pluck('gm.user_id')->all()),
        );
    }

    public function rostersOf(string $userId): array
    {
        if (! Str::isUuid($userId)) {
            return [];
        }

        $userId = strtolower($userId);
        $plays = DB::table(self::PLAYERS_TABLE)->where('user_id', $userId)->pluck('campaign_id')->all();
        $directs = $this->directors()->where('gm.user_id', $userId)->pluck('cgm.campaign_id')->all();
        $candidates = array_values(array_unique(array_map(strval(...), array_merge($plays, $directs))));

        if ($candidates === []) {
            return [];
        }

        $leads = array_flip(array_map(strval(...), $directs));
        $campaigns = CampaignModel::query()
            ->whereIn('id', $candidates)
            ->where('is_published', true)
            ->whereNotIn('status', [CampaignStatus::Completed->value, CampaignStatus::Cancelled->value])
            ->orderBy('id')
            ->pluck('id')
            ->all();

        return array_values(array_map(
            static fn (mixed $id): PublicationMembershipDTO => new PublicationMembershipDTO(self::TYPE, (string) $id, isset($leads[(string) $id])),
            $campaigns,
        ));
    }

    /**
     * Campaign directors with an account: the pivot joined to their game master record.
     */
    private function directors(): Builder
    {
        return DB::table(CampaignGameMasterPivot::TABLE.' as cgm')
            ->join('gametables_game_masters as gm', 'gm.id', '=', 'cgm.game_master_id')
            ->whereNotNull('gm.user_id');
    }

    /**
     * @param  array<mixed>  $ids
     * @return list<string>
     */
    private function ids(array $ids): array
    {
        return array_values(array_unique(array_map(static fn (mixed $id): string => strtolower((string) $id), $ids)));
    }

    private function campaign(string $id): ?Campaign
    {
        if (! Str::isUuid($id)) {
            return null;
        }

        $campaign = $this->campaigns->find(new CampaignId($id));

        return $campaign instanceof Campaign && $campaign->isPublished ? $campaign : null;
    }
}
