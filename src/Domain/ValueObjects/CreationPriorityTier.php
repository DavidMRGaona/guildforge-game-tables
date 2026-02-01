<?php

declare(strict_types=1);

namespace Modules\GameTables\Domain\ValueObjects;

use DateTimeImmutable;
use InvalidArgumentException;
use Modules\GameTables\Domain\Enums\CreationAccessLevel;

final readonly class CreationPriorityTier
{
    public function __construct(
        public int $tier,
        public CreationAccessLevel $accessType,
        public ?string $accessValue,
        public int $daysBeforeEventStart,
    ) {
        if ($tier < 1) {
            throw new InvalidArgumentException('Tier must be at least 1.');
        }

        if ($daysBeforeEventStart < 0) {
            throw new InvalidArgumentException('Days before event start must be non-negative.');
        }

        if ($this->requiresAccessValue() && ($accessValue === null || $accessValue === '')) {
            throw new InvalidArgumentException('Access value is required for role and permission access types.');
        }
    }

    public static function create(
        int $tier,
        CreationAccessLevel $accessType,
        ?string $accessValue,
        int $daysBeforeEventStart,
    ): self {
        return new self($tier, $accessType, $accessValue, $daysBeforeEventStart);
    }

    /**
     * @param  array{tier: int, type: string, value: ?string, days_before: int}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            tier: $data['tier'],
            accessType: CreationAccessLevel::from($data['type']),
            accessValue: $data['value'],
            daysBeforeEventStart: $data['days_before'],
        );
    }

    /**
     * @return array{tier: int, type: string, value: ?string, days_before: int}
     */
    public function toArray(): array
    {
        return [
            'tier' => $this->tier,
            'type' => $this->accessType->value,
            'value' => $this->accessValue,
            'days_before' => $this->daysBeforeEventStart,
        ];
    }

    public function equals(self $other): bool
    {
        return $this->tier === $other->tier
            && $this->accessType === $other->accessType
            && $this->accessValue === $other->accessValue
            && $this->daysBeforeEventStart === $other->daysBeforeEventStart;
    }

    public function getCreationOpenDate(DateTimeImmutable $eventStartDate): DateTimeImmutable
    {
        return $eventStartDate->modify("-{$this->daysBeforeEventStart} days");
    }

    private function requiresAccessValue(): bool
    {
        return $this->accessType === CreationAccessLevel::Role
            || $this->accessType === CreationAccessLevel::Permission;
    }
}
