<?php

declare(strict_types=1);

namespace Modules\GameTables\Infrastructure\Services;

use DateTimeImmutable;
use InvalidArgumentException;
use Modules\GameTables\Application\DTOs\ParticipantResponseDTO;
use Modules\GameTables\Application\DTOs\ProfileParticipationDTO;
use Modules\GameTables\Application\DTOs\RegisterParticipantDTO;
use Modules\GameTables\Application\Services\EligibilityServiceInterface;
use Modules\GameTables\Application\Services\RegistrationLockInterface;
use Modules\GameTables\Application\Services\RegistrationServiceInterface;
use Modules\GameTables\Domain\Entities\GameTable;
use Modules\GameTables\Domain\Entities\Participant;
use Modules\GameTables\Domain\Enums\ParticipantRole;
use Modules\GameTables\Domain\Enums\ParticipantStatus;
use Modules\GameTables\Domain\Events\GuestRegistered;
use Modules\GameTables\Domain\Events\ParticipantCancelled;
use Modules\GameTables\Domain\Events\ParticipantConfirmed;
use Modules\GameTables\Domain\Events\ParticipantPromotedFromWaitingList;
use Modules\GameTables\Domain\Events\ParticipantRegistered;
use Modules\GameTables\Domain\Events\ParticipantRejected;
use Modules\GameTables\Domain\Exceptions\AlreadyRegisteredException;
use Modules\GameTables\Domain\Exceptions\CannotCancelException;
use Modules\GameTables\Domain\Exceptions\ParticipantNotFoundException;
use Modules\GameTables\Domain\Repositories\GameTableRepositoryInterface;
use Modules\GameTables\Domain\Repositories\ParticipantRepositoryInterface;
use Modules\GameTables\Domain\ValueObjects\GameTableId;
use Modules\GameTables\Domain\ValueObjects\ParticipantId;

/**
 * Registrations, cancellations and promotions run under the lock of their table
 * (RegistrationLockInterface); their events are dispatched once the lock is released.
 */
