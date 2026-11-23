<?php

declare(strict_types=1);

namespace Modules\GameTables\Filament\Linking\Sources;

use App\Application\Authorization\Services\AuthorizationServiceInterface;
use App\Application\Linking\Contracts\FindsLinkableRecordsInBatches;
use App\Application\Linking\Contracts\FindsLinkableRecordsOfOrganizer;
use App\Application\Linking\DTOs\LinkableRecordDTO;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Modules\GameTables\Filament\Resources\GameTableResource;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameTableModel;

/**
 * Game tables (one-shots and campaign sessions alike) as linkable records, so other modules can
 * link their data to a table without depending on this module.
 *
 * Published tables are what their page shows, so every viewer finds them; drafts and tables
 * waiting for approval only for who views any table in the panel (gametables.view) and for who
 * created them, as GameTablePolicy::view() says. The panel URL is the edit page, only for who may
 * edit the table. Lists start with the next table to be played, then the latest played.
 */
final readonly class GameTableLinkableRecordSource implements FindsLinkableRecordsInBatches, FindsLinkableRecordsOfOrganizer
{
    use ReadsLinkableRecordQueries;

    public const string TYPE = 'game-table';

    private const string VIEW_PERMISSION = 'gametables:gametables.view';

    public function __construct(private AuthorizationServiceInterface $authorization) {}

    public function type(): string
    {
        return self::TYPE;
    }

    public function label(): string
    {
        return __('game-tables::messages.linking.game_table');
    }

    public function search(string $query, string $viewerId, int $limit): array
    {
        if ($limit < 1) {
            return [];
        }

        $viewer = $this->viewer($viewerId);

        return $this->soonestFirst(fn (): Builder => $this->matchingTitle($this->visibleTo($viewer), $query), $limit, $viewer);
    }

    public function find(string $id, string $viewerId): ?LinkableRecordDTO
    {
        return $this->findMany([$id], $viewerId)[0] ?? null;
    }

    public function findMany(array $ids, string $viewerId): array
    {
        $ids = $this->uuids($ids);

        if ($ids === []) {
            return [];
        }

        $viewer = $this->viewer($viewerId);

        return array_values($this->visibleTo($viewer)->whereKey($ids)->get()
            ->map(fn (GameTableModel $table): LinkableRecordDTO => $this->toRecord($table, $viewer))
            ->all());
    }

    /**
     * The tables the person created or directs (their own game master rows, or the campaign's
     * for a session, unless excluded from it; hidden names count, it is their own), among those
     * the public listing shows: published, neither draft nor cancelled.
     */
    public function organizedBy(string $userId, int $limit): array
    {
        if ($limit < 1) {
            return [];
        }

        return $this->soonestFirst(fn (): Builder => GameTableModel::query()
            ->publiclyListed()
            ->where(fn (Builder $organized) => $organized
                ->where('gametables_tables.created_by', $userId)
                ->orWhere(fn (Builder $directed) => $directed->directedBy($userId))), $limit, null);
    }

    /**
     * @return Builder<GameTableModel>
     */
    private function visibleTo(?UserModel $viewer): Builder
    {
        $tables = GameTableModel::query();

        if ($viewer instanceof UserModel && $this->authorization->can($viewer, self::VIEW_PERMISSION)) {
            return $tables;
        }

        return $tables->where(fn (Builder $visible) => $visible
            ->shownOnItsPage()
            ->when($viewer instanceof UserModel, fn (Builder $own) => $own->orWhere('gametables_tables.created_by', $viewer?->id)));
    }

    /**
     * Upcoming tables by start, then past ones from the latest, at most $limit in all.
     *
     * @param  \Closure(): Builder<GameTableModel>  $tables
     * @return list<LinkableRecordDTO>
     */
    private function soonestFirst(\Closure $tables, int $limit, ?UserModel $viewer): array
    {
        $now = now();
        $found = $tables()->where('gametables_tables.starts_at', '>=', $now)->orderedByStart()->limit($limit)->get();

        if ($found->count() < $limit) {
            $found = $found->concat(
                $tables()->where('gametables_tables.starts_at', '<', $now)->orderedByStart('desc')->limit($limit - $found->count())->get(),
            );
        }

        return array_values($found->map(fn (GameTableModel $table): LinkableRecordDTO => $this->toRecord($table, $viewer))->all());
    }

    private function toRecord(GameTableModel $table, ?UserModel $viewer): LinkableRecordDTO
    {
        return new LinkableRecordDTO(
            type: self::TYPE,
            id: (string) $table->getKey(),
            title: $table->title,
            date: $table->starts_at->toImmutable(),
            panelUrl: $this->panelUrl($table, $viewer),
        );
    }

    private function panelUrl(GameTableModel $table, ?UserModel $viewer): ?string
    {
        if (! $viewer instanceof UserModel
            || ! $this->authorization->can($viewer, 'admin.access')
            || ! Gate::forUser($viewer)->allows('update', $table)) {
            return null;
        }

        try {
            return GameTableResource::getUrl('edit', ['record' => $table]);
        } catch (\Throwable) {
            // The panel is not built here (a queued job, a request outside it): no link, the record still shows
            return null;
        }
    }
}
