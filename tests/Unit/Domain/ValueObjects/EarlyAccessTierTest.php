<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Unit\Domain\ValueObjects;

use DateTimeImmutable;
use InvalidArgumentException;
use Modules\GameTables\Domain\Enums\CreationAccessLevel;
use Modules\GameTables\Domain\ValueObjects\EarlyAccessTier;
use PHPUnit\Framework\TestCase;

final class EarlyAccessTierTest extends TestCase
{
    public function test_it_creates_tier_for_role_based_access(): void
    {
        $tier = new EarlyAccessTier(
            accessType: CreationAccessLevel::Role,
            allowedRoles: ['socio', 'colaborador'],
            requiredPermission: null,
            daysBeforeOpening: 3,
        );

        $this->assertEquals(CreationAccessLevel::Role, $tier->accessType);
        $this->assertEquals(['socio', 'colaborador'], $tier->allowedRoles);
        $this->assertNull($tier->requiredPermission);
        $this->assertEquals(3, $tier->daysBeforeOpening);
    }

    public function test_it_creates_tier_for_permission_based_access(): void
    {
        $tier = EarlyAccessTier::create(
            accessType: CreationAccessLevel::Permission,
            requiredPermission: 'gametables:early_create',
            daysBeforeOpening: 7,
        );

        $this->assertEquals(CreationAccessLevel::Permission, $tier->accessType);
        $this->assertNull($tier->allowedRoles);
        $this->assertEquals('gametables:early_create', $tier->requiredPermission);
        $this->assertEquals(7, $tier->daysBeforeOpening);
    }

    public function test_it_throws_exception_for_role_access_without_roles(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Allowed roles must be specified for role-based early access.');

        new EarlyAccessTier(
            accessType: CreationAccessLevel::Role,
            allowedRoles: null,
            requiredPermission: null,
            daysBeforeOpening: 3,
        );
    }

    public function test_it_throws_exception_for_role_access_with_empty_roles(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Allowed roles must be specified for role-based early access.');

        new EarlyAccessTier(
            accessType: CreationAccessLevel::Role,
            allowedRoles: [],
            requiredPermission: null,
            daysBeforeOpening: 3,
        );
    }

    public function test_it_throws_exception_for_permission_access_without_permission(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Required permission must be specified for permission-based early access.');

        new EarlyAccessTier(
            accessType: CreationAccessLevel::Permission,
            allowedRoles: null,
            requiredPermission: null,
            daysBeforeOpening: 5,
        );
    }

    public function test_it_throws_exception_for_permission_access_with_empty_permission(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Required permission must be specified for permission-based early access.');

        new EarlyAccessTier(
            accessType: CreationAccessLevel::Permission,
            allowedRoles: null,
            requiredPermission: '',
            daysBeforeOpening: 5,
        );
    }

    public function test_it_throws_exception_for_invalid_days_before(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Days before opening must be at least 1.');

        new EarlyAccessTier(
            accessType: CreationAccessLevel::Role,
            allowedRoles: ['socio'],
            requiredPermission: null,
            daysBeforeOpening: 0,
        );
    }

    public function test_it_throws_exception_for_negative_days_before(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Days before opening must be at least 1.');

        new EarlyAccessTier(
            accessType: CreationAccessLevel::Role,
            allowedRoles: ['socio'],
            requiredPermission: null,
            daysBeforeOpening: -1,
        );
    }

    public function test_get_open_date_calculates_correctly(): void
    {
        $tier = EarlyAccessTier::create(
            accessType: CreationAccessLevel::Role,
            allowedRoles: ['socio'],
            daysBeforeOpening: 3,
        );

        $generalOpenDate = new DateTimeImmutable('2026-03-01 00:00:00');
        $earlyOpenDate = $tier->getOpenDate($generalOpenDate);

        $this->assertEquals('2026-02-26 00:00:00', $earlyOpenDate->format('Y-m-d H:i:s'));
    }

    public function test_get_open_date_preserves_time(): void
    {
        $tier = EarlyAccessTier::create(
            accessType: CreationAccessLevel::Role,
            allowedRoles: ['socio'],
            daysBeforeOpening: 7,
        );

        $generalOpenDate = new DateTimeImmutable('2026-03-15 10:30:00');
        $earlyOpenDate = $tier->getOpenDate($generalOpenDate);

        $this->assertEquals('2026-03-08 10:30:00', $earlyOpenDate->format('Y-m-d H:i:s'));
    }

