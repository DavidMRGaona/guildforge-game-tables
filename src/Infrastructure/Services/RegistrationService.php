<?php

declare(strict_types=1);

namespace Modules\GameTables\Infrastructure\Services;

use DateTimeImmutable;
use Modules\GameTables\Application\DTOs\ParticipantResponseDTO;
use Modules\GameTables\Application\DTOs\RegisterParticipantDTO;
use Modules\GameTables\Application\Services\EligibilityServiceInterface;
use Modules\GameTables\Application\Services\RegistrationServiceInterface;
use Modules\GameTables\Domain\Entities\Participant;
use Modules\GameTables\Domain\Enums\ParticipantRole;
use Modules\GameTables\Domain\Enums\ParticipantStatus;
use Modules\GameTables\Domain\Events\GuestRegistered;
use Modules\GameTables\Domain\Events\ParticipantCancelled;
use Modules\GameTables\Domain\Events\ParticipantConfirmed;
use Modules\GameTables\Domain\Events\ParticipantPromotedFromWaitingList;
use Modules\GameTables\Domain\Events\ParticipantRegistered;
use Modules\GameTables\Domain\Exceptions\AlreadyRegisteredException;
use Modules\GameTables\Domain\Exceptions\CannotCancelException;
use Modules\GameTables\Domain\Exceptions\ParticipantNotFoundException;
use Modules\GameTables\Domain\Repositories\GameTableRepositoryInterface;
use Modules\GameTables\Domain\Repositories\ParticipantRepositoryInterface;
use Modules\GameTables\Domain\ValueObjects\GameTableId;
use Modules\GameTables\Domain\ValueObjects\ParticipantId;

