<?php

declare(strict_types=1);

namespace Modules\GameTables\Infrastructure\Persistence\Eloquent\Repositories;

use DateTimeImmutable;
use Modules\GameTables\Domain\Entities\Campaign;
use Modules\GameTables\Domain\Enums\CampaignStatus;
use Modules\GameTables\Domain\Exceptions\CampaignNotFoundException;
use Modules\GameTables\Domain\Repositories\CampaignRepositoryInterface;
use Modules\GameTables\Domain\ValueObjects\CampaignId;
use Modules\GameTables\Domain\ValueObjects\GameSystemId;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\CampaignModel;

final readonly class EloquentCampaignRepository implements CampaignRepositoryInterface
{
    public function save(Campaign $campaign): void
    {
        CampaignModel::query()->updateOrCreate(
            ['id' => $campaign->id->value],
            $this->toArray($campaign),
        );
    }

    public function find(CampaignId $id): ?Campaign
    {
        $model = CampaignModel::query()->find($id->value);

        if ($model === null) {
            return null;
        }

        return $this->toEntity($model);
    }

    public function findOrFail(CampaignId $id): Campaign
    {
        $campaign = $this->find($id);

        if ($campaign === null) {
            throw CampaignNotFoundException::withId($id->value);
        }

        return $campaign;
    }

    public function delete(CampaignId $id): void
    {
        CampaignModel::query()->where('id', $id->value)->delete();
    }

    /**
     * @return array<Campaign>
     */
    public function getActive(): array
    {
        return CampaignModel::query()
            ->whereIn('status', [CampaignStatus::Recruiting->value, CampaignStatus::Active->value])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn (CampaignModel $model): Campaign => $this->toEntity($model))
            ->all();
    }

    /**
     * @return array<Campaign>
     */
    public function getByStatus(CampaignStatus $status): array
    {
        return CampaignModel::query()
            ->where('status', $status->value)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn (CampaignModel $model): Campaign => $this->toEntity($model))
            ->all();
    }

    /**
     * @return array<Campaign>
     */
    public function getByGameSystem(GameSystemId $gameSystemId): array
    {
        return CampaignModel::query()
            ->where('game_system_id', $gameSystemId->value)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn (CampaignModel $model): Campaign => $this->toEntity($model))
            ->all();
    }

    /**
     * @return array<Campaign>
     */
    public function getByCreator(string $userId): array
    {
        return CampaignModel::query()
            ->where('created_by', $userId)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn (CampaignModel $model): Campaign => $this->toEntity($model))
            ->all();
    }

    /**
     * @return array<Campaign>
     */
    public function all(): array
    {
        return CampaignModel::query()
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn (CampaignModel $model): Campaign => $this->toEntity($model))
            ->all();
    }

    public function findPublishedModelWithRelations(string $id): ?object
    {
        return CampaignModel::query()
            ->with([
                'gameSystem',
                'creator',
                'gameTables' => function ($query): void {
                    $query->where('is_published', true)
                        ->with(['gameSystem', 'creator', 'gameMasters.user', 'participants'])
                        ->orderBy('starts_at', 'asc');
                },
                'gameMasters.user',
            ])
            ->where('id', $id)
            ->where('is_published', true)
            ->first();
    }

    public function findPublishedModelBySlugWithRelations(string $slug): ?object
    {
        return CampaignModel::query()
            ->with([
                'gameSystem',
                'creator',
                'gameTables' => function ($query): void {
                    $query->where('is_published', true)
                        ->with(['gameSystem', 'creator', 'gameMasters.user', 'participants'])
                        ->orderBy('starts_at', 'asc');
                },
                'gameMasters.user',
            ])
            ->where('slug', $slug)
            ->where('is_published', true)
            ->first();
    }

    public function toEntity(CampaignModel $model): Campaign
    {
        return new Campaign(
            id: new CampaignId($model->id),
            gameSystemId: new GameSystemId($model->game_system_id),
            createdBy: $model->created_by,
            title: $model->title,
            status: $model->status,
            description: $model->description,
            frequency: $model->frequency,
            sessionCount: $model->session_count,
            currentSession: $model->current_session,
            acceptsNewPlayers: $model->accepts_new_players,
            maxPlayers: $model->max_players,
            isPublished: $model->is_published,
            createdAt: $model->created_at !== null
                ? new DateTimeImmutable($model->created_at->toDateTimeString())
                : null,
            updatedAt: $model->updated_at !== null
                ? new DateTimeImmutable($model->updated_at->toDateTimeString())
                : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function toArray(Campaign $campaign): array
    {
        return [
            'id' => $campaign->id->value,
            'game_system_id' => $campaign->gameSystemId->value,
            'created_by' => $campaign->createdBy,
            'title' => $campaign->title,
            'description' => $campaign->description,
            'frequency' => $campaign->frequency?->value,
            'status' => $campaign->status->value,
            'session_count' => $campaign->sessionCount,
            'current_session' => $campaign->currentSession,
            'accepts_new_players' => $campaign->acceptsNewPlayers,
            'max_players' => $campaign->maxPlayers,
            'is_published' => $campaign->isPublished,
        ];
    }
}
