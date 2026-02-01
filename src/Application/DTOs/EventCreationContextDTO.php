<?php

declare(strict_types=1);

namespace Modules\GameTables\Application\DTOs;

use DateTimeImmutable;
use Modules\GameTables\Domain\Entities\EventGameTableConfig;

/**
 * DTO providing the context needed for creating a game table within an event.
 * This includes configuration restrictions and event-specific constraints.
 */
final readonly class EventCreationContextDTO
{
    /**
     * @param  array<array{label: string, start_time: string, end_time: string, max_tables: int|null, available_slots?: int|null}>  $availableTimeSlots
     */
    public function __construct(
        public string $eventId,
        public bool $tablesEnabled,
        public bool $isSlotBased,
        public array $availableTimeSlots,
        public bool $requiresLocationInput,
        public ?string $effectiveLocation,
        public ?string $eventStartDate,
        public ?string $eventEndDate,
        public bool $hasEligibilityOverride,
        public bool $hasEarlyAccess = false,
        public ?string $creationOpensAt = null,
        public ?string $earlyAccessOpensAt = null,
        public ?int $earlyAccessDaysBefore = null,
    ) {}

    public static function fromConfig(
        EventGameTableConfig $config,
        ?string $eventLocation = null,
        ?DateTimeImmutable $eventStartDate = null,
        ?DateTimeImmutable $eventEndDate = null,
    ): self {
        $availableTimeSlots = array_map(
            fn ($slot): array => $slot->toArray(),
            $config->timeSlots()
        );

        // Calculate early access dates
        $hasEarlyAccess = $config->hasEarlyAccess();
        $creationOpensAt = $config->creationOpensAt();
        $earlyAccessTier = $config->earlyAccessTier();

        $earlyAccessOpensAt = null;
        $earlyAccessDaysBefore = null;

        if ($hasEarlyAccess && $creationOpensAt !== null && $earlyAccessTier !== null) {
            $earlyAccessOpensAt = $earlyAccessTier->getOpenDate($creationOpensAt)->format('c');
            $earlyAccessDaysBefore = $earlyAccessTier->daysBeforeOpening;
        }

        return new self(
            eventId: $config->eventId(),
            tablesEnabled: $config->isEnabled(),
            isSlotBased: $config->isSlotBased(),
            availableTimeSlots: $availableTimeSlots,
            requiresLocationInput: $config->requiresLocationInput(),
            effectiveLocation: $config->getEffectiveLocation($eventLocation),
            eventStartDate: $eventStartDate?->format('c'),
            eventEndDate: $eventEndDate?->format('c'),
            hasEligibilityOverride: $config->hasEligibilityOverride(),
            hasEarlyAccess: $hasEarlyAccess,
            creationOpensAt: $creationOpensAt?->format('c'),
            earlyAccessOpensAt: $earlyAccessOpensAt,
            earlyAccessDaysBefore: $earlyAccessDaysBefore,
        );
    }

    public static function disabled(string $eventId): self
    {
        return new self(
            eventId: $eventId,
            tablesEnabled: false,
            isSlotBased: false,
            availableTimeSlots: [],
            requiresLocationInput: true,
            effectiveLocation: null,
            eventStartDate: null,
            eventEndDate: null,
            hasEligibilityOverride: false,
            hasEarlyAccess: false,
            creationOpensAt: null,
            earlyAccessOpensAt: null,
            earlyAccessDaysBefore: null,
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
            'is_slot_based' => $this->isSlotBased,
            'available_time_slots' => $this->availableTimeSlots,
            'requires_location_input' => $this->requiresLocationInput,
            'effective_location' => $this->effectiveLocation,
            'event_start_date' => $this->eventStartDate,
            'event_end_date' => $this->eventEndDate,
            'has_eligibility_override' => $this->hasEligibilityOverride,
            'has_early_access' => $this->hasEarlyAccess,
            'creation_opens_at' => $this->creationOpensAt,
            'early_access_opens_at' => $this->earlyAccessOpensAt,
            'early_access_days_before' => $this->earlyAccessDaysBefore,
        ];
    }
}
