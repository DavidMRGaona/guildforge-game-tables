<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Unit\Infrastructure\Services;

use Modules\GameTables\Infrastructure\Services\MembershipsModuleMembershipChecker;
use PHPUnit\Framework\TestCase;

final class MembershipsModuleMembershipCheckerTest extends TestCase
{
    public function test_without_the_memberships_module_nobody_is_a_member(): void
    {
        $this->assertFalse((new MembershipsModuleMembershipChecker())->isActiveMember('any-user'));
    }
}
