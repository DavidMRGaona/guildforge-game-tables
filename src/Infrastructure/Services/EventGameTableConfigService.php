<?php

declare(strict_types=1);

namespace Modules\GameTables\Infrastructure\Services;

use App\Application\Services\EventQueryServiceInterface;
use Modules\GameTables\Application\DTOs\EventCreationContextDTO;
use Modules\GameTables\Application\DTOs\EventGameTableConfigDTO;
use Modules\GameTables\Application\DTOs\UpdateEventGameTableConfigDTO;
use Modules\GameTables\Application\Services\EventGameTableConfigServiceInterface;
use Modules\GameTables\Domain\Entities\EventGameTableConfig;
use Modules\GameTables\Domain\Repositories\EventGameTableConfigRepositoryInterface;

final readonly class EventGameTableConfigService implements EventGameTableConfigServiceInterface
{
    public function __construct(
        private EventGameTableConfigRepositoryInterface $configRepository,
        private EventQueryServiceInterface $eventQueryService,
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

    public function getCreationContext(string $eventId): EventCreationContextDTO
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
        );
    }

    public function isEnabledForEvent(string $eventId): bool
    {
        $config = $this->configRepository->findByEvent($eventId);

        return $config?->isEnabled() ?? false;
    }
}
