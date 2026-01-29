<?php

declare(strict_types=1);

namespace Modules\GameTables\Infrastructure\Persistence\Eloquent\Repositories;

use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Support\Collection;
use Modules\GameTables\Domain\Entities\GameTable;
use Modules\GameTables\Domain\Enums\Genre;
use Modules\GameTables\Domain\Enums\SafetyTool;
use Modules\GameTables\Domain\Enums\TableStatus;
use Modules\GameTables\Domain\Exceptions\GameTableNotFoundException;
use Modules\GameTables\Domain\Repositories\GameTableRepositoryInterface;
use Modules\GameTables\Domain\ValueObjects\CampaignId;
use Modules\GameTables\Domain\ValueObjects\GameSystemId;
use Modules\GameTables\Domain\ValueObjects\GameTableId;
use Modules\GameTables\Domain\ValueObjects\TimeSlot;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameTableModel;

final readonly class EloquentGameTableRepository implements GameTableRepositoryInterface
{
    public function save(GameTable $gameTable): void
    {
        $model = GameTableModel::query()->updateOrCreate(
            ['id' => $gameTable->id->value],
            $this->toArray($gameTable),
        );

        // Sync content warnings
        if ($gameTable->contentWarningIds !== null) {
            $model->contentWarnings()->sync($gameTable->contentWarningIds);
        } else {
            $model->contentWarnings()->detach();
        }
    }

    public function find(GameTableId $id): ?GameTable
    {
        $model = GameTableModel::query()->find($id->value);

        if ($model === null) {
            return null;
        }

        return $this->toEntity($model);
    }

    public function findOrFail(GameTableId $id): GameTable
    {
        $gameTable = $this->find($id);

        if ($gameTable === null) {
            throw GameTableNotFoundException::withId($id->value);
        }

        return $gameTable;
    }

    public function delete(GameTableId $id): void
    {
        GameTableModel::query()->where('id', $id->value)->delete();
    }

    /**
     * @return array<GameTable>
     */
    public function getPublished(): array
    {
        return GameTableModel::query()
            ->where('is_published', true)
            ->orderBy('starts_at', 'asc')
            ->get()
            ->map(fn (GameTableModel $model): GameTable => $this->toEntity($model))
            ->all();
    }

    /**
     * @return array<GameTable>
     */
    public function getByStatus(TableStatus $status): array
    {
        return GameTableModel::query()
            ->where('status', $status->value)
            ->orderBy('starts_at', 'asc')
            ->get()
            ->map(fn (GameTableModel $model): GameTable => $this->toEntity($model))
            ->all();
    }

    /**
     * @return array<GameTable>
     */
    public function getByGameSystem(GameSystemId $gameSystemId): array
    {
        return GameTableModel::query()
            ->where('game_system_id', $gameSystemId->value)
            ->orderBy('starts_at', 'asc')
            ->get()
            ->map(fn (GameTableModel $model): GameTable => $this->toEntity($model))
            ->all();
    }

    /**
     * @return array<GameTable>
     */
    public function getByCampaign(CampaignId $campaignId): array
    {
        return GameTableModel::query()
            ->where('campaign_id', $campaignId->value)
            ->orderBy('starts_at', 'asc')
            ->get()
            ->map(fn (GameTableModel $model): GameTable => $this->toEntity($model))
            ->all();
    }

    /**
     * @return array<GameTable>
     */
    public function getByEvent(string $eventId): array
    {
        return GameTableModel::query()
            ->where('event_id', $eventId)
            ->orderBy('starts_at', 'asc')
            ->get()
            ->map(fn (GameTableModel $model): GameTable => $this->toEntity($model))
            ->all();
    }

    /**
     * @return array<GameTable>
     */
    public function getByCreator(string $userId): array
    {
        return GameTableModel::query()
            ->where('created_by', $userId)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn (GameTableModel $model): GameTable => $this->toEntity($model))
            ->all();
    }

    /**
     * @return array<GameTable>
     */
    public function getUpcoming(int $limit = 10): array
    {
        return GameTableModel::query()
            ->where('is_published', true)
            ->where('starts_at', '>=', now())
            ->whereIn('status', [
                TableStatus::Scheduled->value,
                TableStatus::Full->value,
            ])
            ->orderBy('starts_at', 'asc')
            ->limit($limit)
            ->get()
            ->map(fn (GameTableModel $model): GameTable => $this->toEntity($model))
            ->all();
    }

    /**
     * @return array<GameTable>
     */
    public function all(): array
    {
        return GameTableModel::query()
            ->orderBy('starts_at', 'desc')
            ->get()
            ->map(fn (GameTableModel $model): GameTable => $this->toEntity($model))
            ->all();
    }

    public function findPublishedModelWithRelations(string $id): ?object
    {
        return GameTableModel::query()
            ->with([
                'gameSystem',
                'creator',
                'campaign',
                'event',
                'contentWarnings',
                'participants.user',
                'gameMasters.user',
            ])
            ->where('id', $id)
            ->where('is_published', true)
            ->first();
    }

    public function findPublishedModelBySlugWithRelations(string $slug): ?object
    {
        return GameTableModel::query()
            ->with([
                'gameSystem',
                'creator',
                'campaign',
                'event',
                'contentWarnings',
                'participants.user',
                'gameMasters.user',
            ])
            ->where('slug', $slug)
            ->where('is_published', true)
            ->first();
    }

    /**
     * @return Collection<int, object>
     */
    public function getPublishedModelsInDateRange(
        DateTimeInterface $from,
        DateTimeInterface $to,
    ): Collection {
        return GameTableModel::query()
            ->with(['gameSystem', 'creator'])
            ->withCount('participants')
            ->where('is_published', true)
            ->whereNotIn('status', [TableStatus::Cancelled->value, TableStatus::Draft->value])
            ->whereBetween('starts_at', [$from, $to])
            ->orderBy('starts_at', 'asc')
            ->get();
    }

    public function toEntity(GameTableModel $model): GameTable
    {
        // Load content warning IDs
        $contentWarningIds = $model->contentWarnings()->pluck('id')->all();

        return new GameTable(
            id: new GameTableId($model->id),
            gameSystemId: new GameSystemId($model->game_system_id),
            createdBy: $model->created_by,
            title: $model->title,
            slug: $model->slug,
            timeSlot: new TimeSlot(
                new DateTimeImmutable($model->starts_at->toDateTimeString()),
                $model->duration_minutes,
            ),
            tableType: $model->table_type,
            tableFormat: $model->table_format,
            status: $model->status,
            minPlayers: $model->min_players,
            maxPlayers: $model->max_players,
            campaignId: $model->campaign_id !== null ? new CampaignId($model->campaign_id) : null,
            eventId: $model->event_id,
            maxSpectators: $model->max_spectators,
            synopsis: $model->synopsis,
            location: $model->location,
            onlineUrl: $model->online_url,
            minimumAge: $model->minimum_age,
            language: $model->language,
            genres: $model->genres !== null
                ? array_map(fn (string $g): Genre => Genre::from($g), $model->genres)
                : null,
            tone: $model->tone,
            experienceLevel: $model->experience_level,
            characterCreation: $model->character_creation,
            safetyTools: $model->safety_tools !== null
                ? array_map(fn (string $s): SafetyTool => SafetyTool::from($s), $model->safety_tools)
                : null,
            contentWarningIds: count($contentWarningIds) > 0 ? $contentWarningIds : null,
            customWarnings: $model->custom_warnings,
            registrationType: $model->registration_type,
            membersEarlyAccessDays: $model->members_early_access_days,
            registrationOpensAt: $model->registration_opens_at !== null
                ? new DateTimeImmutable($model->registration_opens_at->toDateTimeString())
                : null,
            registrationClosesAt: $model->registration_closes_at !== null
                ? new DateTimeImmutable($model->registration_closes_at->toDateTimeString())
                : null,
            autoConfirm: $model->auto_confirm,
            acceptsRegistrationsInProgress: $model->accepts_registrations_in_progress ?? false,
            isPublished: $model->is_published,
            publishedAt: $model->published_at !== null
                ? new DateTimeImmutable($model->published_at->toDateTimeString())
                : null,
            notes: $model->notes,
            createdAt: $model->created_at !== null
                ? new DateTimeImmutable($model->created_at->toDateTimeString())
                : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function toArray(GameTable $gameTable): array
    {
        return [
            'id' => $gameTable->id->value,
            'game_system_id' => $gameTable->gameSystemId->value,
            'campaign_id' => $gameTable->campaignId?->value,
            'event_id' => $gameTable->eventId,
            'created_by' => $gameTable->createdBy,
            'title' => $gameTable->title,
            'slug' => $gameTable->slug,
            'starts_at' => $gameTable->timeSlot->startsAt,
            'duration_minutes' => $gameTable->timeSlot->durationMinutes,
            'table_type' => $gameTable->tableType->value,
            'table_format' => $gameTable->tableFormat->value,
            'status' => $gameTable->status->value,
            'min_players' => $gameTable->minPlayers,
            'max_players' => $gameTable->maxPlayers,
            'max_spectators' => $gameTable->maxSpectators,
            'synopsis' => $gameTable->synopsis,
            'location' => $gameTable->location,
            'online_url' => $gameTable->onlineUrl,
            'minimum_age' => $gameTable->minimumAge,
            'language' => $gameTable->language,
            'genres' => $gameTable->genres !== null
                ? array_map(fn (Genre $g): string => $g->value, $gameTable->genres)
                : null,
            'tone' => $gameTable->tone?->value,
            'experience_level' => $gameTable->experienceLevel?->value,
            'character_creation' => $gameTable->characterCreation?->value,
            'safety_tools' => $gameTable->safetyTools !== null
                ? array_map(fn (SafetyTool $s): string => $s->value, $gameTable->safetyTools)
                : null,
            'custom_warnings' => $gameTable->customWarnings,
            'registration_type' => $gameTable->registrationType->value,
            'members_early_access_days' => $gameTable->membersEarlyAccessDays,
            'registration_opens_at' => $gameTable->registrationOpensAt,
            'registration_closes_at' => $gameTable->registrationClosesAt,
            'auto_confirm' => $gameTable->autoConfirm,
            'accepts_registrations_in_progress' => $gameTable->acceptsRegistrationsInProgress,
            'is_published' => $gameTable->isPublished,
            'published_at' => $gameTable->publishedAt,
            'notes' => $gameTable->notes,
        ];
    }
}
