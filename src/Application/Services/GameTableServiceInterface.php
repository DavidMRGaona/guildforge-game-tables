<?php

declare(strict_types=1);

namespace Modules\GameTables\Application\Services;

use Modules\GameTables\Application\DTOs\CreateGameTableDTO;
use Modules\GameTables\Application\DTOs\GameTableListDTO;
use Modules\GameTables\Application\DTOs\GameTableResponseDTO;
use Modules\GameTables\Application\DTOs\UpdateGameTableDTO;
use Modules\GameTables\Domain\Enums\TableStatus;

interface GameTableServiceInterface
{
    public function create(CreateGameTableDTO $dto): GameTableResponseDTO;

    public function update(UpdateGameTableDTO $dto): GameTableResponseDTO;

    public function delete(string $id): void;

    public function find(string $id): ?GameTableResponseDTO;

    public function findOrFail(string $id): GameTableResponseDTO;

    public function publish(string $id): GameTableResponseDTO;

    public function unpublish(string $id): GameTableResponseDTO;

    public function openRegistration(string $id): GameTableResponseDTO;

    public function start(string $id): GameTableResponseDTO;

    public function complete(string $id): GameTableResponseDTO;

    public function cancel(string $id): GameTableResponseDTO;

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
