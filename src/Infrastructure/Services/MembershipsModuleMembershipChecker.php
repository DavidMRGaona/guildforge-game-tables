<?php

declare(strict_types=1);

namespace Modules\GameTables\Infrastructure\Services;

use Modules\GameTables\Application\Services\MembershipCheckerInterface;
use Modules\Memberships\Application\Services\MemberServiceInterface;

/**
 * A member is a user linked to an active member of the memberships module.
 * Without that module (the service cannot be resolved) nobody is a member.
 */
final readonly class MembershipsModuleMembershipChecker implements MembershipCheckerInterface
{
    public function __construct(
        private ?MemberServiceInterface $memberService = null,
    ) {}

    public function isActiveMember(string $userId): bool
    {
        if ($this->memberService === null) {
            return false;
        }

        return array_any(
            $this->memberService->getActiveMembers(),
            static fn (object $member): bool => ($member->userId ?? null) === $userId,
        );
    }
}
