<?php

declare(strict_types=1);

namespace Modules\GameTables\Domain\ValueObjects;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Represents a predefined time slot for event game table scheduling.
 */
final readonly class TimeSlotDefinition
{
    public function __construct(
        public string $label,
        public DateTimeImmutable $startTime,
        public DateTimeImmutable $endTime,
        public ?int $maxTables = null,
    ) {
        if (trim($label) === '') {
            throw new InvalidArgumentException('Time slot label cannot be empty.');
        }

        if ($endTime <= $startTime) {
            throw new InvalidArgumentException('End time must be after start time.');
        }

        if ($maxTables !== null && $maxTables < 1) {
            throw new InvalidArgumentException('Max tables must be at least 1 if specified.');
        }
    }

    public static function create(
        string $label,
        DateTimeImmutable $startTime,
        DateTimeImmutable $endTime,
        ?int $maxTables = null,
    ): self {
        return new self($label, $startTime, $endTime, $maxTables);
    }

    /**
     * @param  array{label: string, start_time: string, end_time: string, max_tables?: int|null}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            label: $data['label'],
            startTime: new DateTimeImmutable($data['start_time']),
            endTime: new DateTimeImmutable($data['end_time']),
            maxTables: $data['max_tables'] ?? null,
        );
    }

    public function durationMinutes(): int
    {
        return (int) (($this->endTime->getTimestamp() - $this->startTime->getTimestamp()) / 60);
    }

    public function hasCapacityLimit(): bool
    {
        return $this->maxTables !== null;
    }

    public function overlaps(self $other): bool
    {
        return $this->startTime < $other->endTime && $this->endTime > $other->startTime;
    }

    public function contains(DateTimeImmutable $time): bool
    {
        return $time >= $this->startTime && $time < $this->endTime;
    }

    public function equals(self $other): bool
    {
        return $this->label === $other->label
            && $this->startTime == $other->startTime
            && $this->endTime == $other->endTime
            && $this->maxTables === $other->maxTables;
    }

    /**
     * @return array{label: string, start_time: string, end_time: string, max_tables: int|null}
     */
    public function toArray(): array
    {
        return [
            'label' => $this->label,
            'start_time' => $this->startTime->format('c'),
            'end_time' => $this->endTime->format('c'),
            'max_tables' => $this->maxTables,
        ];
    }
}
