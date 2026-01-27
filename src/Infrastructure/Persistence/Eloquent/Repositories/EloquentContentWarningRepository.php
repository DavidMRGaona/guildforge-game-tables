<?php

declare(strict_types=1);

namespace Modules\GameTables\Infrastructure\Persistence\Eloquent\Repositories;

use DateTimeImmutable;
use Modules\GameTables\Domain\Entities\ContentWarning;
use Modules\GameTables\Domain\Enums\WarningSeverity;
use Modules\GameTables\Domain\Exceptions\ContentWarningNotFoundException;
use Modules\GameTables\Domain\Repositories\ContentWarningRepositoryInterface;
use Modules\GameTables\Domain\ValueObjects\ContentWarningId;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\ContentWarningModel;

final readonly class EloquentContentWarningRepository implements ContentWarningRepositoryInterface
{
    public function save(ContentWarning $contentWarning): void
    {
        ContentWarningModel::query()->updateOrCreate(
            ['id' => $contentWarning->id->value],
            $this->toArray($contentWarning),
        );
    }

    public function find(ContentWarningId $id): ?ContentWarning
    {
        $model = ContentWarningModel::query()->find($id->value);

        if ($model === null) {
            return null;
        }

        return $this->toEntity($model);
    }

    public function findOrFail(ContentWarningId $id): ContentWarning
    {
        $contentWarning = $this->find($id);

        if ($contentWarning === null) {
            throw ContentWarningNotFoundException::withId($id->value);
        }

        return $contentWarning;
    }

    public function findByName(string $name): ?ContentWarning
    {
        $model = ContentWarningModel::query()->where('name', $name)->first();

        if ($model === null) {
            return null;
        }

        return $this->toEntity($model);
    }

    public function delete(ContentWarningId $id): void
    {
        ContentWarningModel::query()->where('id', $id->value)->delete();
    }

    /**
     * @return array<ContentWarning>
     */
    public function getActive(): array
    {
        return ContentWarningModel::query()
            ->where('is_active', true)
            ->orderBy('severity')
            ->orderBy('label')
            ->get()
            ->map(fn (ContentWarningModel $model): ContentWarning => $this->toEntity($model))
            ->all();
    }

    /**
     * @return array<ContentWarning>
     */
    public function getBySeverity(WarningSeverity $severity): array
    {
        return ContentWarningModel::query()
            ->where('severity', $severity->value)
            ->orderBy('label')
            ->get()
            ->map(fn (ContentWarningModel $model): ContentWarning => $this->toEntity($model))
            ->all();
    }

    /**
     * @return array<ContentWarning>
     */
    public function all(): array
    {
        return ContentWarningModel::query()
            ->orderBy('severity')
            ->orderBy('label')
            ->get()
            ->map(fn (ContentWarningModel $model): ContentWarning => $this->toEntity($model))
            ->all();
    }

    public function existsByName(string $name): bool
    {
        return ContentWarningModel::query()->where('name', $name)->exists();
    }

    /**
     * @param array<ContentWarningId> $ids
     *
     * @return array<ContentWarning>
     */
    public function findByIds(array $ids): array
    {
        $idValues = array_map(fn (ContentWarningId $id): string => $id->value, $ids);

        return ContentWarningModel::query()
            ->whereIn('id', $idValues)
            ->get()
            ->map(fn (ContentWarningModel $model): ContentWarning => $this->toEntity($model))
            ->all();
    }

    public function toEntity(ContentWarningModel $model): ContentWarning
    {
        return new ContentWarning(
            id: new ContentWarningId($model->id),
            name: $model->name,
            label: $model->label,
            severity: $model->severity,
            isActive: $model->is_active,
            description: $model->description,
            icon: $model->icon,
            createdAt: $model->created_at !== null
                ? new DateTimeImmutable($model->created_at->toDateTimeString())
                : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function toArray(ContentWarning $contentWarning): array
    {
        return [
            'id' => $contentWarning->id->value,
            'name' => $contentWarning->name,
            'label' => $contentWarning->label,
            'description' => $contentWarning->description,
            'severity' => $contentWarning->severity->value,
            'icon' => $contentWarning->icon,
            'is_active' => $contentWarning->isActive,
        ];
    }
}
