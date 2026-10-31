<?php

declare(strict_types=1);

namespace Modules\GameTables\Infrastructure\Services;

use App\Domain\Surveys\Events\SurveySubjectCancelled;
use App\Domain\Surveys\Events\SurveySubjectEnded;
use Illuminate\Support\Facades\DB;
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
use Modules\GameTables\Domain\Exceptions\SessionBelowRosterException;
use Modules\GameTables\Domain\Repositories\CampaignRosterRepositoryInterface;
use Modules\GameTables\Domain\Repositories\GameTableRepositoryInterface;
use Modules\GameTables\Domain\Repositories\ParticipantRepositoryInterface;
use Modules\GameTables\Domain\ValueObjects\CampaignId;
use Modules\GameTables\Domain\ValueObjects\GameSystemId;
use Modules\GameTables\Domain\ValueObjects\GameTableId;
use Modules\GameTables\Domain\ValueObjects\TimeSlot;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameTableModel;
use Modules\GameTables\Infrastructure\Surveys\GameTableSurveySubjectSource;

final readonly class GameTableService implements GameTableServiceInterface
{
    public function __construct(
        private GameTableRepositoryInterface $gameTableRepository,
        private ParticipantRepositoryInterface $participantRepository,
        private ?CampaignRosterRepositoryInterface $roster = null,
    ) {}

    public function create(CreateGameTableDTO $dto): GameTableResponseDTO
    {
        $id = GameTableId::generate();

        $gameTable = new GameTable(
            id: $id,
            gameSystemId: new GameSystemId($dto->gameSystemId),
            createdBy: $dto->createdBy,
            title: $dto->title,
            // Same unique slug as a table created from the panel (title plus the start of the id)
            slug: (new GameTableModel)->generateUniqueSlug($dto->title, $id->value),
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
            acceptsRegistrationsInProgress: $dto->acceptsRegistrationsInProgress,
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

        if (! $gameTable instanceof GameTable) {
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

        $this->guardRoster($gameTable);

        $gameTable->publish();
        $this->gameTableRepository->save($gameTable);

        GameTablePublished::dispatch($gameTable->id->value, $gameTable->title);

        return GameTableResponseDTO::fromEntity($gameTable);
    }

    public function ensureFitsRoster(string $id): void
    {
        $this->guardRoster($this->gameTableRepository->findOrFail(new GameTableId($id)));
    }

    public function unpublish(string $id): GameTableResponseDTO
    {
        $gameTable = $this->gameTableRepository->findOrFail(new GameTableId($id));
        $readStatus = $gameTable->status;

        $gameTable->unpublish();
        // Refused if the automatic update started the table meanwhile
        $this->gameTableRepository->saveIfStatusIs($gameTable, $readStatus);

        return GameTableResponseDTO::fromEntity($gameTable);
    }

    public function start(string $id): GameTableResponseDTO
    {
        $gameTable = $this->gameTableRepository->findOrFail(new GameTableId($id));
        $readStatus = $gameTable->status;

        $gameTable->start();
        $this->gameTableRepository->saveIfStatusIs($gameTable, $readStatus);

        return GameTableResponseDTO::fromEntity($gameTable);
    }

    public function complete(string $id): GameTableResponseDTO
    {
        $gameTable = $this->gameTableRepository->findOrFail(new GameTableId($id));
        $readStatus = $gameTable->status;

        $gameTable->complete();
        $this->gameTableRepository->saveIfStatusIs($gameTable, $readStatus);

        GameTableCompleted::dispatch($gameTable->id->value, $gameTable->title);
        $this->announceEndToSurveys($gameTable);

        return GameTableResponseDTO::fromEntity($gameTable);
    }

    public function cancel(string $id): GameTableResponseDTO
    {
        $gameTable = $this->gameTableRepository->findOrFail(new GameTableId($id));
        $readStatus = $gameTable->status;

        $gameTable->cancel();
        $this->gameTableRepository->saveIfStatusIs($gameTable, $readStatus);

        GameTableCancelled::dispatch($gameTable->id->value, $gameTable->title, $gameTable->timeSlot->startsAt);
        $this->announceCancellationToSurveys($gameTable);

        return GameTableResponseDTO::fromEntity($gameTable);
    }

    /**
     * Tells a surveys module, if any, that the table ended (core event, after the commit): when
     * the director completes it early, now; when it is completed late (the automatic update may
     * run behind), the end of its slot. Campaign sessions are another kind of subject.
     */
    private function announceEndToSurveys(GameTable $gameTable): void
    {
        if ($gameTable->campaignId instanceof CampaignId) {
            return;
        }

        $now = now()->toDateTimeImmutable();
        $slotEnd = $gameTable->timeSlot->endsAt();
        $endedAt = $slotEnd < $now ? $slotEnd : $now;

        DB::afterCommit(static fn () => event(new SurveySubjectEnded(GameTableSurveySubjectSource::TYPE, $gameTable->id->value, $endedAt)));
    }

    private function announceCancellationToSurveys(GameTable $gameTable): void
    {
        if ($gameTable->campaignId instanceof CampaignId) {
            return;
        }

        $cancelledAt = now()->toDateTimeImmutable();

        DB::afterCommit(static fn () => event(new SurveySubjectCancelled(GameTableSurveySubjectSource::TYPE, $gameTable->id->value, $cancelledAt)));
    }

    public function changeStatus(string $id, TableStatus $status): GameTableResponseDTO
    {
        $gameTable = $this->gameTableRepository->findOrFail(new GameTableId($id));
        $readStatus = $gameTable->status;

        $gameTable->changeStatus($status);
        $this->gameTableRepository->saveIfStatusIs($gameTable, $readStatus);

        return GameTableResponseDTO::fromEntity($gameTable);
    }

    /**
     * @return array<GameTableListDTO>
     */
    public function getPublished(): array
    {
        $gameTables = $this->gameTableRepository->getPublished();

        return array_map(
            $this->toListDTO(...),
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
            $this->toListDTO(...),
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
            $this->toListDTO(...),
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
            $this->toListDTO(...),
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
            $this->toListDTO(...),
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
            $this->toListDTO(...),
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
            $this->toListDTO(...),
            $gameTables,
        );
    }

    /**
     * @throws SessionBelowRosterException when the session has fewer seats than its campaign roster
     */
    private function guardRoster(GameTable $gameTable): void
    {
        if (! $this->roster instanceof CampaignRosterRepositoryInterface || ! $gameTable->campaignId instanceof CampaignId) {
            return;
        }

        $rosterSize = $this->roster->count($gameTable->campaignId);

        if ($gameTable->maxPlayers < $rosterSize) {
            throw SessionBelowRosterException::forTable($gameTable->id->value, $gameTable->maxPlayers, $rosterSize);
        }
    }

    private function toListDTO(GameTable $gameTable): GameTableListDTO
    {
        $confirmedPlayers = $this->participantRepository->countConfirmedPlayers($gameTable->id);

        return GameTableListDTO::fromEntity($gameTable, $confirmedPlayers);
    }
}
