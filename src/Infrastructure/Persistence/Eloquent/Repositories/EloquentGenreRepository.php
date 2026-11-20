<?php

declare(strict_types=1);

namespace Modules\GameTables\Infrastructure\Persistence\Eloquent\Repositories;

use Modules\GameTables\Domain\Entities\Genre;
use Modules\GameTables\Domain\Enums\GenreColor;
use Modules\GameTables\Domain\Repositories\GenreRepositoryInterface;
use Modules\GameTables\Domain\ValueObjects\GenreId;
use Modules\GameTables\Domain\ValueObjects\GenreKey;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GenreModel;

final readonly class EloquentGenreRepository implements GenreRepositoryInterface
{
    public function save(Genre $genre): void
    {
        GenreModel::query()->updateOrCreate(
            ['id' => $genre->id->value],
            [
                'key' => $genre->key->value,
                'name' => $genre->name,
                'color' => $genre->color->value,
                'sort_order' => $genre->sortOrder,
                'is_active' => $genre->isActive,
            ],
        );
    }

    public function findByKey(GenreKey $key): ?Genre
    {
        $model = GenreModel::query()->where('key', $key->value)->first();

        return $model instanceof GenreModel ? $this->toEntity($model) : null;
    }

    public function all(): array
    {
        $genres = [];

        foreach (GenreModel::query()->orderBy('sort_order')->orderBy('name')->get() as $model) {
            $genre = $this->toEntity($model);

            if ($genre instanceof Genre) {
                $genres[] = $genre;
            }
        }

        return $genres;
    }

    /**
     * Null for a row whose key was written by other means and is not a key: it can never
     * match what a table stores
     */
    private function toEntity(GenreModel $model): ?Genre
    {
        $key = GenreKey::tryFrom($model->key);

        if (! $key instanceof GenreKey) {
            return null;
        }

        return new Genre(
            id: GenreId::fromString($model->id),
            key: $key,
            name: $model->name,
            color: GenreColor::tryFrom($model->color) ?? GenreColor::default(),
            sortOrder: $model->sort_order,
            isActive: $model->is_active,
        );
    }
}
