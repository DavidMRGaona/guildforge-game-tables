<?php

declare(strict_types=1);

namespace Modules\GameTables\Policies;

use App\Infrastructure\Authorization\Policies\AuthorizesWithPermissions;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
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
            || $campaign->created_by === $user->id;
    }

    public function delete(UserModel $user, CampaignModel $campaign): bool
    {
        return $this->authorize($user, 'gametables:campaigns.delete')
            || $campaign->created_by === $user->id;
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
}