final readonly class RegistrationService implements RegistrationServiceInterface
{
    public function __construct(
        private ParticipantRepositoryInterface $participantRepository,
        private GameTableRepositoryInterface $gameTableRepository,
        private EligibilityServiceInterface $eligibilityService,
    ) {}

    public function register(RegisterParticipantDTO $dto): ParticipantResponseDTO
    {
        $gameTableId = new GameTableId($dto->gameTableId);

        // Check if already registered
        $existing = $this->participantRepository->findByTableAndUser($gameTableId, $dto->userId);
        if ($existing !== null && $existing->isActive()) {
            throw AlreadyRegisteredException::forTable($dto->userId, $dto->gameTableId);
        }

        $gameTable = $this->gameTableRepository->findOrFail($gameTableId);

        // Determine if should go to waiting list
        $shouldWaitList = false;
        if ($dto->role === ParticipantRole::Player) {
            $confirmedPlayers = $this->participantRepository->countConfirmedPlayers($gameTableId);
            $shouldWaitList = ! $gameTable->hasCapacity($confirmedPlayers);
        } elseif ($dto->role === ParticipantRole::Spectator) {
            $confirmedSpectators = $this->participantRepository->countConfirmedSpectators($gameTableId);
            $shouldWaitList = $gameTable->availableSpectatorSlots($confirmedSpectators) === 0;
        }

        $participant = new Participant(
            id: ParticipantId::generate(),
            gameTableId: $gameTableId,
            userId: $dto->userId,
            role: $dto->role,
            status: ParticipantStatus::Pending,
            notes: $dto->notes,
            createdAt: new DateTimeImmutable(),
        );

        if ($shouldWaitList) {
            $position = $this->participantRepository->getNextWaitingListPosition($gameTableId);
            $participant->addToWaitingList($position);
        } elseif ($gameTable->autoConfirm) {
            $participant->confirm();
        }

        $this->participantRepository->save($participant);

        ParticipantRegistered::dispatch(
            $participant->id->value,
            $participant->gameTableId->value,
            $participant->userId,
            $participant->role->value,
        );

        if ($participant->isConfirmed()) {
            ParticipantConfirmed::dispatch(
                $participant->id->value,
                $participant->gameTableId->value,
                $participant->userId,
            );
        }

        return ParticipantResponseDTO::fromEntity($participant);
    }

    public function cancel(string $participantId): ParticipantResponseDTO
    {
        $participant = $this->participantRepository->findOrFail(new ParticipantId($participantId));

        if (! $participant->canBeCancelled()) {
            throw CannotCancelException::notAllowed($participantId);
        }

        $wasConfirmed = $participant->isConfirmed();
        $gameTableId = $participant->gameTableId;

        $participant->cancel();
        $this->participantRepository->save($participant);

        ParticipantCancelled::dispatch(
            $participant->id->value,
            $participant->gameTableId->value,
            $participant->userId,
            $wasConfirmed,
        );

        // If was confirmed, try to promote from waiting list
        if ($wasConfirmed && $participant->isPlayer()) {
            $this->promoteFromWaitingList($gameTableId->value);
        }

        return ParticipantResponseDTO::fromEntity($participant);
    }

    public function cancelByUser(string $gameTableId, string $userId): ParticipantResponseDTO
    {
        $participant = $this->participantRepository->findByTableAndUser(
            new GameTableId($gameTableId),
            $userId,
        );

        if ($participant === null) {
            throw ParticipantNotFoundException::forUserInTable($userId, $gameTableId);
        }

        return $this->cancel($participant->id->value);
    }

    public function confirm(string $participantId): ParticipantResponseDTO
    {
        $participant = $this->participantRepository->findOrFail(new ParticipantId($participantId));

        $participant->confirm();
        $this->participantRepository->save($participant);

        ParticipantConfirmed::dispatch(
            $participant->id->value,
            $participant->gameTableId->value,
            $participant->userId,
        );

        return ParticipantResponseDTO::fromEntity($participant);
    }

    public function reject(string $participantId): ParticipantResponseDTO
    {
        $participant = $this->participantRepository->findOrFail(new ParticipantId($participantId));

        $participant->reject();
        $this->participantRepository->save($participant);

        return ParticipantResponseDTO::fromEntity($participant);
    }

    public function promoteFromWaitingList(string $gameTableId): ?ParticipantResponseDTO
    {
        $tableId = new GameTableId($gameTableId);
        $participant = $this->participantRepository->getFirstInWaitingList($tableId);

        if ($participant === null) {
            return null;
        }

        $participant->promoteFromWaitingList();
        $this->participantRepository->save($participant);

        ParticipantPromotedFromWaitingList::dispatch(
            $participant->id->value,
            $participant->gameTableId->value,
            $participant->userId,
        );

        return ParticipantResponseDTO::fromEntity($participant);
    }

    public function markAsNoShow(string $participantId): ParticipantResponseDTO
    {
        $participant = $this->participantRepository->findOrFail(new ParticipantId($participantId));

        $participant->markAsNoShow();
        $this->participantRepository->save($participant);

        return ParticipantResponseDTO::fromEntity($participant);
    }

    /**
     * @return array<ParticipantResponseDTO>
     */
    public function getByGameTable(string $gameTableId): array
    {
        $participants = $this->participantRepository->getByGameTable(new GameTableId($gameTableId));

        return array_map(
            fn (Participant $p): ParticipantResponseDTO => ParticipantResponseDTO::fromEntity($p),
            $participants,
        );
    }

    /**
     * @return array<ParticipantResponseDTO>
     */
    public function getWaitingList(string $gameTableId): array
    {
        $participants = $this->participantRepository->getWaitingList(new GameTableId($gameTableId));

        return array_map(
            fn (Participant $p): ParticipantResponseDTO => ParticipantResponseDTO::fromEntity($p),
            $participants,
        );
    }

    /**
     * @return array<ParticipantResponseDTO>
     */
    public function getByUser(string $userId): array
    {
        $participants = $this->participantRepository->getByUser($userId);

        return array_map(
            fn (Participant $p): ParticipantResponseDTO => ParticipantResponseDTO::fromEntity($p),
            $participants,
        );
    }

    public function find(string $participantId): ?ParticipantResponseDTO
    {
        $participant = $this->participantRepository->find(new ParticipantId($participantId));

        if ($participant === null) {
            return null;
        }

        return ParticipantResponseDTO::fromEntity($participant);
    }

    public function findByTableAndUser(string $gameTableId, string $userId): ?ParticipantResponseDTO
    {
        $participant = $this->participantRepository->findByTableAndUser(
            new GameTableId($gameTableId),
            $userId,
        );

        if ($participant === null) {
            return null;
        }

        return ParticipantResponseDTO::fromEntity($participant);
    }

    public function registerGuest(RegisterParticipantDTO $dto): ParticipantResponseDTO
    {
        $gameTableId = new GameTableId($dto->gameTableId);

        // Check if guest with this email is already registered
        if ($dto->email !== null) {
            $existing = $this->participantRepository->findByTableAndEmail($gameTableId, $dto->email);
            if ($existing !== null && $existing->isActive()) {
                throw AlreadyRegisteredException::forGuestEmail($dto->email, $dto->gameTableId);
            }
        }

        $gameTable = $this->gameTableRepository->findOrFail($gameTableId);

        // Generate cancellation token
        $cancellationToken = bin2hex(random_bytes(32));

        // Determine if should go to waiting list
        $shouldWaitList = false;
        if ($dto->role === ParticipantRole::Player) {
            $confirmedPlayers = $this->participantRepository->countConfirmedPlayers($gameTableId);
            $shouldWaitList = ! $gameTable->hasCapacity($confirmedPlayers);
        } elseif ($dto->role === ParticipantRole::Spectator) {
            $confirmedSpectators = $this->participantRepository->countConfirmedSpectators($gameTableId);
            $shouldWaitList = $gameTable->availableSpectatorSlots($confirmedSpectators) === 0;
        }

        $participant = new Participant(
            id: ParticipantId::generate(),
            gameTableId: $gameTableId,
            userId: null,
            role: $dto->role,
            status: ParticipantStatus::Pending,
            notes: $dto->notes,
            createdAt: new DateTimeImmutable(),
            firstName: $dto->firstName,
            lastName: $dto->lastName,
            email: $dto->email,
            phone: $dto->phone,
            cancellationToken: $cancellationToken,
        );

        if ($shouldWaitList) {
            $position = $this->participantRepository->getNextWaitingListPosition($gameTableId);
            $participant->addToWaitingList($position);
        } elseif ($gameTable->autoConfirm) {
            $participant->confirm();
        }

        $this->participantRepository->save($participant);

        GuestRegistered::dispatch(
            $participant->id->value,
            $participant->gameTableId->value,
            $participant->email ?? '',
            $participant->firstName ?? '',
            $participant->role->value,
            $cancellationToken,
        );

        if ($participant->isConfirmed()) {
            ParticipantConfirmed::dispatch(
                $participant->id->value,
                $participant->gameTableId->value,
                null,
            );
        }

        return ParticipantResponseDTO::fromEntity($participant);
    }

    public function cancelByToken(string $token): ParticipantResponseDTO
    {
        $participant = $this->participantRepository->findByCancellationToken($token);

        if ($participant === null) {
            throw ParticipantNotFoundException::byToken($token);
        }

        if (! $participant->canBeCancelled()) {
            throw CannotCancelException::notAllowed($participant->id->value);
        }

        $wasConfirmed = $participant->isConfirmed();
        $gameTableId = $participant->gameTableId;

        $participant->cancel();
        $this->participantRepository->save($participant);

        ParticipantCancelled::dispatch(
            $participant->id->value,
            $participant->gameTableId->value,
            null,
            $wasConfirmed,
        );

        // If was confirmed, try to promote from waiting list
        if ($wasConfirmed && $participant->isPlayer()) {
            $this->promoteFromWaitingList($gameTableId->value);
        }

        return ParticipantResponseDTO::fromEntity($participant);
    }

    public function findByToken(string $token): ?ParticipantResponseDTO
    {
        $participant = $this->participantRepository->findByCancellationToken($token);

        if ($participant === null) {
            return null;
        }

        return ParticipantResponseDTO::fromEntity($participant);
    }
}
