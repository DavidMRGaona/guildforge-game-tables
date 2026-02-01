<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Unit\Domain\Entities;

use DateTimeImmutable;
use Modules\GameTables\Domain\Entities\EventGameTableConfig;
use Modules\GameTables\Domain\Enums\CreationAccessLevel;
use Modules\GameTables\Domain\Enums\LocationMode;
use Modules\GameTables\Domain\Enums\SchedulingMode;
use Modules\GameTables\Domain\ValueObjects\EarlyAccessTier;
use Modules\GameTables\Domain\ValueObjects\EligibilityOverride;
use Modules\GameTables\Domain\ValueObjects\TimeSlotDefinition;
use PHPUnit\Framework\TestCase;

final class EventGameTableConfigTest extends TestCase
{
    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createConfig(array $overrides = []): EventGameTableConfig
    {
        return new EventGameTableConfig(
            eventId: $overrides['eventId'] ?? 'event-123',
            tablesEnabled: $overrides['tablesEnabled'] ?? false,
            schedulingMode: $overrides['schedulingMode'] ?? SchedulingMode::FreeSchedule,
            timeSlots: $overrides['timeSlots'] ?? [],
            locationMode: $overrides['locationMode'] ?? LocationMode::FreeChoice,
            fixedLocation: $overrides['fixedLocation'] ?? null,
            eligibilityOverride: $overrides['eligibilityOverride'] ?? null,
            earlyAccessEnabled: $overrides['earlyAccessEnabled'] ?? false,
            creationOpensAt: $overrides['creationOpensAt'] ?? null,
            earlyAccessTier: $overrides['earlyAccessTier'] ?? null,
        );
    }

    public function test_it_creates_config_with_defaults(): void
    {
        $config = new EventGameTableConfig(eventId: 'event-123');

        $this->assertEquals('event-123', $config->eventId());
        $this->assertFalse($config->isEnabled());
        $this->assertEquals(SchedulingMode::FreeSchedule, $config->schedulingMode());
        $this->assertEmpty($config->timeSlots());
        $this->assertEquals(LocationMode::FreeChoice, $config->locationMode());
        $this->assertNull($config->fixedLocation());
        $this->assertNull($config->eligibilityOverride());
    }

    public function test_it_creates_config_with_custom_values(): void
    {
        $timeSlot = TimeSlotDefinition::create(
            label: 'Morning',
            startTime: new DateTimeImmutable('2026-03-01 09:00:00'),
            endTime: new DateTimeImmutable('2026-03-01 13:00:00'),
            maxTables: 5,
        );

        $eligibilityOverride = EligibilityOverride::create(
            accessLevel: CreationAccessLevel::Role,
            allowedRoles: ['editor'],
        );

        $config = new EventGameTableConfig(
            eventId: 'event-456',
            tablesEnabled: true,
            schedulingMode: SchedulingMode::SlotBased,
            timeSlots: [$timeSlot],
            locationMode: LocationMode::FixedLocation,
            fixedLocation: 'Room A',
            eligibilityOverride: $eligibilityOverride,
        );

        $this->assertEquals('event-456', $config->eventId());
        $this->assertTrue($config->isEnabled());
        $this->assertEquals(SchedulingMode::SlotBased, $config->schedulingMode());
        $this->assertCount(1, $config->timeSlots());
        $this->assertEquals(LocationMode::FixedLocation, $config->locationMode());
        $this->assertEquals('Room A', $config->fixedLocation());
        $this->assertNotNull($config->eligibilityOverride());
    }

    public function test_is_enabled_returns_tables_enabled_state(): void
    {
        $enabledConfig = $this->createConfig(['tablesEnabled' => true]);
        $disabledConfig = $this->createConfig(['tablesEnabled' => false]);

        $this->assertTrue($enabledConfig->isEnabled());
        $this->assertFalse($disabledConfig->isEnabled());
    }

