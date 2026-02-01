<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Unit\Domain\ValueObjects;

use DateTimeImmutable;
use InvalidArgumentException;
use Modules\GameTables\Domain\Enums\CreationAccessLevel;
use Modules\GameTables\Domain\ValueObjects\CreationPriorityTier;
use PHPUnit\Framework\TestCase;

final class CreationPriorityTierTest extends TestCase
{
    public function test_it_creates_tier_for_everyone(): void
    {
        $tier = CreationPriorityTier::create(
            tier: 3,
            accessType: CreationAccessLevel::Everyone,
            accessValue: null,
            daysBeforeEventStart: 0
        );

        $this->assertEquals(3, $tier->tier);
        $this->assertEquals(CreationAccessLevel::Everyone, $tier->accessType);
        $this->assertNull($tier->accessValue);
        $this->assertEquals(0, $tier->daysBeforeEventStart);
    }

    public function test_it_creates_tier_for_registered_users(): void
    {
        $tier = CreationPriorityTier::create(
            tier: 2,
            accessType: CreationAccessLevel::Registered,
            accessValue: null,
            daysBeforeEventStart: 3
        );

        $this->assertEquals(2, $tier->tier);
        $this->assertEquals(CreationAccessLevel::Registered, $tier->accessType);
        $this->assertNull($tier->accessValue);
        $this->assertEquals(3, $tier->daysBeforeEventStart);
    }

    public function test_it_creates_tier_for_role(): void
    {
        $tier = CreationPriorityTier::create(
            tier: 1,
            accessType: CreationAccessLevel::Role,
            accessValue: 'admin',
            daysBeforeEventStart: 7
        );

        $this->assertEquals(1, $tier->tier);
        $this->assertEquals(CreationAccessLevel::Role, $tier->accessType);
        $this->assertEquals('admin', $tier->accessValue);
        $this->assertEquals(7, $tier->daysBeforeEventStart);
    }

    public function test_it_creates_tier_for_permission(): void
    {
        $tier = CreationPriorityTier::create(
            tier: 1,
            accessType: CreationAccessLevel::Permission,
            accessValue: 'create_tables_early',
            daysBeforeEventStart: 14
        );

        $this->assertEquals(1, $tier->tier);
        $this->assertEquals(CreationAccessLevel::Permission, $tier->accessType);
        $this->assertEquals('create_tables_early', $tier->accessValue);
        $this->assertEquals(14, $tier->daysBeforeEventStart);
    }

    public function test_it_throws_exception_for_tier_less_than_one(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Tier must be at least 1.');

        CreationPriorityTier::create(
            tier: 0,
            accessType: CreationAccessLevel::Everyone,
            accessValue: null,
            daysBeforeEventStart: 0
        );
    }

    public function test_it_throws_exception_for_negative_tier(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Tier must be at least 1.');

        CreationPriorityTier::create(
            tier: -1,
            accessType: CreationAccessLevel::Everyone,
            accessValue: null,
            daysBeforeEventStart: 0
        );
    }

    public function test_it_throws_exception_for_negative_days_before(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Days before event start must be non-negative.');

        CreationPriorityTier::create(
            tier: 1,
            accessType: CreationAccessLevel::Everyone,
            accessValue: null,
            daysBeforeEventStart: -1
        );
    }

    public function test_it_throws_exception_for_role_without_access_value(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Access value is required for role and permission access types.');

        CreationPriorityTier::create(
            tier: 1,
            accessType: CreationAccessLevel::Role,
            accessValue: null,
            daysBeforeEventStart: 7
        );
    }

    public function test_it_throws_exception_for_role_with_empty_access_value(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Access value is required for role and permission access types.');

        CreationPriorityTier::create(
            tier: 1,
            accessType: CreationAccessLevel::Role,
            accessValue: '',
            daysBeforeEventStart: 7
        );
    }

    public function test_it_throws_exception_for_permission_without_access_value(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Access value is required for role and permission access types.');

        CreationPriorityTier::create(
            tier: 1,
            accessType: CreationAccessLevel::Permission,
            accessValue: null,
            daysBeforeEventStart: 7
        );
    }

