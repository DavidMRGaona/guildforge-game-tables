<?php

declare(strict_types=1);

namespace Modules\GameTables\Infrastructure\Services;

use App\Application\Authorization\Services\AssociationMembershipServiceInterface;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Modules\GameTables\Application\Services\MembershipCheckerInterface;

/**
 * A member is a user holding the association member role of the core
 * (site settings, "socio" by default). Without that role nobody is a member.
 */
final readonly class AssociationRoleMembershipChecker implements MembershipCheckerInterface
{
    public function __construct(
        private AssociationMembershipServiceInterface $associationMembership,
    ) {}

    public function isActiveMember(string $userId): bool
    {
        $user = UserModel::query()->find($userId);

        return $user !== null && $this->associationMembership->isAssociationMember($user);
    }
}