    public function test_has_eligibility_override_returns_true_when_set(): void
    {
        $configWithOverride = $this->createConfig([
            'eligibilityOverride' => EligibilityOverride::create(
                accessLevel: CreationAccessLevel::Registered,
            ),
        ]);
        $configWithoutOverride = $this->createConfig();

        $this->assertTrue($configWithOverride->hasEligibilityOverride());
        $this->assertFalse($configWithoutOverride->hasEligibilityOverride());
    }

    public function test_get_effective_location_returns_fixed_location_when_mode_is_fixed(): void
    {
        $config = $this->createConfig([
            'locationMode' => LocationMode::FixedLocation,
            'fixedLocation' => 'Conference Room B',
        ]);

        $this->assertEquals('Conference Room B', $config->getEffectiveLocation('Event Hall'));
    }

    public function test_get_effective_location_returns_event_location_when_mode_is_event(): void
    {
        $config = $this->createConfig([
            'locationMode' => LocationMode::EventLocation,
        ]);

        $this->assertEquals('Event Hall', $config->getEffectiveLocation('Event Hall'));
    }

    public function test_get_effective_location_returns_null_when_mode_is_free_choice(): void
    {
        $config = $this->createConfig([
            'locationMode' => LocationMode::FreeChoice,
        ]);

        $this->assertNull($config->getEffectiveLocation('Event Hall'));
    }

    public function test_get_effective_location_returns_null_when_event_mode_but_no_event_location(): void
    {
        $config = $this->createConfig([
            'locationMode' => LocationMode::EventLocation,
        ]);

        $this->assertNull($config->getEffectiveLocation(null));
    }

    public function test_is_slot_based_returns_correct_value(): void
    {
        $slotBasedConfig = $this->createConfig(['schedulingMode' => SchedulingMode::SlotBased]);
        $freeScheduleConfig = $this->createConfig(['schedulingMode' => SchedulingMode::FreeSchedule]);

        $this->assertTrue($slotBasedConfig->isSlotBased());
        $this->assertFalse($freeScheduleConfig->isSlotBased());
    }

    public function test_requires_location_input_returns_correct_value(): void
    {
        $freeChoiceConfig = $this->createConfig(['locationMode' => LocationMode::FreeChoice]);
        $fixedConfig = $this->createConfig(['locationMode' => LocationMode::FixedLocation]);
        $eventConfig = $this->createConfig(['locationMode' => LocationMode::EventLocation]);

        $this->assertTrue($freeChoiceConfig->requiresLocationInput());
        $this->assertFalse($fixedConfig->requiresLocationInput());
        $this->assertFalse($eventConfig->requiresLocationInput());
    }

    public function test_it_sets_tables_enabled(): void
    {
        $config = $this->createConfig();

        $config->setTablesEnabled(true);

        $this->assertTrue($config->isEnabled());
    }

    public function test_it_sets_scheduling_mode(): void
    {
        $config = $this->createConfig();

        $config->setSchedulingMode(SchedulingMode::SlotBased);

        $this->assertEquals(SchedulingMode::SlotBased, $config->schedulingMode());
    }

    public function test_it_sets_time_slots(): void
    {
        $config = $this->createConfig();
        $timeSlots = [
            TimeSlotDefinition::create(
                label: 'Morning',
                startTime: new DateTimeImmutable('2026-03-01 09:00:00'),
                endTime: new DateTimeImmutable('2026-03-01 13:00:00'),
            ),
        ];

        $config->setTimeSlots($timeSlots);

        $this->assertCount(1, $config->timeSlots());
    }

    public function test_it_sets_location_mode(): void
    {
        $config = $this->createConfig();

        $config->setLocationMode(LocationMode::EventLocation);

        $this->assertEquals(LocationMode::EventLocation, $config->locationMode());
    }

    public function test_it_sets_fixed_location(): void
    {
        $config = $this->createConfig();

        $config->setFixedLocation('Main Hall');

        $this->assertEquals('Main Hall', $config->fixedLocation());
    }

    public function test_it_sets_eligibility_override(): void
    {
        $config = $this->createConfig();
        $override = EligibilityOverride::create(
            accessLevel: CreationAccessLevel::Permission,
            requiredPermission: 'gametables.create',
        );

        $config->setEligibilityOverride($override);

        $this->assertNotNull($config->eligibilityOverride());
        $this->assertEquals(CreationAccessLevel::Permission, $config->eligibilityOverride()->accessLevel);
    }

