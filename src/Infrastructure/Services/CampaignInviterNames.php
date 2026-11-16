<?php

declare(strict_types=1);

namespace Modules\GameTables\Infrastructure\Services;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Database\Eloquent\Collection;
use Modules\GameTables\Application\Services\CampaignInviterNamesInterface;
use Modules\GameTables\Domain\Entities\CampaignInvitation;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\CampaignModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameMasterModel;

/**
 * A fixed number of queries (about five) whatever the number of invitations: their campaigns with their
 * game system and directors (and the directors' accounts), and the inviters.
 */
final readonly class CampaignInviterNames implements CampaignInviterNamesInterface
{
    public function forNewInvitation(string $campaignId, string $inviterId): string
    {
        return $this->resolve(
            $this->campaigns([$campaignId])->get($campaignId),
            $this->inviters([$inviterId])->get($inviterId),
            '',
        );
    }

    public function namesOf(array $invitations): array
    {
        if ($invitations === []) {
            return [];
        }

        $campaigns = $this->campaigns(array_map(
            static fn (CampaignInvitation $invitation): string => $invitation->campaignId->value,
            $invitations,
        ));
        $inviters = $this->inviters(array_values(array_filter(array_map(
            static fn (CampaignInvitation $invitation): ?string => $invitation->invitedBy,
            $invitations,
        ))));

        $names = [];

        foreach ($invitations as $invitation) {
            $names[$invitation->id->value] = $this->resolve(
                $campaigns->get($invitation->campaignId->value),
                $invitation->invitedBy === null ? null : $inviters->get($invitation->invitedBy),
                $invitation->inviterName,
            );
        }

        return $names;
    }

    public function nameOf(CampaignInvitation $invitation): string
    {
        return $this->namesOf([$invitation])[$invitation->id->value];
    }

    private function resolve(?CampaignModel $campaign, ?UserModel $inviter, string $storedName): string
    {
        if (! $inviter instanceof UserModel) {
            return $storedName;
        }

        $director = $campaign?->gameMasters->first(
            fn (GameMasterModel $gameMaster): bool => $gameMaster->user_id === $inviter->id
        );

        if ($director instanceof GameMasterModel) {
            // As the campaign page names them: their name only when they chose to show it
            return $director->cardName($campaign->gameSystem?->game_master_title);
        }

        return AccountPublicName::of($inviter);
    }

    /**
     * @param  list<string>  $ids
     * @return Collection<string, CampaignModel>
     */
    private function campaigns(array $ids): Collection
    {
        return CampaignModel::query()
            ->with(['gameSystem', 'gameMasters.user'])
            ->whereKey(array_values(array_unique($ids)))
            ->get()
            ->keyBy('id');
    }

    /**
     * @param  list<string>  $ids
     * @return Collection<string, UserModel>
     */
    private function inviters(array $ids): Collection
    {
        if ($ids === []) {
            return new Collection;
        }

        return UserModel::query()->whereKey(array_values(array_unique($ids)))->get()->keyBy('id');
    }
}
