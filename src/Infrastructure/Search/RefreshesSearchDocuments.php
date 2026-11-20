<?php

declare(strict_types=1);

namespace Modules\GameTables\Infrastructure\Search;

use App\Application\Search\Services\SearchIndexerInterface;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Database\Eloquent\Builder;
use Modules\GameTables\Application\Services\GenreCatalogInterface;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\CampaignModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameSystemModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameTableModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GenreModel;

/**
 * Keeps the search documents of tables and campaigns in line with their rows: saving refreshes the
 * document (the source drops it when the record is no longer listed), deleting forgets it, and renaming
 * a game system refreshes the listed tables and campaigns that show its name. The indexer queues the
 * work for after the commit and never throws.
 *
 * Deleting an account for good takes its tables and campaigns along through the foreign key, without
 * model events, so their listed ids are read before and forgotten after. A game system in use cannot
 * be deleted (the foreign key restricts it), so it never takes records along.
 *
 * Nothing else feeds the documents but the names of the genres: the table keeps their keys in a
 * column and the names come from the genre catalog, so renaming a genre refreshes the tables that
 * have it. Content warnings and game masters are not indexed.
 */
final class RefreshesSearchDocuments
{
    /**
     * Listed records of each account being deleted for good, by account id, until it is gone.
     *
     * @var array<string, array{tables: list<string>, campaigns: list<string>}>
     */
    private static array $recordsOfDeletedCreators = [];

    public static function register(): void
    {
        GameTableModel::saved(static function (GameTableModel $table): void {
            self::indexer()->refresh(GameTableSearchSource::TYPE, $table->id);
        });
        GameTableModel::deleted(static function (GameTableModel $table): void {
            self::indexer()->forget(GameTableSearchSource::TYPE, $table->id);
        });

        CampaignModel::saved(static function (CampaignModel $campaign): void {
            self::indexer()->refresh(CampaignSearchSource::TYPE, $campaign->id);
        });
        CampaignModel::deleted(static function (CampaignModel $campaign): void {
            self::indexer()->forget(CampaignSearchSource::TYPE, $campaign->id);
        });

        GameSystemModel::saved(static function (GameSystemModel $system): void {
            if ($system->wasChanged('name')) {
                self::refreshRecordsOf($system);
            }
        });

        // The documents carry the genres' names; campaign documents carry no genres
        GenreModel::saved(static function (GenreModel $genre): void {
            // A new genre is on no table yet
            if (! $genre->wasRecentlyCreated && $genre->wasChanged('name')) {
                // The catalog this request already read still has the old name
                app()->forgetInstance(GenreCatalogInterface::class);
                self::refreshTablesWith($genre);
            }
        });

        UserModel::forceDeleting(static function (UserModel $user): void {
            self::$recordsOfDeletedCreators[$user->id] = [
                'tables' => self::listedIds(GameTableModel::query()->publiclyListed()->where('created_by', $user->id)),
                'campaigns' => self::listedIds(CampaignModel::query()->publiclyListed()->where('created_by', $user->id)),
            ];
        });
        UserModel::forceDeleted(static function (UserModel $user): void {
            $records = self::$recordsOfDeletedCreators[$user->id] ?? ['tables' => [], 'campaigns' => []];
            unset(self::$recordsOfDeletedCreators[$user->id]);

            foreach ($records['tables'] as $id) {
                self::indexer()->forget(GameTableSearchSource::TYPE, $id);
            }

            foreach ($records['campaigns'] as $id) {
                self::indexer()->forget(CampaignSearchSource::TYPE, $id);
            }
        });
    }

    /**
     * @param  Builder<GameTableModel>|Builder<CampaignModel>  $query
     * @return list<string>
     */
    private static function listedIds(Builder $query): array
    {
        return array_values(array_map(strval(...), $query->pluck('id')->all()));
    }

    /**
     * One query per kind of record; only the listed ones have a document to rewrite.
     */
    private static function refreshRecordsOf(GameSystemModel $system): void
    {
        $indexer = self::indexer();

        foreach (GameTableModel::query()->publiclyListed()->where('game_system_id', $system->id)->pluck('id') as $id) {
            $indexer->refresh(GameTableSearchSource::TYPE, (string) $id);
        }

        foreach (CampaignModel::query()->publiclyListed()->where('game_system_id', $system->id)->pluck('id') as $id) {
            $indexer->refresh(CampaignSearchSource::TYPE, (string) $id);
        }
    }

    /**
     * The listed tables that have the genre, in one query
     */
    private static function refreshTablesWith(GenreModel $genre): void
    {
        $indexer = self::indexer();

        foreach (GameTableModel::query()->publiclyListed()->whereJsonContains('genres', $genre->key)->pluck('id') as $id) {
            $indexer->refresh(GameTableSearchSource::TYPE, (string) $id);
        }
    }

    private static function indexer(): SearchIndexerInterface
    {
        return resolve(SearchIndexerInterface::class);
    }
}
