<?php

declare(strict_types=1);

namespace Modules\GameTables\Infrastructure\Persistence\Eloquent\Repositories;

use App\Application\Services\TimezoneServiceInterface;
use Carbon\Carbon;
use DateTimeImmutable;
use Modules\GameTables\Domain\Entities\EventGameTableConfig;
use Modules\GameTables\Domain\Enums\LocationMode;
use Modules\GameTables\Domain\Enums\SchedulingMode;
use Modules\GameTables\Domain\Repositories\EventGameTableConfigRepositoryInterface;
use Modules\GameTables\Domain\ValueObjects\EarlyAccessTier;
use Modules\GameTables\Domain\ValueObjects\EligibilityOverride;
use Modules\GameTables\Domain\ValueObjects\TimeSlotDefinition;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\EventGameTableConfigModel;

final readonly class EloquentEventGameTableConfigRepository implements EventGameTableConfigRepositoryInterface
{
    public function save(EventGameTableConfig $config): void
    {
        EventGameTableConfigModel::query()->updateOrCreate(
            ['event_id' => $config->eventId()],
            [
                'tables_enabled' => $config->isEnabled(),
                'scheduling_mode' => $config->schedulingMode(),
                'time_slots' => $this->timeSlotsToArray($config->timeSlots()),
                'location_mode' => $config->locationMode(),
                'fixed_location' => $config->fixedLocation(),
                'eligibility_override' => $config->eligibilityOverride()?->toArray(),
                'early_access_enabled' => $config->isEarlyAccessEnabled(),
                'creation_opens_at' => $config->creationOpensAt(),
                'early_access_tier' => $config->earlyAccessTier()?->toArray(),
            ]
        );
    }

    public function findByEvent(string $eventId): ?EventGameTableConfig
    {
        $model = EventGameTableConfigModel::query()->find($eventId);

        return $model !== null ? $this->toEntity($model) : null;
    }

    public function findByEventOrDefault(string $eventId): EventGameTableConfig
    {
        $config = $this->findByEvent($eventId);

        if ($config instanceof EventGameTableConfig) {
            return $config;
        }

        // Return default config (disabled by default)
        return new EventGameTableConfig(
            eventId: $eventId,
            tablesEnabled: false,
            schedulingMode: SchedulingMode::FreeSchedule,
            timeSlots: [],
            locationMode: LocationMode::FreeChoice,
            earlyAccessEnabled: false,
        );
    }

    public function delete(string $eventId): void
    {
        EventGameTableConfigModel::query()
            ->where('event_id', $eventId)
            ->delete();
    }

    public function exists(string $eventId): bool
    {
        return EventGameTableConfigModel::query()
            ->where('event_id', $eventId)
            ->exists();
    }

    public function getEnabledEventIds(): array
    {
        return EventGameTableConfigModel::query()
            ->where('tables_enabled', true)
            ->pluck('event_id')
            ->all();
    }

    private function toEntity(EventGameTableConfigModel $model): EventGameTableConfig
    {
        return new EventGameTableConfig(
            eventId: $model->event_id,
            tablesEnabled: $model->tables_enabled,
            schedulingMode: $model->scheduling_mode,
            timeSlots: $this->arrayToTimeSlots($model->time_slots),
            locationMode: $model->location_mode,
            fixedLocation: $model->fixed_location,
            eligibilityOverride: $this->arrayToEligibilityOverride($model->eligibility_override),
            earlyAccessEnabled: $model->early_access_enabled,
            creationOpensAt: $this->carbonToDateTimeImmutable($model->creation_opens_at),
            earlyAccessTier: $this->arrayToEarlyAccessTier($model->early_access_tier),
        );
    }

    /**
     * @param  array<TimeSlotDefinition>  $timeSlots
     * @return array<array{label: string, start_time: string, end_time: string, max_tables: int|null}>
     */
    private function timeSlotsToArray(array $timeSlots): array
    {
        $timezone = app(TimezoneServiceInterface::class);

        // With the site's offset: the JSON reads the same whatever the storage zone
        return array_map(
            fn (TimeSlotDefinition $slot): array => [
                ...$slot->toArray(),
                'start_time' => (string) $timezone->iso($slot->startTime),
                'end_time' => (string) $timezone->iso($slot->endTime),
            ],
            $timeSlots
        );
    }

    /**
     * @param  array<array{label: string, start_time: string, end_time: string, max_tables?: int|null}>|null  $data
     * @return array<TimeSlotDefinition>
     */
    private function arrayToTimeSlots(?array $data): array
    {
        if ($data === null) {
            return [];
        }

        $timezone = app(TimezoneServiceInterface::class);

        // A slot saved before slots carried an offset holds the site's wall time
        $read = fn (string $time): DateTimeImmutable => $timezone->toStorage(new DateTimeImmutable($time, $timezone->displayTimezone()));

        return array_map(
            fn (array $slot): TimeSlotDefinition => TimeSlotDefinition::create(
                label: $slot['label'],
                startTime: $read($slot['start_time']),
                endTime: $read($slot['end_time']),
                maxTables: $slot['max_tables'] ?? null,
            ),
            $data
        );
    }

    /**
     * @param  array{access_level: string, allowed_roles?: array<string>|null, required_permission?: string|null}|null  $data
     */
    private function arrayToEligibilityOverride(?array $data): ?EligibilityOverride
    {
        if ($data === null) {
            return null;
        }

        return EligibilityOverride::fromArray($data);
    }

    /**
     * @param  array{access_type: string, allowed_roles?: array<string>|null, required_permission?: string|null, days_before_opening: int}|null  $data
     */
    private function arrayToEarlyAccessTier(?array $data): ?EarlyAccessTier
    {
        if ($data === null) {
            return null;
        }

        return EarlyAccessTier::fromArray($data);
    }

    private function carbonToDateTimeImmutable(?Carbon $carbon): ?DateTimeImmutable
    {
        if (! $carbon instanceof Carbon) {
            return null;
        }

        return DateTimeImmutable::createFromMutable($carbon->toDateTime());
    }
}
