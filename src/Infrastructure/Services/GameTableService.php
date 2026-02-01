<?php

declare(strict_types=1);

namespace Modules\GameTables\Infrastructure\Services;

use Modules\GameTables\Application\DTOs\CreateGameTableDTO;
use Modules\GameTables\Application\DTOs\GameTableListDTO;
use Modules\GameTables\Application\DTOs\GameTableResponseDTO;
use Modules\GameTables\Application\DTOs\UpdateGameTableDTO;
use Modules\GameTables\Application\Services\GameTableServiceInterface;
use Modules\GameTables\Domain\Entities\GameTable;
use Modules\GameTables\Domain\Enums\TableStatus;
use Modules\GameTables\Domain\Events\GameTableCancelled;
use Modules\GameTables\Domain\Events\GameTableCompleted;
use Modules\GameTables\Domain\Events\GameTableCreated;
use Modules\GameTables\Domain\Events\GameTablePublished;
use Modules\GameTables\Domain\Events\RegistrationOpened;
use Modules\GameTables\Domain\Repositories\GameTableRepositoryInterface;
use Modules\GameTables\Domain\Repositories\ParticipantRepositoryInterface;
use Modules\GameTables\Domain\ValueObjects\CampaignId;
use Modules\GameTables\Domain\ValueObjects\GameSystemId;
use Modules\GameTables\Domain\ValueObjects\GameTableId;
use Modules\GameTables\Domain\ValueObjects\TimeSlot;

