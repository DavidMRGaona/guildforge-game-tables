<?php

declare(strict_types=1);

namespace Modules\GameTables\Infrastructure\Persistence\Eloquent\Repositories;

use DateTimeImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Modules\GameTables\Domain\Entities\CampaignJoinRequest;
use Modules\GameTables\Domain\Enums\JoinRequestStatus;
use Modules\GameTables\Domain\Exceptions\RosterActionRefusedException;
use Modules\GameTables\Domain\Repositories\CampaignJoinRequestRepositoryInterface;
use Modules\GameTables\Domain\ValueObjects\CampaignId;
use Modules\GameTables\Domain\ValueObjects\JoinRequestId;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\CampaignJoinRequestModel;

final readonly class EloquentCampaignJoinRequestRepository implements CampaignJoinRequestRepositoryInterface
{
    public function save(CampaignJoinRequest $request): void
    {
        try {
            CampaignJoinRequestModel::query()->updateOrCreate(
                ['id' => $request->id->value],
                [
                    'campaign_id' => $request->campaignId->value,
                    'user_id' => $request->userId,
                    'status' => $request->status->value,
                    'message' => $request->message,
                    'decision_reason' => $request->decisionReason,
                    'decided_by' => $request->decidedBy,
                    'decided_at' => $request->decidedAt,
                ],
            );
        } catch (UniqueConstraintViolationException) {
            throw RosterActionRefusedException::because('request_pending');
        }
    }

    public function find(JoinRequestId $id): ?CampaignJoinRequest
    {
        $model = CampaignJoinRequestModel::query()->find($id->value);

        return $model === null ? null : $this->toEntity($model);
    }

    public function findPending(CampaignId $campaignId, string $userId): ?CampaignJoinRequest
    {
        $model = CampaignJoinRequestModel::query()
            ->where('campaign_id', $campaignId->value)
            ->where('user_id', $userId)
            ->where('status', JoinRequestStatus::Pending->value)
            ->first();

        return $model === null ? null : $this->toEntity($model);
    }

    public function pendingFor(CampaignId $campaignId): array
    {
        return array_values(CampaignJoinRequestModel::query()
            ->where('campaign_id', $campaignId->value)
            ->where('status', JoinRequestStatus::Pending->value)
            ->whereHas('user', fn ($user) => $user->whereNull('anonymized_at'))
            ->orderBy('created_at')
            ->orderBy('id')
            ->get()
            ->map(fn (CampaignJoinRequestModel $model): CampaignJoinRequest => $this->toEntity($model))
            ->all());
    }

    public function pendingCountsFor(array $campaignIds): array
    {
        $ids = array_map(fn (CampaignId $id): string => $id->value, $campaignIds);
        $counts = array_fill_keys($ids, 0);

        if ($ids === []) {
            return $counts;
        }

        $rows = CampaignJoinRequestModel::query()
            ->whereIn('campaign_id', $ids)
            ->where('status', JoinRequestStatus::Pending->value)
            ->whereHas('user', fn ($user) => $user->whereNull('anonymized_at'))
            ->groupBy('campaign_id')
            ->selectRaw('campaign_id, count(*) as total')
            ->get();

        foreach ($rows as $row) {
            $counts[(string) $row->campaign_id] = (int) $row->getAttribute('total');
        }

        return $counts;
    }

    private function toEntity(CampaignJoinRequestModel $model): CampaignJoinRequest
    {
        return new CampaignJoinRequest(
            id: new JoinRequestId($model->id),
            campaignId: new CampaignId($model->campaign_id),
            userId: $model->user_id,
            status: $model->status,
            message: $model->message,
            decisionReason: $model->decision_reason,
            decidedBy: $model->decided_by,
            decidedAt: $model->decided_at !== null ? DateTimeImmutable::createFromInterface($model->decided_at) : null,
            createdAt: $model->created_at !== null ? DateTimeImmutable::createFromInterface($model->created_at) : null,
        );
    }
}
