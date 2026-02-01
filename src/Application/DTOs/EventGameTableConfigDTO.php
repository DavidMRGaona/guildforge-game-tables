<?php

declare(strict_types=1);

namespace Modules\GameTables\Application\DTOs;

use Modules\GameTables\Domain\Entities\EventGameTableConfig;
use Modules\GameTables\Domain\Enums\LocationMode;
use Modules\GameTables\Domain\Enums\SchedulingMode;
use Modules\GameTables\Domain\ValueObjects\EligibilityOverride;
use Modules\GameTables\Domain\ValueObjects\TimeSlotDefinition;

/**
 * Response DTO for event game table configuration.
 */
final readonly class EventGameTableConfigDTO
{
    /**
     * @param  array<array{label: string, start_time: string, end_time: string, max_tables: int|null}>  $timeSlots
     * @param  array{access_level: string, allowed_roles: array<string>|null, required_permission: string|null}|null  $eligibilityOverride
     * @param  array{access_type: string, allowed_roles: array<string>|null, required_permission: string|null, days_before_opening: int}|null  $earlyAccessTier
     */
    public function __construct(
        public string $eventId,
        public bool $tablesEnabled,
        public string $schedulingMode,
        public array $timeSlots,
        public string $locationMode,
        public ?string $fixedLocation,
        public ?array $eligibilityOverride,
        public bool $earlyAccessEnabled = false,
        public ?string $creationOpensAt = null,
        public ?array $earlyAccessTier = null,
    ) {}

    public static function fromEntity(EventGameTableConfig $config): self
    {
        return new self(
            eventId: $config->eventId(),
            tablesEnabled: $config->isEnabled(),
            schedulingMode: $config->schedulingMode()->value,
            timeSlots: array_map(
                fn (TimeSlotDefinition $slot): array => $slot->toArray(),
                $config->timeSlots()
            ),
            locationMode: $config->locationMode()->value,
            fixedLocation: $config->fixedLocation(),
            eligibilityOverride: $config->eligibilityOverride()?->toArray(),
            earlyAccessEnabled: $config->isEarlyAccessEnabled(),
            creationOpensAt: $config->creationOpensAt()?->format('c'),
            earlyAccessTier: $config->earlyAccessTier()?->toArray(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'event_id' => $this->eventId,
            'tables_enabled' => $this->tablesEnabled,
            'scheduling_mode' => $this->schedulingMode,
            'time_slots' => $this->timeSlots,
            'location_mode' => $this->locationMode,
            'fixed_location' => $this->fixedLocation,
            'eligibility_override' => $this->eligibilityOverride,
            'early_access_enabled' => $this->earlyAccessEnabled,
            'creation_opens_at' => $this->creationOpensAt,
            'early_access_tier' => $this->earlyAccessTier,
        ];
    }
}