    public function test_it_throws_exception_for_permission_with_empty_access_value(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Access value is required for role and permission access types.');

        CreationPriorityTier::create(
            tier: 1,
            accessType: CreationAccessLevel::Permission,
            accessValue: '',
            daysBeforeEventStart: 7
        );
    }

    public function test_it_creates_from_array_for_everyone(): void
    {
        $tier = CreationPriorityTier::fromArray([
            'tier' => 3,
            'type' => 'everyone',
            'value' => null,
            'days_before' => 0,
        ]);

        $this->assertEquals(3, $tier->tier);
        $this->assertEquals(CreationAccessLevel::Everyone, $tier->accessType);
        $this->assertNull($tier->accessValue);
        $this->assertEquals(0, $tier->daysBeforeEventStart);
    }

    public function test_it_creates_from_array_for_registered(): void
    {
        $tier = CreationPriorityTier::fromArray([
            'tier' => 2,
            'type' => 'registered',
            'value' => null,
            'days_before' => 3,
        ]);

        $this->assertEquals(2, $tier->tier);
        $this->assertEquals(CreationAccessLevel::Registered, $tier->accessType);
        $this->assertNull($tier->accessValue);
        $this->assertEquals(3, $tier->daysBeforeEventStart);
    }

    public function test_it_creates_from_array_for_role(): void
    {
        $tier = CreationPriorityTier::fromArray([
            'tier' => 1,
            'type' => 'role',
            'value' => 'admin',
            'days_before' => 7,
        ]);

        $this->assertEquals(1, $tier->tier);
        $this->assertEquals(CreationAccessLevel::Role, $tier->accessType);
        $this->assertEquals('admin', $tier->accessValue);
        $this->assertEquals(7, $tier->daysBeforeEventStart);
    }

    public function test_it_creates_from_array_for_permission(): void
    {
        $tier = CreationPriorityTier::fromArray([
            'tier' => 1,
            'type' => 'permission',
            'value' => 'create_tables_early',
            'days_before' => 14,
        ]);

        $this->assertEquals(1, $tier->tier);
        $this->assertEquals(CreationAccessLevel::Permission, $tier->accessType);
        $this->assertEquals('create_tables_early', $tier->accessValue);
        $this->assertEquals(14, $tier->daysBeforeEventStart);
    }

    public function test_it_converts_to_array_for_everyone(): void
    {
        $tier = CreationPriorityTier::create(
            tier: 3,
            accessType: CreationAccessLevel::Everyone,
            accessValue: null,
            daysBeforeEventStart: 0
        );

        $array = $tier->toArray();

        $this->assertArrayHasKey('tier', $array);
        $this->assertArrayHasKey('type', $array);
        $this->assertArrayHasKey('value', $array);
        $this->assertArrayHasKey('days_before', $array);
        $this->assertEquals(3, $array['tier']);
        $this->assertEquals('everyone', $array['type']);
        $this->assertNull($array['value']);
        $this->assertEquals(0, $array['days_before']);
    }

    public function test_it_converts_to_array_for_role(): void
    {
        $tier = CreationPriorityTier::create(
            tier: 1,
            accessType: CreationAccessLevel::Role,
            accessValue: 'admin',
            daysBeforeEventStart: 7
        );

        $array = $tier->toArray();

        $this->assertEquals(1, $array['tier']);
        $this->assertEquals('role', $array['type']);
        $this->assertEquals('admin', $array['value']);
        $this->assertEquals(7, $array['days_before']);
    }

    public function test_it_compares_equal_tiers(): void
    {
        $tier1 = CreationPriorityTier::create(
            tier: 1,
            accessType: CreationAccessLevel::Role,
            accessValue: 'admin',
            daysBeforeEventStart: 7
        );

        $tier2 = CreationPriorityTier::create(
            tier: 1,
            accessType: CreationAccessLevel::Role,
            accessValue: 'admin',
            daysBeforeEventStart: 7
        );

        $this->assertTrue($tier1->equals($tier2));
    }

