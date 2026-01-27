<?php

declare(strict_types=1);

namespace Modules\GameTables\Policies;

use App\Infrastructure\Authorization\Policies\AuthorizesWithPermissions;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameSystemModel;

final class GameSystemPolicy
{
    use AuthorizesWithPermissions;

    public function viewAny(UserModel $user): bool
    {
        return $this->authorize($user, 'gametables:gamesystems.view_any');
    }

    public function view(UserModel $user, GameSystemModel $gameSystem): bool
    {
        return $this->authorize($user, 'gametables:gamesystems.view_any');
    }

    public function create(UserModel $user): bool
    {
        return $this->authorize($user, 'gametables:gamesystems.manage');
    }

    public function update(UserModel $user, GameSystemModel $gameSystem): bool
    {
        return $this->authorize($user, 'gametables:gamesystems.manage');
    }

    public function delete(UserModel $user, GameSystemModel $gameSystem): bool
    {
        return $this->authorize($user, 'gametables:gamesystems.manage');
    }

    public function deleteAny(UserModel $user): bool
    {
        return $this->authorize($user, 'gametables:gamesystems.manage');
    }

    public function restore(UserModel $user, GameSystemModel $gameSystem): bool
    {
        return $this->authorize($user, 'gametables:gamesystems.manage');
    }

    public function forceDelete(UserModel $user, GameSystemModel $gameSystem): bool
    {
        return $this->authorize($user, 'gametables:gamesystems.manage');
    }
}
