<?php

declare(strict_types=1);

namespace Modules\GameTables\Application\Services;

use Modules\GameTables\Application\DTOs\CreateGameTableDTO;
use Modules\GameTables\Application\DTOs\GameTableListDTO;
use Modules\GameTables\Application\DTOs\GameTableResponseDTO;
use Modules\GameTables\Application\DTOs\UpdateGameTableDTO;
use Modules\GameTables\Domain\Enums\TableStatus;
use Modules\GameTables\Domain\Exceptions\InvalidStatusTransitionException;
use Modules\GameTables\Domain\Exceptions\SessionBelowRosterException;

interface GameTableServiceInterface
{
    public function create(CreateGameTableDTO $dto): GameTableResponseDTO;

    public function update(UpdateGameTableDTO $dto): GameTableResponseDTO;

    public function delete(string $id): void;

    public function find(string $id): ?GameTableResponseDTO;

    public function findOrFail(string $id): GameTableResponseDTO;

    /**
     * @throws SessionBelowRosterException when a campaign session has fewer seats than its roster
     */
    public function publish(string $id): GameTableResponseDTO;

    /**
     * Refuses a campaign session with fewer seats than its campaign roster, so it is never
     * published (approved or published automatically) without room for the whole roster.
     *
     * @throws SessionBelowRosterException
     */
    public function ensureFitsRoster(string $id): void;

    public function unpublish(string $id): GameTableResponseDTO;

    /**
     * The status changes follow TableStatus::canTransitionTo() and are saved only while the table
     * still has the status they read.
     *
     * @throws InvalidStatusTransitionException when the change is not allowed or the table moved meanwhile
     */
    public function start(string $id): GameTableResponseDTO;

    /**
     * @throws InvalidStatusTransitionException
     */
    public function complete(string $id): GameTableResponseDTO;

    /**
     * @throws InvalidStatusTransitionException
     */
    public function cancel(string $id): GameTableResponseDTO;

    /**
     * @throws InvalidStatusTransitionException
     */
    public function changeStatus(string $id, TableStatus $status): GameTableResponseDTO;

    /**
     * @return array<GameTableListDTO>
     */
    public function getPublished(): array;

    /**
     * @return array<GameTableListDTO>
     */
    public function getByStatus(TableStatus $status): array;

    /**
     * @return array<GameTableListDTO>
     */
    public function getByGameSystem(string $gameSystemId): array;

    /**
     * @return array<GameTableListDTO>
     */
    public function getByCampaign(string $campaignId): array;

    /**
     * @return array<GameTableListDTO>
     */
    public function getByEvent(string $eventId): array;

    /**
     * @return array<GameTableListDTO>
     */
    public function getByCreator(string $userId): array;

    /**
     * @return array<GameTableListDTO>
     */
    public function getUpcoming(int $limit = 10): array;
}
