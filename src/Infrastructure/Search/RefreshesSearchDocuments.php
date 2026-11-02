<?php

declare(strict_types=1);

namespace Modules\GameTables\Infrastructure\Search;

use App\Application\Search\Services\SearchIndexerInterface;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\CampaignModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameSystemModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameTableModel;

/**
 * Keeps the search documents of tables and campaigns in line with their rows: saving refreshes the
 * document (the source drops it when the record is no longer listed), deleting forgets it, and renaming
 * a game system refreshes the listed tables and campaigns that show its name. The indexer queues the
 * work for after the commit and never throws.
 *
 * Nothing else feeds the documents: genres are a column of the table, and content warnings and game
 * masters are not indexed.
 */
final class RefreshesSearchDocuments
{
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

    private static function indexer(): SearchIndexerInterface
    {
        return resolve(SearchIndexerInterface::class);
    }
}
