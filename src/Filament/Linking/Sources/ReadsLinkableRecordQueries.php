<?php

declare(strict_types=1);

namespace Modules\GameTables\Filament\Linking\Sources;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * What the game table and campaign linkable record sources share: who the viewer is and how a
 * query narrows a title.
 */
trait ReadsLinkableRecordQueries
{
    /** Words of the query that are searched; any beyond are ignored. */
    private const int MAX_QUERY_TERMS = 6;

    /** Backslash escapes the LIKE wildcards; SQLite has no default escape character. */
    private const string TITLE_CONTAINS = "LOWER(title) LIKE ? ESCAPE '\\'";

    /**
     * The viewer's account, or null for a visitor (VISITOR, an id that is not an account, a
     * deactivated one, which is soft-deleted, or an anonymized one).
     */
    private function viewer(string $viewerId): ?UserModel
    {
        if (! Str::isUuid($viewerId)) {
            return null;
        }

        $viewer = UserModel::query()->whereNull('anonymized_at')->find($viewerId);

        return $viewer instanceof UserModel ? $viewer : null;
    }

    /**
     * Every lower-cased word of the query must appear in the title.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $records
     * @return Builder<TModel>
     */
    private function matchingTitle(Builder $records, string $query): Builder
    {
        $words = preg_split('/\s+/u', mb_strtolower(trim($query)), -1, PREG_SPLIT_NO_EMPTY);

        foreach (array_slice($words === false ? [] : $words, 0, self::MAX_QUERY_TERMS) as $word) {
            $records->whereRaw(self::TITLE_CONTAINS, ['%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $word).'%']);
        }

        return $records;
    }

    /**
     * Keys are uuids: anything else matches no row, and PostgreSQL rejects it in the query.
     *
     * @param  list<string>  $ids
     * @return list<string>
     */
    private function uuids(array $ids): array
    {
        return array_values(array_filter($ids, Str::isUuid(...)));
    }
}
