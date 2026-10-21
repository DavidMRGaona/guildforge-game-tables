<?php

declare(strict_types=1);

namespace Modules\GameTables\Infrastructure\Persistence\Eloquent\Repositories;

use DateTimeImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Modules\GameTables\Domain\Entities\CampaignInvitation;
use Modules\GameTables\Domain\Enums\CampaignStatus;
use Modules\GameTables\Domain\Enums\FrontendCreationStatus;
use Modules\GameTables\Domain\Exceptions\RosterActionRefusedException;
use Modules\GameTables\Domain\Repositories\CampaignInvitationRepositoryInterface;
use Modules\GameTables\Domain\ValueObjects\CampaignId;
use Modules\GameTables\Domain\ValueObjects\CampaignInvitationId;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\CampaignInvitationModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\CampaignModel;

final readonly class EloquentCampaignInvitationRepository implements CampaignInvitationRepositoryInterface
{
    private const string FORMAT = 'Y-m-d H:i:s';

    public function save(CampaignInvitation $invitation): void
    {
        try {
            CampaignInvitationModel::query()->updateOrCreate(
                ['id' => $invitation->id->value],
                [
                    'campaign_id' => $invitation->campaignId->value,
                    'email' => $invitation->email,
                    'invited_by_user_id' => $invitation->invitedBy,
                    'inviter_name' => $invitation->inviterName,
                    'token_hash' => $invitation->tokenHash,
                    'expires_at' => $invitation->expiresAt->format(self::FORMAT),
                    'send_count' => $invitation->sendCount,
                    'last_sent_at' => $invitation->lastSentAt->format(self::FORMAT),
                ],
            );
        } catch (UniqueConstraintViolationException) {
            throw RosterActionRefusedException::because('invitation_pending');
        }
    }

    public function find(CampaignInvitationId $id): ?CampaignInvitation
    {
        $model = CampaignInvitationModel::query()->find($id->value);

        return $model === null ? null : $this->toEntity($model);
    }

    public function findByTokenHash(string $hash): ?CampaignInvitation
    {
        $model = CampaignInvitationModel::query()->where('token_hash', $hash)->first();

        return $model === null ? null : $this->toEntity($model);
    }

    public function findPending(CampaignId $campaignId, string $email): ?CampaignInvitation
    {
        $model = CampaignInvitationModel::query()
            ->where('campaign_id', $campaignId->value)
            ->where('email', $email)
            ->first();

        return $model === null ? null : $this->toEntity($model);
    }

    public function pendingFor(CampaignId $campaignId): array
    {
        return CampaignInvitationModel::query()
            ->where('campaign_id', $campaignId->value)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get()
            ->map(fn (CampaignInvitationModel $model): CampaignInvitation => $this->toEntity($model))
            ->values()
            ->all();
    }

    public function pendingForEmail(string $email, DateTimeImmutable $now): array
    {
        return CampaignInvitationModel::query()
            ->where('email', $email)
            ->where('expires_at', '>', $now->format(self::FORMAT))
            ->orderBy('created_at')
            ->orderBy('id')
            ->get()
            ->map(fn (CampaignInvitationModel $model): CampaignInvitation => $this->toEntity($model))
            ->values()
            ->all();
    }

    public function delete(CampaignInvitationId $id): void
    {
        CampaignInvitationModel::query()->whereKey($id->value)->delete();
    }

    public function deleteExpired(DateTimeImmutable $now): int
    {
        return CampaignInvitationModel::query()
            ->where('expires_at', '<=', $now->format(self::FORMAT))
            ->delete();
    }

    public function deleteForCampaignsNotOpen(): int
    {
        $finished = array_map(
            fn (CampaignStatus $status): string => $status->value,
            array_values(array_filter(CampaignStatus::cases(), fn (CampaignStatus $status): bool => $status->isFinal())),
        );

        // Mirrors Campaign::isFinished() and Campaign::isApprovedOrPublished().
        return CampaignInvitationModel::query()
            ->whereIn('campaign_id', CampaignModel::query()
                ->select('id')
                ->where(function (Builder $query) use ($finished): void {
                    $query->whereIn('status', $finished)
                        ->orWhere(function (Builder $closed): void {
                            $closed->where('is_published', false)
                                ->where(function (Builder $unapproved): void {
                                    $unapproved->whereNull('frontend_creation_status')
                                        ->orWhere('frontend_creation_status', '!=', FrontendCreationStatus::Approved->value);
                                });
                        });
                }))
            ->delete();
    }

    private function toEntity(CampaignInvitationModel $model): CampaignInvitation
    {
        return new CampaignInvitation(
            id: new CampaignInvitationId($model->id),
            campaignId: new CampaignId($model->campaign_id),
            email: $model->email,
            invitedBy: $model->invited_by_user_id,
            inviterName: $model->inviter_name,
            tokenHash: $model->token_hash,
            expiresAt: new DateTimeImmutable($model->expires_at->toDateTimeString()),
            sendCount: $model->send_count,
            lastSentAt: new DateTimeImmutable($model->last_sent_at->toDateTimeString()),
        );
    }
}
