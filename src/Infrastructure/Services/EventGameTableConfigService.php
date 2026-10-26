<?php

declare(strict_types=1);

namespace Modules\GameTables\Infrastructure\Services;

use App\Application\Services\EventQueryServiceInterface;
use DateTimeImmutable;
use Modules\GameTables\Application\DTOs\EventCreationContextDTO;
use Modules\GameTables\Application\DTOs\EventGameTableConfigDTO;
use Modules\GameTables\Application\DTOs\UpdateEventGameTableConfigDTO;
use Modules\GameTables\Application\Services\EventGameTableConfigServiceInterface;
use Modules\GameTables\Domain\Entities\EventGameTableConfig;
use Modules\GameTables\Domain\Exceptions\TimeSlotUnavailableException;
use Modules\GameTables\Domain\Repositories\EventGameTableConfigRepositoryInterface;
use Modules\GameTables\Domain\Repositories\GameTableRepositoryInterface;
use Modules\GameTables\Domain\ValueObjects\GameTableId;
use Modules\GameTables\Domain\ValueObjects\TimeSlotDefinition;

final readonly class EventGameTableConfigService implements EventGameTableConfigServiceInterface
{
    public function __construct(
        private EventGameTableConfigRepositoryInterface $configRepository,
        private EventQueryServiceInterface $eventQueryService,
        private GameTableRepositoryInterface $gameTableRepository,
    ) {}

    public function getConfigForEvent(string $eventId): EventGameTableConfigDTO
    {
        $config = $this->configRepository->findByEventOrDefault($eventId);

        return EventGameTableConfigDTO::fromEntity($config);
    }

    public function updateConfig(UpdateEventGameTableConfigDTO $dto): void
    {
        $config = new EventGameTableConfig(
            eventId: $dto->eventId,
            tablesEnabled: $dto->tablesEnabled,
            schedulingMode: $dto->schedulingMode,
            timeSlots: $dto->timeSlots,
            locationMode: $dto->locationMode,
            fixedLocation: $dto->fixedLocation,
            eligibilityOverride: $dto->eligibilityOverride,
            earlyAccessEnabled: $dto->earlyAccessEnabled,
            creationOpensAt: $dto->creationOpensAt,
            earlyAccessTier: $dto->earlyAccessTier,
        );

        $this->configRepository->save($config);
    }

    public function getCreationContext(string $eventId, ?string $exceptTableId = null): EventCreationContextDTO
    {
        $config = $this->configRepository->findByEventOrDefault($eventId);

        if (! $config->isEnabled()) {
            return EventCreationContextDTO::disabled($eventId);
        }

        // Get event details for location and dates
        $event = $this->eventQueryService->findById($eventId);
        $eventLocation = $event?->location;
        $eventStartDate = $event?->startDate;
        $eventEndDate = $event?->endDate;

        return EventCreationContextDTO::fromConfig(
            config: $config,
            eventLocation: $eventLocation,
            eventStartDate: $eventStartDate,
            eventEndDate: $eventEndDate,
            tablesLeft: array_map(
                fn (TimeSlotDefinition $slot): ?int => $this->tablesLeft($eventId, $slot, $exceptTableId),
                $config->timeSlots(),
            ),
        );
    }

    public function ensureSlotTakes(string $eventId, DateTimeImmutable $startsAt, ?string $exceptTableId = null): void
    {
        $config = $this->configRepository->findByEvent($eventId);

        // Without slots the form offers a free start, as for an event with free schedule
        if (! $config instanceof EventGameTableConfig || ! $config->isEnabled() || ! $config->isSlotBased() || $config->timeSlots() === []) {
            return;
        }

        $slot = $config->slotStartingAt($startsAt);

        if (! $slot instanceof TimeSlotDefinition) {
            throw TimeSlotUnavailableException::notASlot();
        }

        if ($this->tablesLeft($eventId, $slot, $exceptTableId) === 0) {
            throw TimeSlotUnavailableException::full($slot->label);
        }
    }

    public function isEnabledForEvent(string $eventId): bool
    {
        $config = $this->configRepository->findByEvent($eventId);

        return $config?->isEnabled() ?? false;
    }

    /**
     * Tables $slot still takes (null without a limit), leaving out the table being edited
     */
    private function tablesLeft(string $eventId, TimeSlotDefinition $slot, ?string $exceptTableId): ?int
    {
        if (! $slot->hasCapacityLimit()) {
            return null;
        }

        $taken = $this->gameTableRepository->countTakingTimeSlotPlace(
            $eventId,
            $slot->startTime,
            $exceptTableId !== null ? new GameTableId($exceptTableId) : null,
        );

        return max(0, (int) $slot->maxTables - $taken);
    }
}
