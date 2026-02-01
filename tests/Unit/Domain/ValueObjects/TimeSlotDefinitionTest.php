<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Unit\Domain\ValueObjects;

use DateTimeImmutable;
use InvalidArgumentException;
use Modules\GameTables\Domain\ValueObjects\TimeSlotDefinition;
use PHPUnit\Framework\TestCase;

final class TimeSlotDefinitionTest extends TestCase
{
    public function test_it_creates_time_slot_with_required_data(): void
    {
        $startTime = new DateTimeImmutable('2026-03-01 09:00:00');
        $endTime = new DateTimeImmutable('2026-03-01 13:00:00');

        $timeSlot = new TimeSlotDefinition(
            label: 'Morning',
            startTime: $startTime,
            endTime: $endTime,
        );

        $this->assertEquals('Morning', $timeSlot->label);
        $this->assertEquals($startTime, $timeSlot->startTime);
        $this->assertEquals($endTime, $timeSlot->endTime);
        $this->assertNull($timeSlot->maxTables);
    }

    public function test_it_creates_time_slot_with_max_tables(): void
    {
        $timeSlot = TimeSlotDefinition::create(
            label: 'Afternoon',
            startTime: new DateTimeImmutable('2026-03-01 14:00:00'),
            endTime: new DateTimeImmutable('2026-03-01 18:00:00'),
            maxTables: 5,
        );

        $this->assertEquals(5, $timeSlot->maxTables);
    }

    public function test_it_throws_exception_for_empty_label(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Time slot label cannot be empty.');

        new TimeSlotDefinition(
            label: '',
            startTime: new DateTimeImmutable('2026-03-01 09:00:00'),
            endTime: new DateTimeImmutable('2026-03-01 13:00:00'),
        );
    }

    public function test_it_throws_exception_for_whitespace_only_label(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Time slot label cannot be empty.');

        new TimeSlotDefinition(
            label: '   ',
            startTime: new DateTimeImmutable('2026-03-01 09:00:00'),
            endTime: new DateTimeImmutable('2026-03-01 13:00:00'),
        );
    }

    public function test_it_throws_exception_when_end_time_before_start_time(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('End time must be after start time.');

        new TimeSlotDefinition(
            label: 'Invalid',
            startTime: new DateTimeImmutable('2026-03-01 13:00:00'),
            endTime: new DateTimeImmutable('2026-03-01 09:00:00'),
        );
    }

    public function test_it_throws_exception_when_end_time_equals_start_time(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('End time must be after start time.');

        new TimeSlotDefinition(
            label: 'Invalid',
            startTime: new DateTimeImmutable('2026-03-01 09:00:00'),
            endTime: new DateTimeImmutable('2026-03-01 09:00:00'),
        );
    }

    public function test_it_throws_exception_for_zero_max_tables(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Max tables must be at least 1 if specified.');

        new TimeSlotDefinition(
            label: 'Morning',
            startTime: new DateTimeImmutable('2026-03-01 09:00:00'),
            endTime: new DateTimeImmutable('2026-03-01 13:00:00'),
            maxTables: 0,
        );
    }

    public function test_it_throws_exception_for_negative_max_tables(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Max tables must be at least 1 if specified.');

        new TimeSlotDefinition(
            label: 'Morning',
            startTime: new DateTimeImmutable('2026-03-01 09:00:00'),
            endTime: new DateTimeImmutable('2026-03-01 13:00:00'),
            maxTables: -1,
        );
    }

    public function test_it_calculates_duration_in_minutes(): void
    {
        $timeSlot = TimeSlotDefinition::create(
            label: 'Morning',
            startTime: new DateTimeImmutable('2026-03-01 09:00:00'),
            endTime: new DateTimeImmutable('2026-03-01 13:00:00'),
        );

        $this->assertEquals(240, $timeSlot->durationMinutes());
    }

    public function test_has_capacity_limit_returns_correct_value(): void
    {
        $withLimit = TimeSlotDefinition::create(
            label: 'Morning',
            startTime: new DateTimeImmutable('2026-03-01 09:00:00'),
            endTime: new DateTimeImmutable('2026-03-01 13:00:00'),
            maxTables: 5,
        );

        $withoutLimit = TimeSlotDefinition::create(
            label: 'Afternoon',
            startTime: new DateTimeImmutable('2026-03-01 14:00:00'),
            endTime: new DateTimeImmutable('2026-03-01 18:00:00'),
        );

        $this->assertTrue($withLimit->hasCapacityLimit());
        $this->assertFalse($withoutLimit->hasCapacityLimit());
    }

