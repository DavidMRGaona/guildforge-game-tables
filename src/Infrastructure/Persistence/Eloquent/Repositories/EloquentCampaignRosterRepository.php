<?php

declare(strict_types=1);

namespace Modules\GameTables\Infrastructure\Persistence\Eloquent\Repositories;

use DateTimeImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Modules\GameTables\Domain\Repositories\CampaignRosterRepositoryInterface;
use Modules\GameTables\Domain\ValueObjects\CampaignId;

final readonly class EloquentCampaignRosterRepository implements CampaignRosterRepositoryInterface
{
    private const string TABLE = 'gametables_campaign_players';

    public function contains(CampaignId $campaignId, string $userId): bool
    {
        return $this->activeMembers()
            ->where('p.campaign_id', $campaignId->value)
            ->where('p.user_id', $userId)
            ->exists();
    }

    public function add(CampaignId $campaignId, string $userId, DateTimeImmutable $now): void
    {
        DB::table(self::TABLE)->insertOrIgnore([
            'campaign_id' => $campaignId->value,
            'user_id' => $userId,
            'created_at' => $now->format('Y-m-d H:i:s'),
            'updated_at' => $now->format('Y-m-d H:i:s'),
        ]);
    }

    public function remove(CampaignId $campaignId, string $userId): bool
    {
        return DB::table(self::TABLE)
            ->where('campaign_id', $campaignId->value)
            ->where('user_id', $userId)
            ->delete() > 0;
    }

    public function memberIds(CampaignId $campaignId): array
    {
        return $this->activeMembers()
            ->where('p.campaign_id', $campaignId->value)
            ->orderBy('p.created_at')
            ->orderBy('p.user_id')
            ->pluck('p.user_id')
            ->map(fn (mixed $id): string => (string) $id)
            ->values()
            ->all();
    }

    public function count(CampaignId $campaignId): int
    {
        return $this->activeMembers()->where('p.campaign_id', $campaignId->value)->count();
    }

    public function countsFor(array $campaignIds): array
    {
        $ids = array_map(fn (CampaignId $id): string => $id->value, $campaignIds);
        $counts = array_fill_keys($ids, 0);

        if ($ids === []) {
            return $counts;
        }

        $rows = $this->activeMembers()
            ->whereIn('p.campaign_id', $ids)
            ->groupBy('p.campaign_id')
            ->selectRaw('p.campaign_id as campaign_id, count(*) as total')
            ->get();

        foreach ($rows as $row) {
            $counts[(string) $row->campaign_id] = (int) $row->total;
        }

        return $counts;
    }

    private function activeMembers(): Builder
    {
        return DB::table(self::TABLE.' as p')
            ->join('users', 'users.id', '=', 'p.user_id')
            ->whereNull('users.anonymized_at');
    }
}
