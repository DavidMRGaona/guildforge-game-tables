<?php

declare(strict_types=1);

namespace Modules\GameTables\Infrastructure\Persistence\Eloquent\Repositories;

use DateTimeImmutable;
use Modules\GameTables\Domain\Entities\GameSystem;
use Modules\GameTables\Domain\Enums\TableStatus;
use Modules\GameTables\Domain\Exceptions\GameSystemNotFoundException;
use Modules\GameTables\Domain\Repositories\GameSystemRepositoryInterface;
use Modules\GameTables\Domain\ValueObjects\GameSystemId;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameSystemModel;

final readonly class EloquentGameSystemRepository implements GameSystemRepositoryInterface
{
    public function save(GameSystem $gameSystem): void
    {
        GameSystemModel::query()->updateOrCreate(
            ['id' => $gameSystem->id->value],
            $this->toArray($gameSystem),
        );
    }

    public function find(GameSystemId $id): ?GameSystem
    {
        $model = GameSystemModel::query()->find($id->value);

        if ($model === null) {
            return null;
        }

        return $this->toEntity($model);
    }

    public function findOrFail(GameSystemId $id): GameSystem
    {
        $gameSystem = $this->find($id);

        if ($gameSystem === null) {
            throw GameSystemNotFoundException::withId($id->value);
        }

        return $gameSystem;
    }

    public function findBySlug(string $slug): ?GameSystem
    {
        $model = GameSystemModel::query()->where('slug', $slug)->first();

        if ($model === null) {
            return null;
        }

        return $this->toEntity($model);
    }

    public function delete(GameSystemId $id): void
    {
        GameSystemModel::query()->where('id', $id->value)->delete();
    }

    /**
     * @return array<GameSystem>
     */
    public function getActive(): array
    {
        return GameSystemModel::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(fn (GameSystemModel $model): GameSystem => $this->toEntity($model))
            ->all();
    }

    /**
     * @return array<GameSystem>
     */
    public function all(): array
    {
        return GameSystemModel::query()
            ->orderBy('name')
            ->get()
            ->map(fn (GameSystemModel $model): GameSystem => $this->toEntity($model))
            ->all();
    }

    public function existsBySlug(string $slug): bool
    {
        return GameSystemModel::query()->where('slug', $slug)->exists();
    }

    /**
     * @return array<array{id: string, name: string, count: int}>
     */
    public function getActiveWithPublishedTableCount(): array
    {
        return GameSystemModel::query()
            ->where('is_active', true)
            ->withCount(['gameTables' => function ($query): void {
                $query->where('is_published', true)
                    ->whereNotIn('status', [TableStatus::Cancelled->value, TableStatus::Draft->value]);
            }])
            ->orderBy('name')
            ->get()
            ->filter(fn (GameSystemModel $system): bool => $system->game_tables_count > 0)
            ->map(fn (GameSystemModel $system): array => [
                'id' => $system->id,
                'name' => $system->name,
                'count' => $system->game_tables_count,
            ])
            ->values()
            ->all();
    }

    public function toEntity(GameSystemModel $model): GameSystem
    {
        return new GameSystem(
            id: new GameSystemId($model->id),
            name: $model->name,
            slug: $model->slug,
            isActive: $model->is_active,
            description: $model->description,
            publisher: $model->publisher?->name,
            edition: $model->edition,
            year: $model->year,
            logoUrl: $model->logo_url,
            websiteUrl: $model->website_url,
            createdAt: $model->created_at !== null
                ? new DateTimeImmutable($model->created_at->toDateTimeString())
                : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function toArray(GameSystem $gameSystem): array
    {
        return [
            'id' => $gameSystem->id->value,
            'name' => $gameSystem->name,
            'slug' => $gameSystem->slug,
            'description' => $gameSystem->description,
            'publisher' => $gameSystem->publisher,
            'edition' => $gameSystem->edition,
            'year' => $gameSystem->year,
            'logo_url' => $gameSystem->logoUrl,
            'website_url' => $gameSystem->websiteUrl,
            'is_active' => $gameSystem->isActive,
        ];
    }
}