    public function test_it_clears_eligibility_override(): void
    {
        $config = $this->createConfig([
            'eligibilityOverride' => EligibilityOverride::create(
                accessLevel: CreationAccessLevel::Registered,
            ),
        ]);

        $config->setEligibilityOverride(null);

        $this->assertNull($config->eligibilityOverride());
    }

    // Early Access Tests

    public function test_it_creates_config_with_defaults_has_no_early_access(): void
    {
        $config = new EventGameTableConfig(eventId: 'event-123');

        $this->assertFalse($config->hasEarlyAccess());
        $this->assertNull($config->creationOpensAt());
        $this->assertNull($config->earlyAccessTier());
    }

    public function test_it_creates_config_with_early_access(): void
    {
        $creationOpensAt = new DateTimeImmutable('2026-03-01 00:00:00');
        $earlyAccessTier = EarlyAccessTier::create(
            accessType: CreationAccessLevel::Role,
            allowedRoles: ['socio'],
            daysBeforeOpening: 3,
        );

        $config = new EventGameTableConfig(
            eventId: 'event-123',
            tablesEnabled: true,
            earlyAccessEnabled: true,
            creationOpensAt: $creationOpensAt,
            earlyAccessTier: $earlyAccessTier,
        );

        $this->assertTrue($config->hasEarlyAccess());
        $this->assertEquals($creationOpensAt, $config->creationOpensAt());
        $this->assertNotNull($config->earlyAccessTier());
        $this->assertEquals(3, $config->earlyAccessTier()->daysBeforeOpening);
    }

    public function test_has_early_access_requires_both_enabled_and_opens_at(): void
    {
        // Only enabled, no date
        $configOnlyEnabled = $this->createConfig([
            'earlyAccessEnabled' => true,
            'creationOpensAt' => null,
        ]);
        $this->assertFalse($configOnlyEnabled->hasEarlyAccess());

        // Only date, not enabled
        $configOnlyDate = $this->createConfig([
            'earlyAccessEnabled' => false,
            'creationOpensAt' => new DateTimeImmutable('2026-03-01'),
        ]);
        $this->assertFalse($configOnlyDate->hasEarlyAccess());

        // Both enabled and date
        $configBoth = $this->createConfig([
            'earlyAccessEnabled' => true,
            'creationOpensAt' => new DateTimeImmutable('2026-03-01'),
        ]);
        $this->assertTrue($configBoth->hasEarlyAccess());
    }

    public function test_it_sets_early_access_enabled(): void
    {
        $config = $this->createConfig();

        $config->setEarlyAccessEnabled(true);

        $this->assertTrue($config->isEarlyAccessEnabled());
    }

    public function test_it_sets_creation_opens_at(): void
    {
        $config = $this->createConfig();
        $openDate = new DateTimeImmutable('2026-03-01 00:00:00');

        $config->setCreationOpensAt($openDate);

        $this->assertEquals($openDate, $config->creationOpensAt());
    }

    public function test_it_sets_early_access_tier(): void
    {
        $config = $this->createConfig();
        $tier = EarlyAccessTier::create(
            accessType: CreationAccessLevel::Role,
            allowedRoles: ['socio'],
            daysBeforeOpening: 5,
        );

        $config->setEarlyAccessTier($tier);

        $this->assertNotNull($config->earlyAccessTier());
        $this->assertEquals(5, $config->earlyAccessTier()->daysBeforeOpening);
    }

    public function test_it_clears_early_access_tier(): void
    {
        $config = $this->createConfig([
            'earlyAccessTier' => EarlyAccessTier::create(
                accessType: CreationAccessLevel::Role,
                allowedRoles: ['socio'],
                daysBeforeOpening: 3,
            ),
        ]);

        $config->setEarlyAccessTier(null);

        $this->assertNull($config->earlyAccessTier());
    }
}
