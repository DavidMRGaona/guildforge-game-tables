<?php

declare(strict_types=1);

namespace Modules\GameTables\Application\DTOs;

use DateTimeImmutable;
use Modules\GameTables\Domain\Enums\CreationAccessLevel;
use Modules\GameTables\Domain\Enums\LocationMode;
use Modules\GameTables\Domain\Enums\SchedulingMode;
use Modules\GameTables\Domain\ValueObjects\EarlyAccessTier;
use Modules\GameTables\Domain\ValueObjects\EligibilityOverride;
use Modules\GameTables\Domain\ValueObjects\TimeSlotDefinition;

/**
 * Input DTO for updating event game table configuration.
 */
final readonly class UpdateEventGameTableConfigDTO
{
    /**
     * @param  array<TimeSlotDefinition>  $timeSlots
     */
    public function __construct(
        public string $eventId,
        public bool $tablesEnabled = false,
        public SchedulingMode $schedulingMode = SchedulingMode::FreeSchedule,
        public array $timeSlots = [],
        public LocationMode $locationMode = LocationMode::FreeChoice,
        public ?string $fixedLocation = null,
        public ?EligibilityOverride $eligibilityOverride = null,
        public bool $earlyAccessEnabled = false,
        public ?DateTimeImmutable $creationOpensAt = null,
        public ?EarlyAccessTier $earlyAccessTier = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $schedulingMode = isset($data['scheduling_mode'])
            ? SchedulingMode::from($data['scheduling_mode'])
            : SchedulingMode::FreeSchedule;

        $locationMode = isset($data['location_mode'])
            ? LocationMode::from($data['location_mode'])
            : LocationMode::FreeChoice;

        $timeSlots = self::parseTimeSlots($data['time_slots'] ?? []);
        $eligibilityOverride = self::parseEligibilityOverride($data['eligibility_override'] ?? null);
        $earlyAccessTier = self::parseEarlyAccessTier($data['early_access_tier'] ?? null);
        $creationOpensAt = isset($data['creation_opens_at']) && $data['creation_opens_at'] !== ''
            ? new DateTimeImmutable($data['creation_opens_at'])
            : null;

        return new self(
            eventId: $data['event_id'],
            tablesEnabled: (bool) ($data['tables_enabled'] ?? false),
            schedulingMode: $schedulingMode,
            timeSlots: $timeSlots,
            locationMode: $locationMode,
            fixedLocation: $data['fixed_location'] ?? null,
            eligibilityOverride: $eligibilityOverride,
            earlyAccessEnabled: (bool) ($data['early_access_enabled'] ?? false),
            creationOpensAt: $creationOpensAt,
            earlyAccessTier: $earlyAccessTier,
        );
    }

    /**
     * @param  array<array{label?: string, start_time?: string, end_time?: string, max_tables?: int|null}>  $data
     * @return array<TimeSlotDefinition>
     */
    private static function parseTimeSlots(array $data): array
    {
        if (empty($data)) {
            return [];
        }

        return array_filter(array_map(
            function (array $item): ?TimeSlotDefinition {
                if (empty($item['label']) || empty($item['start_time']) || empty($item['end_time'])) {
                    return null;
                }

                return TimeSlotDefinition::create(
                    label: $item['label'],
                    startTime: new DateTimeImmutable($item['start_time']),
                    endTime: new DateTimeImmutable($item['end_time']),
                    maxTables: isset($item['max_tables']) && $item['max_tables'] !== '' ? (int) $item['max_tables'] : null,
                );
            },
            $data
        ));
    }

    /**
     * @param  array{access_level?: string, allowed_roles?: array<string>|null, required_permission?: string|null}|null  $data
     */
    private static function parseEligibilityOverride(?array $data): ?EligibilityOverride
    {
        if ($data === null || empty($data['access_level'])) {
            return null;
        }

        $accessLevel = CreationAccessLevel::from($data['access_level']);

        return EligibilityOverride::create(
            accessLevel: $accessLevel,
            allowedRoles: $data['allowed_roles'] ?? null,
            requiredPermission: $data['required_permission'] ?? null,
        );
    }

    /**
     * @param  array{access_type?: string, allowed_roles?: array<string>|null, required_permission?: string|null, days_before_opening?: int}|null  $data
     */
    private static function parseEarlyAccessTier(?array $data): ?EarlyAccessTier
    {
        if ($data === null || empty($data['access_type']) || empty($data['days_before_opening'])) {
            return null;
        }

        $accessType = CreationAccessLevel::from($data['access_type']);

        return EarlyAccessTier::create(
            accessType: $accessType,
            allowedRoles: $data['allowed_roles'] ?? null,
            requiredPermission: $data['required_permission'] ?? null,
            daysBeforeOpening: (int) $data['days_before_opening'],
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
            'scheduling_mode' => $this->schedulingMode->value,
            'time_slots' => array_map(
                fn (TimeSlotDefinition $slot): array => $slot->toArray(),
                $this->timeSlots
            ),
            'location_mode' => $this->locationMode->value,
            'fixed_location' => $this->fixedLocation,
            'eligibility_override' => $this->eligibilityOverride?->toArray(),
            'early_access_enabled' => $this->earlyAccessEnabled,
            'creation_opens_at' => $this->creationOpensAt?->format('c'),
            'early_access_tier' => $this->earlyAccessTier?->toArray(),
        ];
    }
}