    public function test_it_compares_different_tier_numbers(): void
    {
        $tier1 = CreationPriorityTier::create(
            tier: 1,
            accessType: CreationAccessLevel::Everyone,
            accessValue: null,
            daysBeforeEventStart: 0
        );

        $tier2 = CreationPriorityTier::create(
            tier: 2,
            accessType: CreationAccessLevel::Everyone,
            accessValue: null,
            daysBeforeEventStart: 0
        );

        $this->assertFalse($tier1->equals($tier2));
    }

    public function test_it_compares_different_access_types(): void
    {
        $tier1 = CreationPriorityTier::create(
            tier: 1,
            accessType: CreationAccessLevel::Everyone,
            accessValue: null,
            daysBeforeEventStart: 7
        );

        $tier2 = CreationPriorityTier::create(
            tier: 1,
            accessType: CreationAccessLevel::Registered,
            accessValue: null,
            daysBeforeEventStart: 7
        );

        $this->assertFalse($tier1->equals($tier2));
    }

    public function test_it_compares_different_access_values(): void
    {
        $tier1 = CreationPriorityTier::create(
            tier: 1,
            accessType: CreationAccessLevel::Role,
            accessValue: 'admin',
            daysBeforeEventStart: 7
        );

        $tier2 = CreationPriorityTier::create(
            tier: 1,
            accessType: CreationAccessLevel::Role,
            accessValue: 'moderator',
            daysBeforeEventStart: 7
        );

        $this->assertFalse($tier1->equals($tier2));
    }

    public function test_it_compares_different_days_before(): void
    {
        $tier1 = CreationPriorityTier::create(
            tier: 1,
            accessType: CreationAccessLevel::Everyone,
            accessValue: null,
            daysBeforeEventStart: 7
        );

        $tier2 = CreationPriorityTier::create(
            tier: 1,
            accessType: CreationAccessLevel::Everyone,
            accessValue: null,
            daysBeforeEventStart: 14
        );

        $this->assertFalse($tier1->equals($tier2));
    }

    public function test_it_calculates_creation_open_date_with_zero_days(): void
    {
        $tier = CreationPriorityTier::create(
            tier: 3,
            accessType: CreationAccessLevel::Everyone,
            accessValue: null,
            daysBeforeEventStart: 0
        );

        $eventStartDate = new DateTimeImmutable('2026-02-15 10:00:00');
        $openDate = $tier->getCreationOpenDate($eventStartDate);

        $this->assertEquals(
            new DateTimeImmutable('2026-02-15 10:00:00'),
            $openDate
        );
    }

    public function test_it_calculates_creation_open_date_with_seven_days(): void
    {
        $tier = CreationPriorityTier::create(
            tier: 1,
            accessType: CreationAccessLevel::Role,
            accessValue: 'admin',
            daysBeforeEventStart: 7
        );

        $eventStartDate = new DateTimeImmutable('2026-02-15 10:00:00');
        $openDate = $tier->getCreationOpenDate($eventStartDate);

        $this->assertEquals(
            new DateTimeImmutable('2026-02-08 10:00:00'),
            $openDate
        );
    }

    public function test_it_calculates_creation_open_date_with_thirty_days(): void
    {
        $tier = CreationPriorityTier::create(
            tier: 1,
            accessType: CreationAccessLevel::Permission,
            accessValue: 'early_access',
            daysBeforeEventStart: 30
        );

        $eventStartDate = new DateTimeImmutable('2026-03-15 18:00:00');
        $openDate = $tier->getCreationOpenDate($eventStartDate);

        $this->assertEquals(
            new DateTimeImmutable('2026-02-13 18:00:00'),
            $openDate
        );
    }

    public function test_it_preserves_time_when_calculating_open_date(): void
    {
        $tier = CreationPriorityTier::create(
            tier: 2,
            accessType: CreationAccessLevel::Registered,
            accessValue: null,
            daysBeforeEventStart: 3
        );

        $eventStartDate = new DateTimeImmutable('2026-02-15 23:59:59');
        $openDate = $tier->getCreationOpenDate($eventStartDate);

        $this->assertEquals(
            new DateTimeImmutable('2026-02-12 23:59:59'),
            $openDate
        );
    }
}
