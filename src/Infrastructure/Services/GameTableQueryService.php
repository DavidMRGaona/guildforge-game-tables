<?php

declare(strict_types=1);

namespace Modules\GameTables\Infrastructure\Services;

use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use Modules\GameTables\Application\DTOs\GameMasterResponseDTO;
use Modules\GameTables\Application\DTOs\GameTableListDTO;
use Modules\GameTables\Application\DTOs\GameTableResponseDTO;
use Modules\GameTables\Application\DTOs\ParticipantResponseDTO;
use Modules\GameTables\Application\Services\GameTableQueryServiceInterface;
use Modules\GameTables\Domain\Enums\GameMasterRole;
use Modules\GameTables\Domain\Enums\Genre;
use Modules\GameTables\Domain\Enums\SafetyTool;
use Modules\GameTables\Domain\Enums\TableStatus;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameMasterModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameSystemModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameTableModel;

final class GameTableQueryService implements GameTableQueryServiceInterface
{
    public function getPublishedTablesPaginated(
        int $page,
        int $perPage,
        ?array $gameSystemIds = null,
        ?string $format = null,
        ?string $status = null,
        ?string $eventId = null,
    ): array {
        $query = $this->buildPublishedQuery($gameSystemIds, $format, $status, $eventId);

        $tables = $query
            ->orderBy('starts_at', 'asc')
            ->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->get();

        return $tables->map(fn (GameTableModel $table) => $this->toListDTO($table))->all();
    }

    public function getPublishedTablesTotal(
        ?array $gameSystemIds = null,
        ?string $format = null,
        ?string $status = null,
        ?string $eventId = null,
    ): int {
        return $this->buildPublishedQuery($gameSystemIds, $format, $status, $eventId)->count();
    }

    public function getUpcomingTables(\DateTimeInterface $from, \DateTimeInterface $to): array
    {
        $tables = GameTableModel::query()
            ->with(['gameSystem', 'creator'])
            ->withCount('participants')
            ->where('is_published', true)
            ->whereNotIn('status', [TableStatus::Cancelled->value, TableStatus::Draft->value])
            ->whereBetween('starts_at', [$from, $to])
            ->orderBy('starts_at', 'asc')
            ->get();

        return $tables->map(fn (GameTableModel $table) => $this->toListDTO($table))->all();
    }

    public function findPublished(string $id): ?GameTableResponseDTO
    {
        $table = GameTableModel::query()
            ->with(['gameSystem', 'creator', 'campaign', 'event', 'contentWarnings', 'participants.user', 'gameMasters.user'])
            ->where('id', $id)
            ->where('is_published', true)
            ->first();

        if ($table === null) {
            return null;
        }

        return $this->toResponseDTO($table);
    }

    public function getGameSystemsWithTables(): array
    {
        return GameSystemModel::query()
            ->where('is_active', true)
            ->withCount(['gameTables' => function ($query): void {
                $query->where('is_published', true)
                    ->whereNotIn('status', [TableStatus::Cancelled->value, TableStatus::Draft->value]);
            }])
            ->orderBy('name')
            ->get()
            ->filter(fn (GameSystemModel $system) => $system->game_tables_count > 0)
            ->map(fn (GameSystemModel $system) => [
                'id' => $system->id,
                'name' => $system->name,
                'count' => $system->game_tables_count,
            ])
            ->values()
            ->all();
    }

