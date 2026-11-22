<?php

declare(strict_types=1);

namespace Modules\GameTables\Infrastructure\Activity;

use App\Application\Activity\Contracts\ActivitySourceInterface;
use App\Application\Activity\Contracts\OffersActivityAttributes;
use App\Application\Activity\DTOs\ActivityFactDTO;
use App\Application\Activity\DTOs\ActivityHistoryPageDTO;
use App\Application\Activity\DTOs\ActivitySubjectDTO;
use App\Application\Activity\DTOs\ActivitySubjectFactsDTO;
use App\Domain\Activity\ActivityKind;
use App\Domain\Activity\ActivityRole;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use DateTimeImmutable;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Modules\GameTables\Domain\Enums\CampaignStatus;
use Modules\GameTables\Domain\Enums\TableStatus;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\CampaignModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameMasterModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameTableModel;

/**
 * Completed campaigns as activity (core#774): its roster played it, its direction ran it and
 * somebody created it, with the game system played. A campaign completed again only refreshes
 * its facts: they are the same subject.
 *
 * Only published campaigns, which any signed-in member can know exist. Never reads the signed-in
 * user: the same answer in a queued job as in a request.
 */
final readonly class CampaignActivitySource implements ActivitySourceInterface, OffersActivityAttributes
{
    /** Stored by consumers with every fact: never changes. */
    public const string TYPE = 'game-tables.campaign';

    private const string CURSOR_SCOPE = 'campaign';

    public function __construct(
        private GameSystemActivityAttribute $gameSystem,
    ) {}

    public function type(): string
    {
        return self::TYPE;
    }

    public function kind(): ActivityKind
    {
        return ActivityKind::Campaign;
    }

    public function label(): string
    {
        return __('game-tables::messages.activity.campaign_label');
    }

    public function facts(string $subjectId): array
    {
        if (! Str::isUuid($subjectId)) {
            return [];
        }

        $campaign = $this->counted()->whereKey(strtolower($subjectId))->first();

        return $campaign instanceof CampaignModel ? self::factsOf($campaign) : [];
    }

    /**
     * A limit under 1 is read as 1 and one over ActivityHistoryPageDTO::MAX_LIMIT as that maximum
     * (the core never asks for either), so a page never claims to be the last one while subjects
     * remain.
     */
    public function history(?string $cursor, int $limit): ActivityHistoryPageDTO
    {
        $after = $cursor === null ? null : ActivityCursor::read(self::CURSOR_SCOPE, $cursor);
        $limit = max(1, min($limit, ActivityHistoryPageDTO::MAX_LIMIT));

        $campaigns = $this->counted()
            ->when($after !== null, static fn (Builder $query): Builder => $query->where('gametables_campaigns.id', '>', $after))
            ->orderBy('gametables_campaigns.id')
            ->limit($limit + 1)
            ->get();

        $page = $campaigns->take($limit);
        $last = $page->last();

        return new ActivityHistoryPageDTO(
            subjects: array_values($page->map(static fn (CampaignModel $campaign): ActivitySubjectFactsDTO => new ActivitySubjectFactsDTO((string) $campaign->getKey(), self::factsOf($campaign)))->all()),
            nextCursor: $campaigns->count() > $limit && $last instanceof CampaignModel ? ActivityCursor::after(self::CURSOR_SCOPE, (string) $last->getKey()) : null,
        );
    }

    /**
     * Title and page of the campaigns the viewer may open: the campaign page's own rule
     * (published), which shows them to guests too.
     */
    public function describe(array $subjectIds, ?Authenticatable $viewer): array
    {
        $ids = array_values(array_unique(array_map(strtolower(...), array_filter($subjectIds, Str::isUuid(...)))));

        if ($ids === []) {
            return [];
        }

        $described = [];

        foreach (CampaignModel::query()->shownOnItsPage()->whereKey($ids)->get(['id', 'title', 'slug']) as $campaign) {
            $id = (string) $campaign->getKey();
            $described[$id] = new ActivitySubjectDTO($id, $campaign->title, route('campaigns.show', $campaign->slug ?? $id, false));
        }

        return $described;
    }

    public function attributes(): array
    {
        return [$this->gameSystem->attribute()];
    }

    public function attributeValues(string $key): array
    {
        return $this->gameSystem->values($key);
    }

    /**
     * The campaigns that count, with what their facts are built from (no query per campaign):
     * completed and published.
     *
     * @return Builder<CampaignModel>
     */
    private function counted(): Builder
    {
        return CampaignModel::query()
            ->where('gametables_campaigns.is_published', true)
            ->where('gametables_campaigns.status', CampaignStatus::Completed->value)
            ->with([
                'gameSystem',
                'gameMasters',
                // Deactivated accounts too, as sessions keep theirs: whether they count is the consumer's choice
                'players' => static fn (BelongsToMany $players): BelongsToMany => $players->withTrashed(),
                'gameTables' => static fn (HasMany $sessions): HasMany => $sessions
                    ->where('status', TableStatus::Completed->value)
                    ->select(['id', 'campaign_id', 'starts_at', 'status']),
            ]);
    }

    /**
     * One fact per person and role: the creator, the roster and the direction as the module shows
     * it to anybody (its game masters with an account and a public name, the rule of the profile's
     * campaigns; a game master who chose not to show their name, or whoever created it from the
     * website without directing it, is never reported as a director). It took place at its last
     * completed session, or when it was created without any.
     *
     * @return list<ActivityFactDTO>
     */
    private static function factsOf(CampaignModel $campaign): array
    {
        $occurredAt = self::occurredAt($campaign);
        $attributes = GameSystemActivityAttribute::of($campaign->gameSystem);

        /** @var list<GameMasterModel> $gameMasters */
        $gameMasters = $campaign->gameMasters->all();
        /** @var list<UserModel> $players */
        $players = $campaign->players->all();

        $roles = [
            ActivityRole::Player->value => array_map(static fn (UserModel $player): string => (string) $player->getKey(), $players),
            ActivityRole::Director->value => array_map(
                static fn (GameMasterModel $gameMaster): ?string => $gameMaster->is_name_public ? $gameMaster->user_id : null,
                $gameMasters,
            ),
            ActivityRole::Creator->value => [$campaign->created_by],
        ];

        return ActivityFacts::of($roles, $occurredAt, $attributes);
    }

    private static function occurredAt(CampaignModel $campaign): DateTimeImmutable
    {
        $last = null;

        foreach ($campaign->gameTables as $session) {
            /** @var GameTableModel $session */
            $startsAt = $session->starts_at->toDateTimeImmutable();
            $last = $last === null || $startsAt > $last ? $startsAt : $last;
        }

        return $last ?? $campaign->created_at?->toDateTimeImmutable() ?? new DateTimeImmutable('@0');
    }
}
