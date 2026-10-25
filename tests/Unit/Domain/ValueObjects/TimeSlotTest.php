<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Unit\Domain\ValueObjects;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use Modules\GameTables\Domain\ValueObjects\TimeSlot;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TimeSlotTest extends TestCase
{
    public function test_it_creates_time_slot(): void
    {
        $startsAt = new DateTimeImmutable('2026-01-26 18:00:00');

        $timeSlot = new TimeSlot($startsAt, 240);

        $this->assertEquals($startsAt, $timeSlot->startsAt);
        $this->assertEquals(240, $timeSlot->durationMinutes);
    }

    public function test_it_throws_exception_for_zero_duration(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Duration must be greater than zero.');

        new TimeSlot(new DateTimeImmutable, 0);
    }

    public function test_it_throws_exception_for_negative_duration(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Duration must be greater than zero.');

        new TimeSlot(new DateTimeImmutable, -60);
    }

    public function test_it_calculates_ends_at(): void
    {
        $startsAt = new DateTimeImmutable('2026-01-26 18:00:00');
        $timeSlot = new TimeSlot($startsAt, 240);

        $endsAt = $timeSlot->endsAt();

        $this->assertEquals(new DateTimeImmutable('2026-01-26 22:00:00'), $endsAt);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function clockChangeNights(): array
    {
        return [
            // The clocks go back at 03:00: four hours from 01:00 summer time end at 04:00 winter time
            'october' => ['2026-10-25 01:00:00', '2026-10-25T04:00:00+01:00'],
            // The clocks go forward at 02:00: four hours from 00:30 winter time end at 05:30 summer time
            'march' => ['2026-03-29 00:30:00', '2026-03-29T05:30:00+02:00'],
        ];
    }

    #[DataProvider('clockChangeNights')]
    public function test_the_duration_is_elapsed_time_across_a_clock_change(string $startsAt, string $expected): void
    {
        $start = new DateTimeImmutable($startsAt, new DateTimeZone('Europe/Madrid'));
        $timeSlot = new TimeSlot($start, 240);

        $this->assertSame($expected, $timeSlot->endsAt()->format('c'));
        $this->assertSame(240 * 60, $timeSlot->endsAt()->getTimestamp() - $start->getTimestamp());
    }

    public function test_it_calculates_duration_hours(): void
    {
        $timeSlot = new TimeSlot(new DateTimeImmutable, 240);

        $this->assertEquals(4.0, $timeSlot->durationHours());
    }

    public function test_it_calculates_duration_hours_with_fraction(): void
    {
        $timeSlot = new TimeSlot(new DateTimeImmutable, 90);

        $this->assertEquals(1.5, $timeSlot->durationHours());
    }

    public function test_it_detects_in_progress(): void
    {
        $startsAt = new DateTimeImmutable('2026-01-26 18:00:00');
        $timeSlot = new TimeSlot($startsAt, 240);

        $this->assertTrue($timeSlot->isInProgress(new DateTimeImmutable('2026-01-26 20:00:00')));
        $this->assertFalse($timeSlot->isInProgress(new DateTimeImmutable('2026-01-26 17:00:00')));
        $this->assertFalse($timeSlot->isInProgress(new DateTimeImmutable('2026-01-26 23:00:00')));
    }

    public function test_it_detects_has_started(): void
    {
        $startsAt = new DateTimeImmutable('2026-01-26 18:00:00');
        $timeSlot = new TimeSlot($startsAt, 240);

        $this->assertTrue($timeSlot->hasStarted(new DateTimeImmutable('2026-01-26 18:00:00')));
        $this->assertTrue($timeSlot->hasStarted(new DateTimeImmutable('2026-01-26 20:00:00')));
        $this->assertFalse($timeSlot->hasStarted(new DateTimeImmutable('2026-01-26 17:00:00')));
    }

    public function test_it_detects_has_ended(): void
    {
        $startsAt = new DateTimeImmutable('2026-01-26 18:00:00');
        $timeSlot = new TimeSlot($startsAt, 240);

        $this->assertTrue($timeSlot->hasEnded(new DateTimeImmutable('2026-01-26 22:00:00')));
        $this->assertTrue($timeSlot->hasEnded(new DateTimeImmutable('2026-01-26 23:00:00')));
        $this->assertFalse($timeSlot->hasEnded(new DateTimeImmutable('2026-01-26 21:00:00')));
    }

    public function test_it_detects_overlapping_slots(): void
    {
        $slot1 = new TimeSlot(new DateTimeImmutable('2026-01-26 18:00:00'), 240);
        $slot2 = new TimeSlot(new DateTimeImmutable('2026-01-26 20:00:00'), 240);
        $slot3 = new TimeSlot(new DateTimeImmutable('2026-01-26 22:00:00'), 240);

        $this->assertTrue($slot1->overlaps($slot2));
        $this->assertFalse($slot1->overlaps($slot3));
    }

    public function test_it_compares_equality(): void
    {
        $startsAt = new DateTimeImmutable('2026-01-26 18:00:00');
        $slot1 = new TimeSlot($startsAt, 240);
        $slot2 = new TimeSlot($startsAt, 240);
        $slot3 = new TimeSlot($startsAt, 180);

        $this->assertTrue($slot1->equals($slot2));
        $this->assertFalse($slot1->equals($slot3));
    }

    public function test_it_converts_to_array(): void
    {
        $startsAt = new DateTimeImmutable('2026-01-26 18:00:00');
        $timeSlot = new TimeSlot($startsAt, 240);

        $array = $timeSlot->toArray();

        $this->assertArrayHasKey('starts_at', $array);
        $this->assertArrayHasKey('duration_minutes', $array);
        $this->assertEquals(240, $array['duration_minutes']);
    }
}
