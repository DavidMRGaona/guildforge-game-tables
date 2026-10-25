<?php

declare(strict_types=1);

namespace Modules\GameTables\Infrastructure\Services;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Modules\GameTables\Application\DTOs\MailRecipientDTO;
use Modules\GameTables\Application\Services\CampaignDirectionInterface;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\CampaignModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameMasterModel;

final readonly class CampaignDirection implements CampaignDirectionInterface
{
    public function isDirector(string $campaignId, string $userId): bool
    {
        return in_array($userId, $this->directorUserIds($campaignId), true);
    }

    public function directorUserIds(string $campaignId): array
    {
        return array_map(
            fn (UserModel $user): string => $user->id,
            $this->directors($campaignId, false),
        );
    }

    public function campaignIdsDirectedBy(string $userId): array
    {
        $active = UserModel::query()->whereKey($userId)->whereNull('anonymized_at')->exists();

        if (! $active) {
            return [];
        }

        $web = CampaignModel::query()
            ->where('created_by', $userId)
            ->whereNotNull('frontend_creation_status')
            ->pluck('id');

        $asGm = CampaignModel::query()
            ->whereHas('gameMasters', fn ($query) => $query->where('gametables_game_masters.user_id', $userId))
            ->pluck('id');

        return array_values($web->merge($asGm)->unique()->map(fn (mixed $id): string => (string) $id)->all());
    }

    public function recipients(string $campaignId): array
    {
        $recipients = [];

        foreach ($this->directors($campaignId, true) as $user) {
            if ($user->email === '') {
                continue;
            }

            $recipients[$user->email] ??= new MailRecipientDTO($user->email, $user->name);
        }

        return array_values($recipients);
    }

    /**
     * Active accounts directing the campaign, unique by id, the web creator first.
     *
     * @return list<UserModel>
     */
    private function directors(string $campaignId, bool $forMail): array
    {
        $campaign = CampaignModel::query()->find($campaignId);

        if ($campaign === null) {
            return [];
        }

        $ids = [];

        if ($campaign->frontend_creation_status !== null) {
            $ids[] = $campaign->created_by;
        }

        $gms = $campaign->gameMasters()->whereNotNull('gametables_game_masters.user_id');

        if ($forMail) {
            $gms->where('gametables_game_masters.notify_by_email', true);
        }

        /** @var list<string> $gmUserIds */
        $gmUserIds = $gms->get()
            ->map(fn (GameMasterModel $gm): string => (string) $gm->user_id)
            ->all();

        $ids = array_values(array_unique([...$ids, ...$gmUserIds]));

        if ($ids === []) {
            return [];
        }

        $users = UserModel::query()
            ->whereIn('id', $ids)
            ->whereNull('anonymized_at')
            ->get()
            ->keyBy('id');

        $ordered = [];
        foreach ($ids as $id) {
            $user = $users->get($id);

            if ($user !== null) {
                $ordered[] = $user;
            }
        }

        return $ordered;
    }
}
