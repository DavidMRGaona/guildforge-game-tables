<?php

declare(strict_types=1);

namespace Modules\GameTables\Filament\Linking\Sources;

use App\Application\Authorization\Services\AuthorizationServiceInterface;
use App\Application\Linking\Contracts\FindsLinkableRecordsInBatches;
use App\Application\Linking\Contracts\FindsLinkableRecordsOfOrganizer;
use App\Application\Linking\DTOs\LinkableRecordDTO;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;
use Modules\GameTables\Domain\Enums\TableStatus;
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
        $now = now();
        $tables = fn (): Builder => $this->matchingTitle($this->visibleTo($viewer), $query);
        $found = $tables()->where('gametables_tables.starts_at', '>=', $now)->orderedByStart()->limit($limit)->get();

        if ($found->count() < $limit) {
            $found = $found->concat(
                $tables()->where('gametables_tables.starts_at', '<', $now)->orderedByStart('desc')->limit($limit - $found->count())->get(),
            );
        }

        return $this->toRecords($found, $viewer);
    }

    /**
     * The id is read in lower case, as the keys are stored; the record answers with the id asked,
     * as the contract wants.
     */
    public function find(string $id, string $viewerId): ?LinkableRecordDTO
    {
        $record = $this->findMany([strtolower($id)], $viewerId)[0] ?? null;

        return $record instanceof LinkableRecordDTO && $record->id !== $id
            ? new LinkableRecordDTO(type: $record->type, id: $id, title: $record->title, date: $record->date, panelUrl: $record->panelUrl)
            : $record;
    }

    public function findMany(array $ids, string $viewerId): array
    {
        $ids = $this->uuids($ids);

        if ($ids === []) {
            return [];
        }

        $viewer = $this->viewer($viewerId);

        return $this->toRecords($this->visibleTo($viewer)->whereKey($ids)->get(), $viewer);
    }

    /**
     * The tables the person created or directs (their own game master rows, or the campaign's
     * for a session, unless excluded from it; hidden names count, it is their own), among those
     * the public listing shows (published, neither draft nor cancelled) that have not ended:
     * neither completed nor past their start plus duration. A table being played counts, before
     * the upcoming ones. Never a played one: nobody links a booking to it.
     */
    public function organizedBy(string $userId, int $limit): array
    {
        if ($limit < 1) {
            return [];
        }

        $organized = static fn (): Builder => GameTableModel::query()
            ->publiclyListed()
            ->where('gametables_tables.status', '!=', TableStatus::Completed->value)
            ->where(fn (Builder $mine) => $mine
                ->where('gametables_tables.created_by', $userId)
                ->orWhere(fn (Builder $directed) => $directed->directedBy($userId)));
        $now = now();

        // Started ones are few: those that may still be running are read, and the end is worked
        // out here, the same on every database
        $longest = (int) GameTableModel::query()->max('duration_minutes');
        $running = $organized()
            ->where('gametables_tables.starts_at', '<', $now)
            ->where('gametables_tables.starts_at', '>=', $now->copy()->subMinutes($longest))
            ->orderedByStart()
            ->get()
            ->filter(static fn (GameTableModel $table): bool => $table->starts_at->copy()->addMinutes((int) $table->duration_minutes)->greaterThan($now));
        $upcoming = $organized()->where('gametables_tables.starts_at', '>=', $now)->orderedByStart()->limit($limit)->get();

        return array_slice($this->toRecords($running->concat($upcoming), null), 0, $limit);
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
     * Panel access is asked once for the whole answer, and only for an account.
     *
     * @param  Collection<int, GameTableModel>  $tables
     * @return list<LinkableRecordDTO>
     */
    private function toRecords(Collection $tables, ?UserModel $viewer): array
    {
        $editor = $viewer instanceof UserModel && $this->authorization->can($viewer, 'admin.access') ? $viewer : null;

        return array_values($tables->map(fn (GameTableModel $table): LinkableRecordDTO => new LinkableRecordDTO(
            type: self::TYPE,
            id: (string) $table->getKey(),
            title: $table->title,
            date: $table->starts_at->toImmutable(),
            panelUrl: $editor instanceof UserModel ? $this->panelUrl($table, $editor) : null,
        ))->all());
    }

    private function panelUrl(GameTableModel $table, UserModel $editor): ?string
    {
        if (! Gate::forUser($editor)->allows('update', $table)) {
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
