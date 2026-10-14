<?php

declare(strict_types=1);

namespace Modules\GameTables\Policies;

use App\Infrastructure\Authorization\Policies\AuthorizesWithPermissions;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\PublisherModel;

/**
 * Publishers belong to the game system catalog and share its permissions.
 */
final class PublisherPolicy
{
    use AuthorizesWithPermissions;

    public function viewAny(UserModel $user): bool
    {
        return $this->authorize($user, 'gametables:gamesystems.view_any');
    }

    public function view(UserModel $user, PublisherModel $publisher): bool
    {
        return $this->authorize($user, 'gametables:gamesystems.view_any');
    }

    public function create(UserModel $user): bool
    {
        return $this->authorize($user, 'gametables:gamesystems.manage');
    }

    public function update(UserModel $user, PublisherModel $publisher): bool
    {
        return $this->authorize($user, 'gametables:gamesystems.manage');
    }

    public function delete(UserModel $user, PublisherModel $publisher): bool
    {
        return $this->authorize($user, 'gametables:gamesystems.manage');
    }

    public function deleteAny(UserModel $user): bool
    {
        return $this->authorize($user, 'gametables:gamesystems.manage');
    }

    public function restore(UserModel $user, PublisherModel $publisher): bool
    {
        return $this->authorize($user, 'gametables:gamesystems.manage');
    }

    public function forceDelete(UserModel $user, PublisherModel $publisher): bool
    {
        return $this->authorize($user, 'gametables:gamesystems.manage');
    }
}
