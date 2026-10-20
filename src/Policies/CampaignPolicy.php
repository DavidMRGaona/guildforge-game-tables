<?php

declare(strict_types=1);

namespace Modules\GameTables\Policies;

use App\Infrastructure\Authorization\Policies\AuthorizesWithPermissions;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Modules\GameTables\Application\Services\CampaignDirectionInterface;
use Modules\GameTables\Domain\Enums\FrontendCreationStatus;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\CampaignModel;

final class CampaignPolicy
{
    use AuthorizesWithPermissions;

    public function viewAny(UserModel $user): bool
    {
        return $this->authorize($user, 'gametables:campaigns.view_any');
    }

    public function view(UserModel $user, CampaignModel $campaign): bool
    {
        return $this->authorize($user, 'gametables:campaigns.view')
            || $campaign->created_by === $user->id;
    }

    public function create(UserModel $user): bool
    {
        return $this->authorize($user, 'gametables:campaigns.create');
    }

    public function update(UserModel $user, CampaignModel $campaign): bool
    {
        return $this->authorize($user, 'gametables:campaigns.update')
            || $this->authorMayChange($user, $campaign);
    }

    public function delete(UserModel $user, CampaignModel $campaign): bool
    {
        return $this->authorize($user, 'gametables:campaigns.delete')
            || $this->authorMayChange($user, $campaign);
    }

    public function deleteAny(UserModel $user): bool
    {
        return $this->authorize($user, 'gametables:campaigns.delete');
    }

    public function restore(UserModel $user, CampaignModel $campaign): bool
    {
        return $this->authorize($user, 'gametables:campaigns.delete');
    }

    public function forceDelete(UserModel $user, CampaignModel $campaign): bool
    {
        return $this->authorize($user, 'gametables:campaigns.delete');
    }

    /**
     * Directors manage the roster of an approved or published campaign; staff permissions do not grant it.
     */
    public function manageRoster(UserModel $user, CampaignModel $campaign): bool
    {
        $open = $campaign->frontend_creation_status === FrontendCreationStatus::Approved || (bool) $campaign->is_published;

        return $open && app(CampaignDirectionInterface::class)->isDirector($campaign->id, $user->id);
    }

    /**
     * The author changes the campaign only while it is an unpublished draft or rejected campaign.
     */
    private function authorMayChange(UserModel $user, CampaignModel $campaign): bool
    {
        return $campaign->created_by === $user->id
            && FrontendCreationStatus::allowsAuthorChanges($campaign->frontend_creation_status, (bool) $campaign->is_published);
    }
}
