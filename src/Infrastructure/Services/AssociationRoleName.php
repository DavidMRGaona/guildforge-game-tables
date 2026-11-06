<?php

declare(strict_types=1);

namespace Modules\GameTables\Infrastructure\Services;

use App\Application\Authorization\Services\AssociationMembershipServiceInterface;
use App\Infrastructure\Persistence\Eloquent\Models\RoleModel;
use Modules\GameTables\Application\Services\AssociationRoleNameInterface;

/**
 * Read once per request or job (bound scoped): a page names the role in many labels.
 */
final class AssociationRoleName implements AssociationRoleNameInterface
{
    private ?string $displayName = null;

    public function __construct(
        private readonly AssociationMembershipServiceInterface $membership,
    ) {}

    public function displayName(): string
    {
        return $this->displayName ??= $this->read();
    }

    private function read(): string
    {
        $role = $this->membership->roleName();
        $displayName = $role === null ? null : RoleModel::query()->where('name', $role)->value('display_name');

        return is_string($displayName) && trim($displayName) !== ''
            ? trim($displayName)
            : (string) __('game-tables::messages.association_role_fallback');
    }
}