    public function test_overlaps_detects_overlapping_slots(): void
    {
        $slot1 = TimeSlotDefinition::create(
            label: 'Slot 1',
            startTime: new DateTimeImmutable('2026-03-01 09:00:00'),
            endTime: new DateTimeImmutable('2026-03-01 13:00:00'),
        );

        $slot2 = TimeSlotDefinition::create(
            label: 'Slot 2',
            startTime: new DateTimeImmutable('2026-03-01 12:00:00'),
            endTime: new DateTimeImmutable('2026-03-01 16:00:00'),
        );

        $this->assertTrue($slot1->overlaps($slot2));
        $this->assertTrue($slot2->overlaps($slot1));
    }

    public function test_overlaps_returns_false_for_adjacent_slots(): void
    {
        $slot1 = TimeSlotDefinition::create(
            label: 'Morning',
            startTime: new DateTimeImmutable('2026-03-01 09:00:00'),
            endTime: new DateTimeImmutable('2026-03-01 13:00:00'),
        );

        $slot2 = TimeSlotDefinition::create(
            label: 'Afternoon',
            startTime: new DateTimeImmutable('2026-03-01 13:00:00'),
            endTime: new DateTimeImmutable('2026-03-01 17:00:00'),
        );

        $this->assertFalse($slot1->overlaps($slot2));
        $this->assertFalse($slot2->overlaps($slot1));
    }

    public function test_contains_checks_if_time_is_within_slot(): void
    {
        $slot = TimeSlotDefinition::create(
            label: 'Morning',
            startTime: new DateTimeImmutable('2026-03-01 09:00:00'),
            endTime: new DateTimeImmutable('2026-03-01 13:00:00'),
        );

        $this->assertTrue($slot->contains(new DateTimeImmutable('2026-03-01 10:00:00')));
        $this->assertTrue($slot->contains(new DateTimeImmutable('2026-03-01 09:00:00')));
        $this->assertFalse($slot->contains(new DateTimeImmutable('2026-03-01 13:00:00')));
        $this->assertFalse($slot->contains(new DateTimeImmutable('2026-03-01 08:00:00')));
    }

    public function test_equals_compares_all_properties(): void
    {
        $slot1 = TimeSlotDefinition::create(
            label: 'Morning',
            startTime: new DateTimeImmutable('2026-03-01 09:00:00'),
            endTime: new DateTimeImmutable('2026-03-01 13:00:00'),
            maxTables: 5,
        );

        $slot2 = TimeSlotDefinition::create(
            label: 'Morning',
            startTime: new DateTimeImmutable('2026-03-01 09:00:00'),
            endTime: new DateTimeImmutable('2026-03-01 13:00:00'),
            maxTables: 5,
        );

        $slot3 = TimeSlotDefinition::create(
            label: 'Afternoon',
            startTime: new DateTimeImmutable('2026-03-01 09:00:00'),
            endTime: new DateTimeImmutable('2026-03-01 13:00:00'),
            maxTables: 5,
        );

        $this->assertTrue($slot1->equals($slot2));
        $this->assertFalse($slot1->equals($slot3));
    }

    public function test_from_array_creates_instance(): void
    {
        $slot = TimeSlotDefinition::fromArray([
            'label' => 'Morning',
            'start_time' => '2026-03-01T09:00:00+00:00',
            'end_time' => '2026-03-01T13:00:00+00:00',
            'max_tables' => 5,
        ]);

        $this->assertEquals('Morning', $slot->label);
        $this->assertEquals(5, $slot->maxTables);
    }

    public function test_to_array_returns_correct_format(): void
    {
        $startTime = new DateTimeImmutable('2026-03-01 09:00:00+00:00');
        $endTime = new DateTimeImmutable('2026-03-01 13:00:00+00:00');

        $slot = TimeSlotDefinition::create(
            label: 'Morning',
            startTime: $startTime,
            endTime: $endTime,
            maxTables: 5,
        );

        $array = $slot->toArray();

        $this->assertEquals('Morning', $array['label']);
        $this->assertEquals($startTime->format('c'), $array['start_time']);
        $this->assertEquals($endTime->format('c'), $array['end_time']);
        $this->assertEquals(5, $array['max_tables']);
    }
}
