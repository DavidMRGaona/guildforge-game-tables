<?php

declare(strict_types=1);

namespace Modules\GameTables\Infrastructure\Services;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Modules\GameTables\Application\Services\TableDirectionInterface;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameMasterModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameTableModel;

final readonly class TableDirection implements TableDirectionInterface
{
    public function isDirector(string $gameTableId, string $userId): bool
    {
        return in_array($userId, $this->directorUserIds($gameTableId), true);
    }

    public function directorUserIds(string $gameTableId): array
    {
        $table = GameTableModel::query()->find($gameTableId);

        if ($table === null) {
            return [];
        }

        /** @var list<string> $ids */
        $ids = $table->effective_game_masters
            ->filter(fn (GameMasterModel $gm): bool => $gm->user_id !== null)
            ->map(fn (GameMasterModel $gm): string => (string) $gm->user_id)
            ->unique()
            ->values()
            ->all();

        if ($ids === []) {
            return [];
        }

        $active = UserModel::query()
            ->whereIn('id', $ids)
            ->whereNull('anonymized_at')
            ->pluck('id')
            ->map(fn (mixed $id): string => (string) $id)
            ->all();

        return array_values(array_filter($ids, fn (string $id): bool => in_array($id, $active, true)));
    }

    public function tableIdsDirectedBy(string $userId): array
    {
        $active = UserModel::query()->whereKey($userId)->whereNull('anonymized_at')->exists();

        if (! $active) {
            return [];
        }

        $local = GameTableModel::query()
            ->whereHas('localGameMasters', fn ($query) => $query->where('gametables_game_masters.user_id', $userId))
            ->pluck('id');

        $inherited = GameTableModel::query()
            ->whereNotNull('campaign_id')
            ->whereHas('campaign.gameMasters', function ($query) use ($userId): void {
                $query->where('gametables_game_masters.user_id', $userId)
                    ->whereNotIn('gametables_game_masters.id', function ($sub): void {
                        $sub->select('gametables_table_gm.game_master_id')
                            ->from('gametables_table_gm')
                            ->whereColumn('gametables_table_gm.game_table_id', 'gametables_tables.id')
                            ->where('gametables_table_gm.excluded', true);
                    });
            })
            ->pluck('id');

        return $local->merge($inherited)->unique()->values()->map(fn (mixed $id): string => (string) $id)->all();
    }
}
