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
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Modules\GameTables\Domain\Enums\ParticipantRole;
use Modules\GameTables\Domain\Enums\ParticipantStatus;
use Modules\GameTables\Domain\Enums\TableStatus;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameMasterModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameTableModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\ParticipantModel;

/**
 * Completed tables and campaign sessions as activity (core#774): its confirmed players played it,
 * its direction (its own game masters and those it inherits from its campaign) ran it and somebody
 * created it, all with the game system played.
 *
 * Only what any signed-in member can know exists: published tables, and for a campaign session a
 * published campaign too. Guests, spectators and game masters without an account are never
 * reported. Never reads the signed-in user: the same answer in a queued job as in a request.
 */
final readonly class SessionActivitySource implements ActivitySourceInterface, OffersActivityAttributes
{
    /** Stored by consumers with every fact: never changes. */
    public const string TYPE = 'game-tables.session';

    private const string CURSOR_SCOPE = 'session';

    public function __construct(
        private GameSystemActivityAttribute $gameSystem,
    ) {}

    public function type(): string
    {
        return self::TYPE;
    }

    public function kind(): ActivityKind
    {
        return ActivityKind::GameSession;
    }

    public function label(): string
    {
        return __('game-tables::messages.activity.session_label');
    }

    public function facts(string $subjectId): array
    {
        if (! Str::isUuid($subjectId)) {
            return [];
        }

        $table = $this->counted()->whereKey(strtolower($subjectId))->first();

        return $table instanceof GameTableModel ? self::factsOf($table) : [];
    }

    public function history(?string $cursor, int $limit): ActivityHistoryPageDTO
    {
        $after = $cursor === null ? null : ActivityCursor::read(self::CURSOR_SCOPE, $cursor);
        $limit = max(1, min($limit, ActivityHistoryPageDTO::MAX_LIMIT));

        $tables = $this->counted()
            ->when($after !== null, static fn (Builder $query): Builder => $query->where('gametables_tables.id', '>', $after))
            ->orderBy('gametables_tables.id')
            ->limit($limit + 1)
            ->get();

        $page = $tables->take($limit);
        $last = $page->last();

        return new ActivityHistoryPageDTO(
            subjects: array_values($page->map(static fn (GameTableModel $table): ActivitySubjectFactsDTO => new ActivitySubjectFactsDTO((string) $table->getKey(), self::factsOf($table)))->all()),
            nextCursor: $tables->count() > $limit && $last instanceof GameTableModel ? ActivityCursor::after(self::CURSOR_SCOPE, (string) $last->getKey()) : null,
        );
    }

    /**
     * Title and page of the tables the viewer may open: the table page's own rule (published),
     * which shows them to guests too.
     */
    public function describe(array $subjectIds, ?Authenticatable $viewer): array
    {
        $ids = array_values(array_unique(array_map(strtolower(...), array_filter($subjectIds, Str::isUuid(...)))));

        if ($ids === []) {
            return [];
        }

        $described = [];

        foreach (GameTableModel::query()->shownOnItsPage()->whereKey($ids)->get(['id', 'title', 'slug']) as $table) {
            $id = (string) $table->getKey();
            $described[$id] = new ActivitySubjectDTO($id, $table->title, route('gametables.show', $table->slug ?? $id, false));
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
     * The tables that count, with what their facts are built from (no query per table): completed,
     * published and, for a campaign session, of a published campaign.
     *
     * @return Builder<GameTableModel>
     */
    private function counted(): Builder
    {
        return GameTableModel::query()
            ->where('gametables_tables.is_published', true)
            ->where('gametables_tables.status', TableStatus::Completed->value)
            ->where(static fn (Builder $query): Builder => $query
                ->whereNull('gametables_tables.campaign_id')
                ->orWhereHas('campaign', static fn (Builder $campaign): Builder => $campaign->where('is_published', true)))
            ->with([
                'gameSystem',
                'gameMasters',
                'campaign.gameMasters',
                'participants' => static fn (HasMany $participants): HasMany => $participants
                    ->where('role', ParticipantRole::Player->value)
                    ->where('status', ParticipantStatus::Confirmed->value)
                    ->whereNotNull('user_id'),
            ]);
    }

    /**
     * One fact per person and role: the creator, every director with an account (the module's
     * rule for a table's direction, read from the loaded relations) and every confirmed player.
     * It took place when it started.
     *
     * @return list<ActivityFactDTO>
     */
    private static function factsOf(GameTableModel $table): array
    {
        $occurredAt = $table->starts_at->toDateTimeImmutable();
        $attributes = GameSystemActivityAttribute::of($table->gameSystem);

        /** @var list<ParticipantModel> $players */
        $players = $table->participants->all();

        $roles = [
            ActivityRole::Player->value => array_map(static fn (ParticipantModel $player): ?string => $player->user_id, $players),
            ActivityRole::Director->value => array_map(
                static fn (GameMasterModel $gameMaster): ?string => $gameMaster->user_id,
                $table->loadedEffectiveGameMasters(),
            ),
            ActivityRole::Creator->value => [$table->created_by],
        ];

        return ActivityFacts::of($roles, $occurredAt, $attributes);
    }
}
