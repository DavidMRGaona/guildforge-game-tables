<?php

declare(strict_types=1);

namespace Modules\GameTables\Domain\Repositories;

use DateTimeImmutable;
use Modules\GameTables\Domain\Entities\GameTable;
use Modules\GameTables\Domain\Enums\TableStatus;
use Modules\GameTables\Domain\Exceptions\GameTableNotFoundException;
use Modules\GameTables\Domain\Exceptions\InvalidStatusTransitionException;
use Modules\GameTables\Domain\ValueObjects\CampaignId;
use Modules\GameTables\Domain\ValueObjects\GameSystemId;
use Modules\GameTables\Domain\ValueObjects\GameTableId;

interface GameTableRepositoryInterface
{
    public function save(GameTable $gameTable): void;

    /**
     * Saves $gameTable only while its stored status is still $expected, so a change made since it
     * was read (the automatic update, another user) is never overwritten.
     *
     * @throws InvalidStatusTransitionException from the stored status when it is no longer $expected
     * @throws GameTableNotFoundException when the table was deleted meanwhile
     */
    public function saveIfStatusIs(GameTable $gameTable, TableStatus $expected): void;

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
     * Ids of the tables in one of the given statuses whose start time is at or before $now,
     * earliest first.
     *
     * @param  list<TableStatus>  $statuses
     * @return list<string>
     */
    public function idsStartedInStatuses(array $statuses, DateTimeImmutable $now): array;

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
}
