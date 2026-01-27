<?php

declare(strict_types=1);

namespace Modules\GameTables\Domain\ValueObjects;

use DateTimeImmutable;
use InvalidArgumentException;

final readonly class TimeSlot
{
    public function __construct(
        public DateTimeImmutable $startsAt,
        public int $durationMinutes,
    ) {
        if ($durationMinutes <= 0) {
            throw new InvalidArgumentException('Duration must be greater than zero.');
        }
    }

    public static function create(DateTimeImmutable $startsAt, int $durationMinutes): self
    {
        return new self($startsAt, $durationMinutes);
    }

    public function endsAt(): DateTimeImmutable
    {
        return $this->startsAt->modify("+{$this->durationMinutes} minutes");
    }

    public function durationHours(): float
    {
        return $this->durationMinutes / 60;
    }

    public function isInProgress(DateTimeImmutable $now): bool
    {
        return $now >= $this->startsAt && $now < $this->endsAt();
    }

    public function hasStarted(DateTimeImmutable $now): bool
    {
        return $now >= $this->startsAt;
    }

    public function hasEnded(DateTimeImmutable $now): bool
    {
        return $now >= $this->endsAt();
    }

    public function isPast(DateTimeImmutable $now): bool
    {
        return $this->hasEnded($now);
    }

    public function isFuture(DateTimeImmutable $now): bool
    {
        return $now < $this->startsAt;
    }

    public function overlaps(self $other): bool
    {
        return $this->startsAt < $other->endsAt() && $this->endsAt() > $other->startsAt;
    }

    public function equals(self $other): bool
    {
        return $this->startsAt == $other->startsAt
            && $this->durationMinutes === $other->durationMinutes;
    }

    /**
     * @return array{starts_at: string, duration_minutes: int}
     */
    public function toArray(): array
    {
        return [
            'starts_at' => $this->startsAt->format('c'),
            'duration_minutes' => $this->durationMinutes,
        ];
    }
}