    public function getEventsWithTables(): array
    {
        return EventModel::query()
            ->where('is_published', true)
            ->where('end_date', '>=', now())
            ->withCount(['gameTables' => function ($query): void {
                $query->where('is_published', true)
                    ->whereNotIn('status', [TableStatus::Cancelled->value, TableStatus::Draft->value]);
            }])
            ->orderBy('start_date')
            ->get()
            ->filter(fn (EventModel $event) => $event->game_tables_count > 0)
            ->map(fn (EventModel $event) => [
                'id' => $event->id,
                'title' => $event->title,
                'count' => $event->game_tables_count,
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<string>|null  $gameSystemIds
     */
    private function buildPublishedQuery(
        ?array $gameSystemIds = null,
        ?string $format = null,
        ?string $status = null,
        ?string $eventId = null,
    ): \Illuminate\Database\Eloquent\Builder {
        $query = GameTableModel::query()
            ->with(['gameSystem', 'creator', 'event', 'gameMasters.user'])
            ->withCount('participants')
            ->where('is_published', true)
            ->whereNotIn('status', [TableStatus::Cancelled->value, TableStatus::Draft->value]);

        // When browsing by event, show all tables (event page controls visibility).
        // When browsing general listing, only show future tables.
        if ($eventId === null) {
            $query->where('starts_at', '>=', now());
        }

        if ($gameSystemIds !== null && count($gameSystemIds) > 0) {
            $query->whereIn('game_system_id', $gameSystemIds);
        }

        if ($format !== null) {
            $query->where('table_format', $format);
        }

        if ($status !== null) {
            $query->where('status', $status);
        }

        if ($eventId !== null) {
            $query->where('event_id', $eventId);
        }

        return $query;
    }

    private function toListDTO(GameTableModel $table): GameTableListDTO
    {
        $mainGm = $table->gameMasters->firstWhere('role', GameMasterRole::Main);
        $mainGameMasterName = $mainGm?->display_name ?? $table->creator?->name ?? '';

        return new GameTableListDTO(
            id: $table->id,
            title: $table->title,
            gameSystemName: $table->gameSystem?->name ?? '',
            startsAt: $table->starts_at,
            durationMinutes: $table->duration_minutes,
            tableFormat: $table->table_format,
            tableType: $table->table_type,
            status: $table->status,
            location: $table->location,
            onlineUrl: $table->online_url,
            minPlayers: $table->min_players,
            maxPlayers: $table->max_players,
            currentPlayers: $table->participants_count ?? 0,
            isPublished: $table->is_published,
            creatorName: $table->creator?->name ?? '',
            mainGameMasterName: $mainGameMasterName,
            eventId: $table->event_id,
            eventTitle: $table->event?->title,
        );
    }

    private function toResponseDTO(GameTableModel $table): GameTableResponseDTO
    {
        $participants = $table->participants->map(fn ($p) => new ParticipantResponseDTO(
            id: $p->id,
            gameTableId: $p->game_table_id,
            userId: $p->user_id,
            userName: $p->user?->name ?? '',
            role: $p->role,
            status: $p->status,
            waitingListPosition: $p->waiting_list_position,
            notes: $p->notes,
            confirmedAt: $p->confirmed_at,
            createdAt: $p->created_at,
        ))->all();

        $gameMasters = $table->gameMasters->map(
            fn (GameMasterModel $gm) => GameMasterResponseDTO::fromModel($gm)
        )->all();

        return new GameTableResponseDTO(
            id: $table->id,
            title: $table->title,
            synopsis: $table->synopsis,
            gameSystemId: $table->game_system_id,
            gameSystemName: $table->gameSystem?->name ?? '',
            campaignId: $table->campaign_id,
            campaignTitle: $table->campaign?->title,
            eventId: $table->event_id,
            eventTitle: $table->event?->title,
            createdBy: $table->created_by,
            creatorName: $table->creator?->name ?? '',
            tableType: $table->table_type,
            tableFormat: $table->table_format,
            status: $table->status,
            startsAt: $table->starts_at,
            durationMinutes: $table->duration_minutes,
            location: $table->location,
            onlineUrl: $table->online_url,
            minPlayers: $table->min_players,
            maxPlayers: $table->max_players,
            maxSpectators: $table->max_spectators,
            minimumAge: $table->minimum_age,
            language: $table->language,
            experienceLevel: $table->experience_level,
            characterCreation: $table->character_creation,
            genres: $table->genres !== null
                ? array_map(fn (string $g): Genre => Genre::from($g), $table->genres)
                : [],
            tone: $table->tone,
            safetyTools: $table->safety_tools !== null
                ? array_map(fn (string $s): SafetyTool => SafetyTool::from($s), $table->safety_tools)
                : [],
            contentWarnings: $table->contentWarnings->pluck('name')->all(),
            customWarnings: $table->custom_warnings ?? [],
            registrationType: $table->registration_type,
            membersEarlyAccessDays: $table->members_early_access_days,
            registrationOpensAt: $table->registration_opens_at,
            registrationClosesAt: $table->registration_closes_at,
            autoConfirm: $table->auto_confirm,
            isPublished: $table->is_published,
            publishedAt: $table->published_at,
            notes: $table->notes,
            participants: $participants,
            gameMasters: $gameMasters,
            createdAt: $table->created_at,
            updatedAt: $table->updated_at,
        );
    }
}
