<?php

declare(strict_types=1);

namespace Modules\GameTables\Application\Services;

/**
 * Whether a user counts as a member for "Solo socios" tables and members' early access.
 */
interface MembershipCheckerInterface
{
    public function isActiveMember(string $userId): bool;
}
