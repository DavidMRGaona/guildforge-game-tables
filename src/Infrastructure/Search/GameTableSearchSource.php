<?php

declare(strict_types=1);

namespace Modules\GameTables\Infrastructure\Search;

use App\Application\Search\Contracts\IndexedSearchSourceInterface;
use App\Application\Search\DTOs\SearchDocumentDTO;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Modules\GameTables\Domain\Enums\Genre;
use Modules\GameTables\Domain\Enums\TableStatus;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameTableModel;

/**
 * The tables of the public listing, past ones included (the listing only cuts them by date). Notes and
 * moderation notes are private and never reach the index.
 */
final class GameTableSearchSource implements IndexedSearchSourceInterface
{
    public const string TYPE = 'gametables.tables';

    public function type(): string
    {
        return self::TYPE;
    }

    public function label(): string
    {
        return __('game-tables::messages.search.tables.label');
    }

    public function itemLabel(): string
    {
        return __('game-tables::messages.search.tables.item');
    }

    public function pastLabel(): string
    {
        return __('game-tables::messages.search.tables.past');
    }

    public function document(string $id): ?SearchDocumentDTO
    {
        // Keys are uuids: anything else matches no row, and PostgreSQL rejects it in the query
        if (! Str::isUuid($id)) {
            return null;
        }

        $table = $this->listed()->whereKey($id)->first();

        return $table instanceof GameTableModel ? $this->toDocument($table) : null;
    }

    /**
     * @return iterable<SearchDocumentDTO>
     */
    public function documents(): iterable
    {
        foreach ($this->listed()->lazyById(200) as $table) {
            yield $this->toDocument($table);
        }
    }

    /**
     * @return Builder<GameTableModel>
     */
    private function listed(): Builder
    {
        return GameTableModel::query()->publiclyListed()->with('gameSystem');
    }

    private function toDocument(GameTableModel $table): SearchDocumentDTO
    {
        $system = $table->gameSystem?->name;
        $genres = array_map(
            static fn (Genre $genre): string => $genre->label(),
            array_values(array_filter(array_map(Genre::tryFrom(...), $table->genres ?? []))),
        );
        $startsAt = $table->starts_at->toImmutable();

        return new SearchDocumentDTO(
            id: $table->id,
            title: $table->title,
            body: SearchText::plain($table->synopsis),
            url: route('gametables.show', $table->slug ?? $table->id, false),
            keywords: SearchText::join([$system, ...$genres], ' '),
            meta: SearchText::join([$system, $table->location], ' · '),
            imagePublicId: $table->image_public_id,
            startsAt: $startsAt,
            endsAt: $startsAt->addMinutes($table->duration_minutes),
            // Over by status; being over by date comes from the dates when searching
            finished: $table->status === TableStatus::Completed,
        );
    }
}