final readonly class RegistrationService implements RegistrationServiceInterface
{
    public function __construct(
        private ParticipantRepositoryInterface $participantRepository,
        private GameTableRepositoryInterface $gameTableRepository,
        private EligibilityServiceInterface $eligibilityService,
        private RegistrationLockInterface $lock,
    ) {}

    public function register(RegisterParticipantDTO $dto): ParticipantResponseDTO
    {
        $userId = $this->requireUserId($dto);

        $participant = $this->lock->forTable($dto->gameTableId, function () use ($dto, $userId): Participant {
            // The same rules the table page applies, checked under the lock
            $this->eligibilityService->ensureUserCanRegister($dto->gameTableId, $userId, $dto->role);

            return $this->placeUser($dto, $userId);
        });

        $this->announceUserRegistration($participant);

        return ParticipantResponseDTO::fromEntity($participant);
    }

    /**
     * Registered-user flows need a user; guests go through registerGuest().
     */
    private function requireUserId(RegisterParticipantDTO $dto): string
    {
        return $dto->userId ?? throw new InvalidArgumentException('A user ID is required to register a user; use registerGuest() for guests.');
    }

    /**
     * One row per user and table: a cancelled registration is reactivated, an active one refused.
     */
    private function placeUser(RegisterParticipantDTO $dto, string $userId): Participant
    {
        $gameTableId = new GameTableId($dto->gameTableId);

        $existing = $this->participantRepository->findByTableAndUser($gameTableId, $userId);
        if ($existing !== null && $existing->isActive()) {
            throw AlreadyRegisteredException::forTable($userId, $dto->gameTableId);
        }

        $gameTable = $this->gameTableRepository->findOrFail($gameTableId);

        if ($existing !== null) {
            $participant = $existing;
            $participant->reactivate();
            $participant->changeRole($dto->role);
            $participant->updateNotes($dto->notes);
        } else {
            $participant = new Participant(
                id: ParticipantId::generate(),
                gameTableId: $gameTableId,
                userId: $userId,
                role: $dto->role,
                status: ParticipantStatus::Pending,
                notes: $dto->notes,
                createdAt: new DateTimeImmutable,
            );
        }

        $this->seat($participant, $gameTable);
        $this->participantRepository->save($participant);

        return $participant;
    }

    /**
     * One row per guest email and table: a cancelled registration is reactivated with a new
     * token, an active one refused.
     */
    private function placeGuest(RegisterParticipantDTO $dto): Participant
    {
        $gameTableId = new GameTableId($dto->gameTableId);

        $existing = $dto->email !== null
            ? $this->participantRepository->findByTableAndEmail($gameTableId, $dto->email)
            : null;

        if ($existing !== null && $existing->isActive()) {
            throw AlreadyRegisteredException::forGuestEmail((string) $dto->email, $dto->gameTableId);
        }

        $gameTable = $this->gameTableRepository->findOrFail($gameTableId);
        $cancellationToken = bin2hex(random_bytes(32));

        if ($existing !== null) {
            $participant = $existing;
            $participant->reactivate();
            $participant->changeRole($dto->role);
            $participant->updateNotes($dto->notes);
            $participant->firstName = $dto->firstName;
            $participant->lastName = $dto->lastName;
            $participant->phone = $dto->phone;
            $participant->setCancellationToken($cancellationToken);
        } else {
            $participant = new Participant(
                id: ParticipantId::generate(),
                gameTableId: $gameTableId,
                userId: null,
                role: $dto->role,
                status: ParticipantStatus::Pending,
                notes: $dto->notes,
                createdAt: new DateTimeImmutable,
                firstName: $dto->firstName,
                lastName: $dto->lastName,
                email: $dto->email,
                phone: $dto->phone,
                cancellationToken: $cancellationToken,
            );
        }

        $this->seat($participant, $gameTable);
        $this->participantRepository->save($participant);

        return $participant;
    }

    /**
     * A free seat (confirmed when the table confirms automatically, pending otherwise) or the
     * next position of the waiting list.
     */
    private function seat(Participant $participant, GameTable $gameTable): void
    {
        $shouldWaitList = false;
        if ($participant->role === ParticipantRole::Player) {
            $confirmedPlayers = $this->participantRepository->countConfirmedPlayers($gameTable->id);
            $shouldWaitList = ! $gameTable->hasCapacity($confirmedPlayers);
        } elseif ($participant->role === ParticipantRole::Spectator) {
            $confirmedSpectators = $this->participantRepository->countConfirmedSpectators($gameTable->id);
            $shouldWaitList = $gameTable->availableSpectatorSlots($confirmedSpectators) === 0;
        }

        if ($shouldWaitList) {
            $position = $this->participantRepository->getNextWaitingListPosition($gameTable->id);
            $participant->addToWaitingList($position);
        } elseif ($gameTable->autoConfirm) {
            $participant->confirm();
        }
    }

    private function announceUserRegistration(Participant $participant): void
    {
        ParticipantRegistered::dispatch(
            $participant->id->value,
            $participant->gameTableId->value,
            $participant->userId,
            $participant->role->value,
            $participant->status,
            $participant->waitingListPosition,
        );

        if ($participant->isConfirmed()) {
            ParticipantConfirmed::dispatch(
                $participant->id->value,
                $participant->gameTableId->value,
                $participant->userId,
                true, // automatic: confirmed on registration
            );
        }
    }

    private function announceGuestRegistration(Participant $participant): void
    {
        GuestRegistered::dispatch(
            $participant->id->value,
            $participant->gameTableId->value,
            $participant->email ?? '',
            $participant->firstName ?? '',
            $participant->role->value,
            $participant->cancellationToken ?? '',
            $participant->status,
            $participant->waitingListPosition,
        );

        if ($participant->isConfirmed()) {
            ParticipantConfirmed::dispatch(
                $participant->id->value,
                $participant->gameTableId->value,
                null,
                true, // automatic: confirmed on registration
            );
        }
    }

    public function cancel(string $participantId): ParticipantResponseDTO
    {
        [$participant, $wasConfirmed, $promoted] = $this->lock->forParticipant(
            $participantId,
            // Read under the lock: a registration cancelled twice at once frees one seat
            fn (): array => $this->cancelLocked($this->participantRepository->findOrFail(new ParticipantId($participantId))),
        );

        $this->announceCancellation($participant, $participant->userId, $wasConfirmed, $promoted);

        return ParticipantResponseDTO::fromEntity($participant);
    }

    /**
     * @return array{Participant, bool, Participant|null} the cancelled participant, whether it was confirmed, the promoted one
     */
    private function cancelLocked(Participant $participant): array
    {
        if (! $participant->canBeCancelled()) {
            throw CannotCancelException::notAllowed($participant->id->value);
        }

        $wasConfirmed = $participant->isConfirmed();

        $participant->cancel();
        $this->participantRepository->save($participant);

        // The only automatic promotion: one waiting player per freed player seat
        $promoted = $wasConfirmed && $participant->isPlayer()
            ? $this->promoteNext($participant->gameTableId)
            : null;

        return [$participant, $wasConfirmed, $promoted];
    }

    private function announceCancellation(Participant $participant, ?string $userId, bool $wasConfirmed, ?Participant $promoted): void
    {
        ParticipantCancelled::dispatch(
            $participant->id->value,
            $participant->gameTableId->value,
            $userId,
            $wasConfirmed,
        );

        if ($promoted !== null) {
            $this->announcePromotion($promoted);
        }
    }

    /**
     * Moves the first waiting player into a freed seat, while the waiting list is open and a
     * player seat is free. Runs under the table lock.
     */
    private function promoteNext(GameTableId $gameTableId): ?Participant
    {
        // A freed seat is a player seat: spectators in the list are never moved into it
        $participant = $this->participantRepository->getFirstInWaitingList($gameTableId, ParticipantRole::Player);

        if ($participant === null) {
            return null;
        }

        $gameTable = $this->gameTableRepository->find($gameTableId);

        // Closed with the registrations: the director promotes by hand from the panel
        if ($gameTable === null || ! $gameTable->isWaitingListOpen(new DateTimeImmutable())) {
            return null;
        }

        if (! $gameTable->hasCapacity($this->participantRepository->countConfirmedPlayers($gameTableId))) {
            return null;
        }

        // A table with manual confirmation keeps the director's say: the promoted player waits as pending
        $participant->promoteFromWaitingList($gameTable->autoConfirm);
        $this->participantRepository->save($participant);

        return $participant;
    }

    private function announcePromotion(Participant $participant): void
    {
        ParticipantPromotedFromWaitingList::dispatch(
            $participant->id->value,
            $participant->gameTableId->value,
            $participant->userId,
            $participant->isConfirmed(),
        );
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

        ParticipantRejected::dispatch(
            $participant->id->value,
            $participant->gameTableId->value,
            $participant->userId,
        );

        return ParticipantResponseDTO::fromEntity($participant);
    }

    public function promoteFromWaitingList(string $gameTableId): ?ParticipantResponseDTO
    {
        $participant = $this->lock->forTable($gameTableId, fn (): ?Participant => $this->promoteNext(new GameTableId($gameTableId)));

        if ($participant === null) {
            return null;
        }

        $this->announcePromotion($participant);

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
        $participant = $this->lock->forTable($dto->gameTableId, function () use ($dto): Participant {
            if ($dto->email !== null) {
                // The same rules the table page applies, checked under the lock
                $this->eligibilityService->ensureGuestCanRegister($dto->gameTableId, $dto->email, $dto->role);
            }

            return $this->placeGuest($dto);
        });

        $this->announceGuestRegistration($participant);

        return ParticipantResponseDTO::fromEntity($participant);
    }

    public function cancelByToken(string $token): ParticipantResponseDTO
    {
        [$participant, $wasConfirmed, $promoted] = $this->lock->forCancellationToken($token, function () use ($token): array {
            // Read under the lock: a link followed twice at once frees one seat
            $participant = $this->participantRepository->findByCancellationToken($token);

            if ($participant === null) {
                throw ParticipantNotFoundException::byToken($token);
            }

            return $this->cancelLocked($participant);
        });

        $this->announceCancellation($participant, null, $wasConfirmed, $promoted);

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

    /**
     * @return array<ProfileParticipationDTO>
     */
    public function getByUserForProfile(string $userId): array
    {
        $data = $this->participantRepository->getActiveByUserWithTableData($userId);

        $now = new \DateTimeImmutable;

        return array_map(
            fn (array $item): ProfileParticipationDTO => new ProfileParticipationDTO(
                id: $item['participant']->id->value,
                gameTableId: $item['game_table_id'],
                gameTableTitle: $item['game_table_title'],
                gameTableSlug: $item['game_table_slug'],
                gameTableStartsAt: $item['game_table_starts_at'],
                gameSystemName: $item['game_system_name'],
                role: $item['participant']->role->label(),
                roleKey: $item['participant']->role->value,
                roleColor: $item['participant']->role->color(),
                status: $item['participant']->status->label(),
                statusKey: $item['participant']->status->value,
                statusColor: $item['participant']->status->color(),
                waitingListPosition: $item['participant']->waitingListPosition,
                isUpcoming: $item['game_table_starts_at'] !== null && $item['game_table_starts_at'] > $now,
            ),
            $data,
        );
    }

    /**
     * The director's choice from the panel: confirmed, whatever the dates and the setting.
     */
    public function promoteParticipant(string $participantId): ParticipantResponseDTO
    {
        $participant = $this->participantRepository->findOrFail(new ParticipantId($participantId));

        $participant->promoteFromWaitingList();
        $this->participantRepository->save($participant);

        $this->announcePromotion($participant);

        return ParticipantResponseDTO::fromEntity($participant);
    }

    public function registerByAdmin(RegisterParticipantDTO $dto): ParticipantResponseDTO
    {
        $userId = $this->requireUserId($dto);

        // Admins skip the eligibility rules: a full table puts the participant in the waiting list
        $participant = $this->lock->forTable($dto->gameTableId, fn (): Participant => $this->placeUser($dto, $userId));

        $this->announceUserRegistration($participant);

        return ParticipantResponseDTO::fromEntity($participant);
    }

    public function registerGuestByAdmin(RegisterParticipantDTO $dto): ParticipantResponseDTO
    {
        $participant = $this->lock->forTable($dto->gameTableId, fn (): Participant => $this->placeGuest($dto));

        $this->announceGuestRegistration($participant);

        return ParticipantResponseDTO::fromEntity($participant);
    }
}
