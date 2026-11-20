<?php

declare(strict_types=1);

namespace Modules\GameTables\Policies;

use App\Infrastructure\Authorization\Policies\AuthorizesWithPermissions;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GenreModel;

/**
 * Seeing the genres and managing them (create, edit, order, delete) are separate permissions.
 */
final class GenrePolicy
{
    use AuthorizesWithPermissions;

    private const string VIEW = 'gametables:genres.view_any';

    private const string MANAGE = 'gametables:genres.manage';

    public function viewAny(UserModel $user): bool
    {
        return $this->authorize($user, self::VIEW);
    }

    public function view(UserModel $user, GenreModel $genre): bool
    {
        return $this->authorize($user, self::VIEW);
    }

    public function create(UserModel $user): bool
    {
        return $this->authorize($user, self::MANAGE);
    }

    public function update(UserModel $user, GenreModel $genre): bool
    {
        return $this->authorize($user, self::MANAGE);
    }

    public function reorder(UserModel $user): bool
    {
        return $this->authorize($user, self::MANAGE);
    }

    public function delete(UserModel $user, GenreModel $genre): bool
    {
        return $this->authorize($user, self::MANAGE);
    }

    public function deleteAny(UserModel $user): bool
    {
        return $this->authorize($user, self::MANAGE);
    }
}
