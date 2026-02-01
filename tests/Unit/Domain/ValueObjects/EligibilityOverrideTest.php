<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Unit\Domain\ValueObjects;

use InvalidArgumentException;
use Modules\GameTables\Domain\Enums\CreationAccessLevel;
use Modules\GameTables\Domain\ValueObjects\EligibilityOverride;
use PHPUnit\Framework\TestCase;

final class EligibilityOverrideTest extends TestCase
{
    public function test_it_creates_override_for_everyone_access(): void
    {
        $override = new EligibilityOverride(
            accessLevel: CreationAccessLevel::Everyone,
        );

        $this->assertEquals(CreationAccessLevel::Everyone, $override->accessLevel);
        $this->assertNull($override->allowedRoles);
        $this->assertNull($override->requiredPermission);
    }

    public function test_it_creates_override_for_registered_access(): void
    {
        $override = EligibilityOverride::create(
            accessLevel: CreationAccessLevel::Registered,
        );

        $this->assertEquals(CreationAccessLevel::Registered, $override->accessLevel);
    }

    public function test_it_creates_override_for_role_based_access(): void
    {
        $override = EligibilityOverride::create(
            accessLevel: CreationAccessLevel::Role,
            allowedRoles: ['editor', 'admin'],
        );

        $this->assertEquals(CreationAccessLevel::Role, $override->accessLevel);
        $this->assertEquals(['editor', 'admin'], $override->allowedRoles);
    }

    public function test_it_creates_override_for_permission_based_access(): void
    {
        $override = EligibilityOverride::create(
            accessLevel: CreationAccessLevel::Permission,
            requiredPermission: 'gametables.create',
        );

        $this->assertEquals(CreationAccessLevel::Permission, $override->accessLevel);
        $this->assertEquals('gametables.create', $override->requiredPermission);
    }

    public function test_it_throws_exception_for_role_access_without_roles(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Allowed roles must be specified for role-based access.');

        new EligibilityOverride(
            accessLevel: CreationAccessLevel::Role,
        );
    }

    public function test_it_throws_exception_for_role_access_with_empty_roles(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Allowed roles must be specified for role-based access.');

        new EligibilityOverride(
            accessLevel: CreationAccessLevel::Role,
            allowedRoles: [],
        );
    }

    public function test_it_throws_exception_for_permission_access_without_permission(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Required permission must be specified for permission-based access.');

        new EligibilityOverride(
            accessLevel: CreationAccessLevel::Permission,
        );
    }

    public function test_it_throws_exception_for_permission_access_with_empty_permission(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Required permission must be specified for permission-based access.');

        new EligibilityOverride(
            accessLevel: CreationAccessLevel::Permission,
            requiredPermission: '',
        );
    }

    public function test_requires_authentication_returns_correct_value(): void
    {
        $everyoneOverride = EligibilityOverride::create(
            accessLevel: CreationAccessLevel::Everyone,
        );

        $registeredOverride = EligibilityOverride::create(
            accessLevel: CreationAccessLevel::Registered,
        );

        $this->assertFalse($everyoneOverride->requiresAuthentication());
        $this->assertTrue($registeredOverride->requiresAuthentication());
    }

    public function test_is_role_based_returns_correct_value(): void
    {
        $roleOverride = EligibilityOverride::create(
            accessLevel: CreationAccessLevel::Role,
            allowedRoles: ['editor'],
        );

        $registeredOverride = EligibilityOverride::create(
            accessLevel: CreationAccessLevel::Registered,
        );

        $this->assertTrue($roleOverride->isRoleBased());
        $this->assertFalse($registeredOverride->isRoleBased());
    }

    public function test_is_permission_based_returns_correct_value(): void
    {
        $permissionOverride = EligibilityOverride::create(
            accessLevel: CreationAccessLevel::Permission,
            requiredPermission: 'gametables.create',
        );

        $registeredOverride = EligibilityOverride::create(
            accessLevel: CreationAccessLevel::Registered,
        );

        $this->assertTrue($permissionOverride->isPermissionBased());
        $this->assertFalse($registeredOverride->isPermissionBased());
    }

    public function test_equals_compares_all_properties(): void
    {
        $override1 = EligibilityOverride::create(
            accessLevel: CreationAccessLevel::Role,
            allowedRoles: ['editor', 'admin'],
        );

        $override2 = EligibilityOverride::create(
            accessLevel: CreationAccessLevel::Role,
            allowedRoles: ['editor', 'admin'],
        );

        $override3 = EligibilityOverride::create(
            accessLevel: CreationAccessLevel::Role,
            allowedRoles: ['editor'],
        );

        $this->assertTrue($override1->equals($override2));
        $this->assertFalse($override1->equals($override3));
    }

    public function test_from_array_creates_instance(): void
    {
        $override = EligibilityOverride::fromArray([
            'access_level' => 'role',
            'allowed_roles' => ['editor', 'admin'],
        ]);

        $this->assertEquals(CreationAccessLevel::Role, $override->accessLevel);
        $this->assertEquals(['editor', 'admin'], $override->allowedRoles);
    }

    public function test_to_array_returns_correct_format(): void
    {
        $override = EligibilityOverride::create(
            accessLevel: CreationAccessLevel::Role,
            allowedRoles: ['editor'],
            requiredPermission: null,
        );

        $array = $override->toArray();

        $this->assertEquals('role', $array['access_level']);
        $this->assertEquals(['editor'], $array['allowed_roles']);
        $this->assertNull($array['required_permission']);
    }
}