final readonly class GameTableService implements GameTableServiceInterface
{
    public function __construct(
        private GameTableRepositoryInterface $gameTableRepository,
        private ParticipantRepositoryInterface $participantRepository,
    ) {}

    public function create(CreateGameTableDTO $dto): GameTableResponseDTO
    {
        $gameTable = new GameTable(
            id: GameTableId::generate(),
            gameSystemId: new GameSystemId($dto->gameSystemId),
            createdBy: $dto->createdBy,
            title: $dto->title,
            slug: \Illuminate\Support\Str::slug($dto->title),
            timeSlot: new TimeSlot($dto->startsAt, $dto->durationMinutes),
            tableType: $dto->tableType,
            tableFormat: $dto->tableFormat,
            status: TableStatus::Draft,
            minPlayers: $dto->minPlayers,
            maxPlayers: $dto->maxPlayers,
            campaignId: $dto->campaignId !== null ? new CampaignId($dto->campaignId) : null,
            eventId: $dto->eventId,
            maxSpectators: $dto->maxSpectators,
            synopsis: $dto->synopsis,
            location: $dto->location,
            onlineUrl: $dto->onlineUrl,
            minimumAge: $dto->minimumAge,
            language: $dto->language,
            genres: $dto->genres,
            tone: $dto->tone,
            experienceLevel: $dto->experienceLevel,
            characterCreation: $dto->characterCreation,
            safetyTools: $dto->safetyTools,
            contentWarningIds: $dto->contentWarningIds,
            customWarnings: $dto->customWarnings,
            registrationType: $dto->registrationType,
            membersEarlyAccessDays: $dto->membersEarlyAccessDays,
            registrationOpensAt: $dto->registrationOpensAt,
            registrationClosesAt: $dto->registrationClosesAt,
            autoConfirm: $dto->autoConfirm,
            notes: $dto->notes,
        );

        $this->gameTableRepository->save($gameTable);

        GameTableCreated::dispatch(
            $gameTable->id->value,
            $gameTable->createdBy,
            $gameTable->title,
        );

        return GameTableResponseDTO::fromEntity($gameTable);
    }

    public function update(UpdateGameTableDTO $dto): GameTableResponseDTO
    {
        $gameTable = $this->gameTableRepository->findOrFail(new GameTableId($dto->id));

        $gameTable->updateDetails(
            title: $dto->title,
            synopsis: $dto->synopsis,
            timeSlot: new TimeSlot($dto->startsAt, $dto->durationMinutes),
            tableType: $dto->tableType,
            tableFormat: $dto->tableFormat,
            location: $dto->location,
            onlineUrl: $dto->onlineUrl,
            minPlayers: $dto->minPlayers,
            maxPlayers: $dto->maxPlayers,
            maxSpectators: $dto->maxSpectators,
            minimumAge: $dto->minimumAge,
            language: $dto->language,
            genres: $dto->genres,
            tone: $dto->tone,
            experienceLevel: $dto->experienceLevel,
            characterCreation: $dto->characterCreation,
            safetyTools: $dto->safetyTools,
        );

        $gameTable->updateContentWarnings($dto->contentWarningIds, $dto->customWarnings);

        $gameTable->updateRegistrationSettings(
            registrationType: $dto->registrationType,
            membersEarlyAccessDays: $dto->membersEarlyAccessDays,
            registrationOpensAt: $dto->registrationOpensAt,
            registrationClosesAt: $dto->registrationClosesAt,
            autoConfirm: $dto->autoConfirm,
        );

        $gameTable->linkToEvent($dto->eventId);
        $gameTable->linkToCampaign($dto->campaignId !== null ? new CampaignId($dto->campaignId) : null);
        $gameTable->notes = $dto->notes;

        $this->gameTableRepository->save($gameTable);

        return GameTableResponseDTO::fromEntity($gameTable);
    }

    public function delete(string $id): void
    {
        $this->gameTableRepository->delete(new GameTableId($id));
    }

    public function find(string $id): ?GameTableResponseDTO
    {
        $gameTable = $this->gameTableRepository->find(new GameTableId($id));

        if ($gameTable === null) {
            return null;
        }

        return GameTableResponseDTO::fromEntity($gameTable);
    }

    public function findOrFail(string $id): GameTableResponseDTO
    {
        $gameTable = $this->gameTableRepository->findOrFail(new GameTableId($id));

        return GameTableResponseDTO::fromEntity($gameTable);
    }

    public function publish(string $id): GameTableResponseDTO
    {
        $gameTable = $this->gameTableRepository->findOrFail(new GameTableId($id));

        $gameTable->publish();
        $this->gameTableRepository->save($gameTable);

        GameTablePublished::dispatch($gameTable->id->value, $gameTable->title);

        return GameTableResponseDTO::fromEntity($gameTable);
    }

    public function unpublish(string $id): GameTableResponseDTO
    {
        $gameTable = $this->gameTableRepository->findOrFail(new GameTableId($id));

        $gameTable->unpublish();
        $this->gameTableRepository->save($gameTable);

        return GameTableResponseDTO::fromEntity($gameTable);
    }

    public function openRegistration(string $id): GameTableResponseDTO
    {
        $gameTable = $this->gameTableRepository->findOrFail(new GameTableId($id));

        $gameTable->openRegistration();
        $this->gameTableRepository->save($gameTable);

        RegistrationOpened::dispatch($gameTable->id->value, $gameTable->title);

        return GameTableResponseDTO::fromEntity($gameTable);
    }

    public function start(string $id): GameTableResponseDTO
    {
        $gameTable = $this->gameTableRepository->findOrFail(new GameTableId($id));

        $gameTable->start();
        $this->gameTableRepository->save($gameTable);

        return GameTableResponseDTO::fromEntity($gameTable);
    }

    public function complete(string $id): GameTableResponseDTO
    {
        $gameTable = $this->gameTableRepository->findOrFail(new GameTableId($id));

        $gameTable->complete();
        $this->gameTableRepository->save($gameTable);

        GameTableCompleted::dispatch($gameTable->id->value, $gameTable->title);

        return GameTableResponseDTO::fromEntity($gameTable);
    }

    public function cancel(string $id): GameTableResponseDTO
    {
        $gameTable = $this->gameTableRepository->findOrFail(new GameTableId($id));

        $gameTable->cancel();
        $this->gameTableRepository->save($gameTable);

        GameTableCancelled::dispatch($gameTable->id->value, $gameTable->title);

        return GameTableResponseDTO::fromEntity($gameTable);
    }

    public function changeStatus(string $id, TableStatus $status): GameTableResponseDTO
    {
        $gameTable = $this->gameTableRepository->findOrFail(new GameTableId($id));

        $gameTable->changeStatus($status);
        $this->gameTableRepository->save($gameTable);

        return GameTableResponseDTO::fromEntity($gameTable);
    }

    /**
     * @return array<GameTableListDTO>
     */
    public function getPublished(): array
    {
        $gameTables = $this->gameTableRepository->getPublished();

        return array_map(
            fn (GameTable $gt): GameTableListDTO => $this->toListDTO($gt),
            $gameTables,
        );
    }

    /**
     * @return array<GameTableListDTO>
     */
    public function getByStatus(TableStatus $status): array
    {
        $gameTables = $this->gameTableRepository->getByStatus($status);

        return array_map(
            fn (GameTable $gt): GameTableListDTO => $this->toListDTO($gt),
            $gameTables,
        );
    }

    /**
     * @return array<GameTableListDTO>
     */
    public function getByGameSystem(string $gameSystemId): array
    {
        $gameTables = $this->gameTableRepository->getByGameSystem(new GameSystemId($gameSystemId));

        return array_map(
            fn (GameTable $gt): GameTableListDTO => $this->toListDTO($gt),
            $gameTables,
        );
    }

    /**
     * @return array<GameTableListDTO>
     */
    public function getByCampaign(string $campaignId): array
    {
        $gameTables = $this->gameTableRepository->getByCampaign(new CampaignId($campaignId));

        return array_map(
            fn (GameTable $gt): GameTableListDTO => $this->toListDTO($gt),
            $gameTables,
        );
    }

    /**
     * @return array<GameTableListDTO>
     */
    public function getByEvent(string $eventId): array
    {
        $gameTables = $this->gameTableRepository->getByEvent($eventId);

        return array_map(
            fn (GameTable $gt): GameTableListDTO => $this->toListDTO($gt),
            $gameTables,
        );
    }

    /**
     * @return array<GameTableListDTO>
     */
    public function getByCreator(string $userId): array
    {
        $gameTables = $this->gameTableRepository->getByCreator($userId);

        return array_map(
            fn (GameTable $gt): GameTableListDTO => $this->toListDTO($gt),
            $gameTables,
        );
    }

    /**
     * @return array<GameTableListDTO>
     */
    public function getUpcoming(int $limit = 10): array
    {
        $gameTables = $this->gameTableRepository->getUpcoming($limit);

        return array_map(
            fn (GameTable $gt): GameTableListDTO => $this->toListDTO($gt),
            $gameTables,
        );
    }

    private function toListDTO(GameTable $gameTable): GameTableListDTO
    {
        $confirmedPlayers = $this->participantRepository->countConfirmedPlayers($gameTable->id);

        return GameTableListDTO::fromEntity($gameTable, $confirmedPlayers);
    }
}
