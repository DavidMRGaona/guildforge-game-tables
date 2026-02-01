<?php

declare(strict_types=1);

namespace Modules\GameTables\Domain\Entities;

use DateTimeImmutable;
use Modules\GameTables\Domain\Enums\LocationMode;
use Modules\GameTables\Domain\Enums\SchedulingMode;
use Modules\GameTables\Domain\ValueObjects\EarlyAccessTier;
use Modules\GameTables\Domain\ValueObjects\EligibilityOverride;
use Modules\GameTables\Domain\ValueObjects\TimeSlotDefinition;

/**
 * Configuration for game table creation within an event.
 */
final class EventGameTableConfig
{
    /**
     * @param  array<TimeSlotDefinition>  $timeSlots
     */
    public function __construct(
        private readonly string $eventId,
        private bool $tablesEnabled = false,
        private SchedulingMode $schedulingMode = SchedulingMode::FreeSchedule,
        private array $timeSlots = [],
        private LocationMode $locationMode = LocationMode::FreeChoice,
        private ?string $fixedLocation = null,
        private ?EligibilityOverride $eligibilityOverride = null,
        private bool $earlyAccessEnabled = false,
        private ?DateTimeImmutable $creationOpensAt = null,
        private ?EarlyAccessTier $earlyAccessTier = null,
    ) {}

    public function eventId(): string
    {
        return $this->eventId;
    }

    public function isEnabled(): bool
    {
        return $this->tablesEnabled;
    }

    public function schedulingMode(): SchedulingMode
    {
        return $this->schedulingMode;
    }

    /**
     * @return array<TimeSlotDefinition>
     */
    public function timeSlots(): array
    {
        return $this->timeSlots;
    }

    public function locationMode(): LocationMode
    {
        return $this->locationMode;
    }

    public function fixedLocation(): ?string
    {
        return $this->fixedLocation;
    }

    public function eligibilityOverride(): ?EligibilityOverride
    {
        return $this->eligibilityOverride;
    }

    public function hasEligibilityOverride(): bool
    {
        return $this->eligibilityOverride !== null;
    }

    /**
     * Check if scheduling is slot-based.
     */
    public function isSlotBased(): bool
    {
        return $this->schedulingMode === SchedulingMode::SlotBased;
    }

    /**
     * Check if the user needs to input a location.
     */
    public function requiresLocationInput(): bool
    {
        return $this->locationMode === LocationMode::FreeChoice;
    }

    /**
     * Get the effective location for a game table based on config and event location.
     *
     * Returns:
     * - Fixed location if mode is FixedLocation
     * - Event location if mode is EventLocation
     * - null if mode is FreeChoice (user must provide)
     */
    public function getEffectiveLocation(?string $eventLocation): ?string
    {
        return match ($this->locationMode) {
            LocationMode::FixedLocation => $this->fixedLocation,
            LocationMode::EventLocation => $eventLocation,
            LocationMode::FreeChoice => null,
        };
    }

    public function setTablesEnabled(bool $enabled): void
    {
        $this->tablesEnabled = $enabled;
    }

    public function setSchedulingMode(SchedulingMode $mode): void
    {
        $this->schedulingMode = $mode;
    }

    /**
     * @param  array<TimeSlotDefinition>  $timeSlots
     */
    public function setTimeSlots(array $timeSlots): void
    {
        $this->timeSlots = $timeSlots;
    }

    public function setLocationMode(LocationMode $mode): void
    {
        $this->locationMode = $mode;
    }

    public function setFixedLocation(?string $location): void
    {
        $this->fixedLocation = $location;
    }

    public function setEligibilityOverride(?EligibilityOverride $override): void
    {
        $this->eligibilityOverride = $override;
    }

    // Early Access Methods

    /**
     * Check if early access is configured and active.
     * Requires both early access to be enabled AND a creation open date to be set.
     */
    public function hasEarlyAccess(): bool
    {
        return $this->earlyAccessEnabled && $this->creationOpensAt !== null;
    }

    public function isEarlyAccessEnabled(): bool
    {
        return $this->earlyAccessEnabled;
    }

    public function creationOpensAt(): ?DateTimeImmutable
    {
        return $this->creationOpensAt;
    }

    public function earlyAccessTier(): ?EarlyAccessTier
    {
        return $this->earlyAccessTier;
    }

    public function setEarlyAccessEnabled(bool $enabled): void
    {
        $this->earlyAccessEnabled = $enabled;
    }

    public function setCreationOpensAt(?DateTimeImmutable $date): void
    {
        $this->creationOpensAt = $date;
    }

    public function setEarlyAccessTier(?EarlyAccessTier $tier): void
    {
        $this->earlyAccessTier = $tier;
    }
}
