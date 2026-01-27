<?php

declare(strict_types=1);

namespace Modules\GameTables\Policies;

use App\Infrastructure\Authorization\Policies\AuthorizesWithPermissions;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\ContentWarningModel;

final class ContentWarningPolicy
{
    use AuthorizesWithPermissions;

    public function viewAny(UserModel $user): bool
    {
        return $this->authorize($user, 'gametables:contentwarnings.view_any');
    }

    public function view(UserModel $user, ContentWarningModel $contentWarning): bool
    {
        return $this->authorize($user, 'gametables:contentwarnings.view_any');
    }

    public function create(UserModel $user): bool
    {
        return $this->authorize($user, 'gametables:contentwarnings.manage');
    }

    public function update(UserModel $user, ContentWarningModel $contentWarning): bool
    {
        return $this->authorize($user, 'gametables:contentwarnings.manage');
    }

    public function delete(UserModel $user, ContentWarningModel $contentWarning): bool
    {
        return $this->authorize($user, 'gametables:contentwarnings.manage');
    }

    public function deleteAny(UserModel $user): bool
    {
        return $this->authorize($user, 'gametables:contentwarnings.manage');
    }

    public function restore(UserModel $user, ContentWarningModel $contentWarning): bool
    {
        return $this->authorize($user, 'gametables:contentwarnings.manage');
    }

    public function forceDelete(UserModel $user, ContentWarningModel $contentWarning): bool
    {
        return $this->authorize($user, 'gametables:contentwarnings.manage');
    }
}
