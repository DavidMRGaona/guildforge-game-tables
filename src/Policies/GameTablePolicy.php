<?php

declare(strict_types=1);

namespace Modules\GameTables\Policies;

use App\Infrastructure\Authorization\Policies\AuthorizesWithPermissions;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameTableModel;

final class GameTablePolicy
{
    use AuthorizesWithPermissions;

    public function viewAny(UserModel $user): bool
    {
        return $this->authorize($user, 'gametables:gametables.view_any');
    }

    public function view(UserModel $user, GameTableModel $gameTable): bool
    {
        return $this->authorize($user, 'gametables:gametables.view')
            || $gameTable->created_by === $user->id;
    }

    public function create(UserModel $user): bool
    {
        return $this->authorize($user, 'gametables:gametables.create');
    }

    public function update(UserModel $user, GameTableModel $gameTable): bool
    {
        // Admin with permission can update any table
        if ($this->authorize($user, 'gametables:gametables.update')) {
            return true;
        }

        // Owner can update only if the frontend creation status allows it
        return $gameTable->created_by === $user->id
            && ($gameTable->frontend_creation_status === null
                || $gameTable->frontend_creation_status->canEdit());
    }

    public function submitForReview(UserModel $user, GameTableModel $gameTable): bool
    {
        return $gameTable->created_by === $user->id
            && $gameTable->frontend_creation_status !== null
            && $gameTable->frontend_creation_status->canSubmitForReview();
    }

    public function delete(UserModel $user, GameTableModel $gameTable): bool
    {
        return $this->authorize($user, 'gametables:gametables.delete')
            || $gameTable->created_by === $user->id;
    }

    public function deleteAny(UserModel $user): bool
    {
        return $this->authorize($user, 'gametables:gametables.delete');
    }

    public function restore(UserModel $user, GameTableModel $gameTable): bool
    {
        return $this->authorize($user, 'gametables:gametables.delete');
    }

    public function forceDelete(UserModel $user, GameTableModel $gameTable): bool
    {
        return $this->authorize($user, 'gametables:gametables.delete');
    }
}
