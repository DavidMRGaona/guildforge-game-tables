<?php

declare(strict_types=1);

namespace Modules\GameTables\Domain\Repositories;

use Modules\GameTables\Domain\Entities\GameTable;
use Modules\GameTables\Domain\Enums\TableStatus;
use Modules\GameTables\Domain\ValueObjects\CampaignId;
use Modules\GameTables\Domain\ValueObjects\GameSystemId;
use Modules\GameTables\Domain\ValueObjects\GameTableId;

interface GameTableRepositoryInterface
{
    public function save(GameTable $gameTable): void;

    public function find(GameTableId $id): ?GameTable;

    public function findOrFail(GameTableId $id): GameTable;

    public function delete(GameTableId $id): void;

    /**
     * @return array<GameTable>
     */
    public function getPublished(): array;

    /**
     * @return array<GameTable>
     */
    public function getByStatus(TableStatus $status): array;

    /**
     * @return array<GameTable>
     */
    public function getByGameSystem(GameSystemId $gameSystemId): array;

    /**
     * @return array<GameTable>
     */
    public function getByCampaign(CampaignId $campaignId): array;

    /**
     * @return array<GameTable>
     */
    public function getByEvent(string $eventId): array;

    /**
     * @return array<GameTable>
     */
    public function getByCreator(string $userId): array;

    /**
     * @return array<GameTable>
     */
    public function getUpcoming(int $limit = 10): array;

    /**
     * @return array<GameTable>
     */
    public function all(): array;

    /**
     * Find a published game table model with all relations for detail view.
     * Returns the model (not entity) to support eager loading for read scenarios.
     */
    public function findPublishedModelWithRelations(string $id): ?object;

    /**
     * Find a published game table model by slug with all relations for detail view.
     * Returns the model (not entity) to support eager loading for read scenarios.
     */
    public function findPublishedModelBySlugWithRelations(string $slug): ?object;

    /**
     * Get published game table models within a date range for calendar view.
     * Returns models (not entities) to support eager loading for read scenarios.
     *
     * @return \Illuminate\Support\Collection<int, object>
     */
    public function getPublishedModelsInDateRange(
        \DateTimeInterface $from,
        \DateTimeInterface $to,
    ): \Illuminate\Support\Collection;
}