    public function test_is_role_based_returns_correct_value(): void
    {
        $roleTier = EarlyAccessTier::create(
            accessType: CreationAccessLevel::Role,
            allowedRoles: ['socio'],
            daysBeforeOpening: 3,
        );

        $permissionTier = EarlyAccessTier::create(
            accessType: CreationAccessLevel::Permission,
            requiredPermission: 'gametables:early_create',
            daysBeforeOpening: 3,
        );

        $this->assertTrue($roleTier->isRoleBased());
        $this->assertFalse($permissionTier->isRoleBased());
    }

    public function test_is_permission_based_returns_correct_value(): void
    {
        $roleTier = EarlyAccessTier::create(
            accessType: CreationAccessLevel::Role,
            allowedRoles: ['socio'],
            daysBeforeOpening: 3,
        );

        $permissionTier = EarlyAccessTier::create(
            accessType: CreationAccessLevel::Permission,
            requiredPermission: 'gametables:early_create',
            daysBeforeOpening: 3,
        );

        $this->assertFalse($roleTier->isPermissionBased());
        $this->assertTrue($permissionTier->isPermissionBased());
    }

    public function test_from_array_creates_role_based_tier(): void
    {
        $tier = EarlyAccessTier::fromArray([
            'access_type' => 'role',
            'allowed_roles' => ['socio', 'colaborador'],
            'days_before_opening' => 5,
        ]);

        $this->assertEquals(CreationAccessLevel::Role, $tier->accessType);
        $this->assertEquals(['socio', 'colaborador'], $tier->allowedRoles);
        $this->assertEquals(5, $tier->daysBeforeOpening);
    }

    public function test_from_array_creates_permission_based_tier(): void
    {
        $tier = EarlyAccessTier::fromArray([
            'access_type' => 'permission',
            'required_permission' => 'gametables:early_create',
            'days_before_opening' => 7,
        ]);

        $this->assertEquals(CreationAccessLevel::Permission, $tier->accessType);
        $this->assertEquals('gametables:early_create', $tier->requiredPermission);
        $this->assertEquals(7, $tier->daysBeforeOpening);
    }

    public function test_to_array_returns_correct_format_for_role_based(): void
    {
        $tier = EarlyAccessTier::create(
            accessType: CreationAccessLevel::Role,
            allowedRoles: ['socio', 'colaborador'],
            daysBeforeOpening: 3,
        );

        $array = $tier->toArray();

        $this->assertEquals([
            'access_type' => 'role',
            'allowed_roles' => ['socio', 'colaborador'],
            'required_permission' => null,
            'days_before_opening' => 3,
        ], $array);
    }

    public function test_to_array_returns_correct_format_for_permission_based(): void
    {
        $tier = EarlyAccessTier::create(
            accessType: CreationAccessLevel::Permission,
            requiredPermission: 'gametables:early_create',
            daysBeforeOpening: 7,
        );

        $array = $tier->toArray();

        $this->assertEquals([
            'access_type' => 'permission',
            'allowed_roles' => null,
            'required_permission' => 'gametables:early_create',
            'days_before_opening' => 7,
        ], $array);
    }

    public function test_equals_compares_all_properties(): void
    {
        $tier1 = EarlyAccessTier::create(
            accessType: CreationAccessLevel::Role,
            allowedRoles: ['socio', 'colaborador'],
            daysBeforeOpening: 3,
        );

        $tier2 = EarlyAccessTier::create(
            accessType: CreationAccessLevel::Role,
            allowedRoles: ['socio', 'colaborador'],
            daysBeforeOpening: 3,
        );

        $tier3 = EarlyAccessTier::create(
            accessType: CreationAccessLevel::Role,
            allowedRoles: ['socio'],
            daysBeforeOpening: 3,
        );

        $tier4 = EarlyAccessTier::create(
            accessType: CreationAccessLevel::Role,
            allowedRoles: ['socio', 'colaborador'],
            daysBeforeOpening: 5,
        );

        $this->assertTrue($tier1->equals($tier2));
        $this->assertFalse($tier1->equals($tier3));
        $this->assertFalse($tier1->equals($tier4));
    }
}
