<?php

declare(strict_types=1);

namespace Modules\GameTables\Infrastructure\Services;

use Carbon\CarbonImmutable;
use DateTimeImmutable;
use InvalidArgumentException;
use Modules\GameTables\Application\DTOs\ParticipantResponseDTO;
use Modules\GameTables\Application\DTOs\ProfileParticipationDTO;
use Modules\GameTables\Application\DTOs\RegisterParticipantDTO;
use Modules\GameTables\Application\Services\AccountLookupInterface;
use Modules\GameTables\Application\Services\EligibilityServiceInterface;
use Modules\GameTables\Application\Services\RegistrationLockInterface;
use Modules\GameTables\Application\Services\RegistrationServiceInterface;
use Modules\GameTables\Domain\Entities\GameTable;
use Modules\GameTables\Domain\Entities\Participant;
use Modules\GameTables\Domain\Enums\GuestAccountsMode;
use Modules\GameTables\Domain\Enums\GuestConfirmationMode;
use Modules\GameTables\Domain\Enums\ParticipantRole;
use Modules\GameTables\Domain\Enums\ParticipantStatus;
use Modules\GameTables\Domain\Events\GuestConfirmationRequested;
use Modules\GameTables\Domain\Events\GuestRegistered;
use Modules\GameTables\Domain\Events\ParticipantCancelled;
use Modules\GameTables\Domain\Events\ParticipantConfirmed;
use Modules\GameTables\Domain\Events\ParticipantPromotedFromWaitingList;
use Modules\GameTables\Domain\Events\ParticipantRegistered;
use Modules\GameTables\Domain\Events\ParticipantRejected;
use Modules\GameTables\Domain\Exceptions\AccountEmailRequiresLoginException;
use Modules\GameTables\Domain\Exceptions\AlreadyRegisteredException;
use Modules\GameTables\Domain\Exceptions\CannotCancelException;
use Modules\GameTables\Domain\Exceptions\InvalidStatusTransitionException;
use Modules\GameTables\Domain\Exceptions\ParticipantNotFoundException;
use Modules\GameTables\Domain\Exceptions\RegistrationNotAllowedException;
use Modules\GameTables\Domain\Exceptions\TableFullException;
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
    private SeatCounter $seats;

    private GuestConfirmationPolicy $confirmationPolicy;

    public function __construct(
        private ParticipantRepositoryInterface $participantRepository,
        private GameTableRepositoryInterface $gameTableRepository,
        private EligibilityServiceInterface $eligibilityService,
        private RegistrationLockInterface $lock,
        private GameTableSettingsReader $settings = new GameTableSettingsReader(),
        // Without it, guest emails are not checked against accounts
        private ?AccountLookupInterface $accounts = null,
    ) {
        $this->seats = new SeatCounter($participantRepository, $settings);
        $this->confirmationPolicy = new GuestConfirmationPolicy($participantRepository, $settings);
    }

    public function register(RegisterParticipantDTO $dto): ParticipantResponseDTO
    {
        $userId = $this->requireUserId($dto);

        [$participant, $rank] = $this->lock->forTable($dto->gameTableId, function () use ($dto, $userId): array {
            // The same rules the table page applies, checked under the lock
            $this->eligibilityService->ensureUserCanRegister($dto->gameTableId, $userId, $dto->role);

            return $this->withRank($this->placeUser($dto, $userId));
        });

        $this->announceUserRegistration($participant, $rank);

        return ParticipantResponseDTO::fromEntity($participant, waitingListRank: $rank);
    }

    /**
     * @return array{Participant, int|null} the participant and its place among those waiting
     */
    private function withRank(Participant $participant): array
    {
        return [$participant, $this->rankOf($participant)];
    }

    /**
     * The place shown to someone on the waiting list: their rank among those waiting for the same
     * role, not the stored position, which keeps the gaps left by those who leave.
     */
    private function rankOf(Participant $participant): ?int
    {
        return $participant->isOnWaitingList()
            ? $this->participantRepository->getWaitingListRank($participant->id)
            : null;
    }

    private function toResponse(Participant $participant): ParticipantResponseDTO
    {
        return ParticipantResponseDTO::fromEntity($participant, waitingListRank: $this->rankOf($participant));
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
     * The guest registration with this email at the table, active first (guest emails are compared
     * without case or surrounding spaces).
     */
    private function findGuest(RegisterParticipantDTO $dto): ?Participant
    {
        $email = $this->normalisedEmail($dto);

        return $email !== null
            ? $this->participantRepository->findByTableAndEmail(new GameTableId($dto->gameTableId), $email)
            : null;
    }

    private function normalisedEmail(RegisterParticipantDTO $dto): ?string
    {
        return $dto->email !== null ? mb_strtolower(trim($dto->email)) : null;
    }

    /**
     * One row per guest email and table: a cancelled (or expired) registration is reactivated with
     * a new token, an active one refused. From the public form the email is left unconfirmed: in
     * hold mode the guest is placed as anyone else and keeps the place until the deadline; in wait
     * mode (also once the table has as many held places as the cap allows) they stay pending,
     * without a seat or a waiting list position, until they confirm. From the panel the email
     * counts as confirmed.
     *
     * @param  Participant|null  $existing  the result of findGuest()
     */
    private function placeGuest(RegisterParticipantDTO $dto, ?Participant $existing, bool $requireConfirmation, DateTimeImmutable $now): Participant
    {
        $gameTableId = new GameTableId($dto->gameTableId);
        $email = $this->normalisedEmail($dto);

        if ($existing !== null && $existing->isActive()) {
            throw AlreadyRegisteredException::forGuestEmail((string) $email, $dto->gameTableId);
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
                createdAt: $now,
                firstName: $dto->firstName,
                lastName: $dto->lastName,
                email: $email,
                phone: $dto->phone,
                cancellationToken: $cancellationToken,
            );
        }

        if (! $requireConfirmation) {
            $this->seat($participant, $gameTable);
            $participant->confirmEmail($now);
            $this->participantRepository->save($participant);

            return $participant;
        }

        // Counted under the table lock, with the insert: two guests at once never pass the cap
        $mode = $this->confirmationPolicy->modeFor($gameTable);
        $deadline = null;

        if ($mode === GuestConfirmationMode::Hold) {
            $deadline = $this->confirmationPolicy->deadlineFor($gameTable, $now);

            // Registrations closing this very moment: the place would lapse as it is taken
            if ($deadline <= $now) {
                throw RegistrationNotAllowedException::because('registration_closed', __('game-tables::messages.errors.registration_closed'));
            }

            $this->seat($participant, $gameTable);
        }

        $participant->requireEmailConfirmation($mode, bin2hex(random_bytes(32)), $deadline, $now);
        $participant->recordFingerprint($dto->fingerprint);
        $this->participantRepository->save($participant);

        return $participant;
    }

    /**
     * A free seat (confirmed when the table confirms automatically, pending otherwise) or the
     * next position of the waiting list. Taken seats are those of SeatCounter.
     */
    private function seat(Participant $participant, GameTable $gameTable): void
    {
        $shouldWaitList = false;
        if ($participant->role === ParticipantRole::Player) {
            $shouldWaitList = ! $gameTable->hasCapacity($this->seats->takenPlayerSeats($gameTable));
        } elseif ($participant->role === ParticipantRole::Spectator) {
            $shouldWaitList = $gameTable->availableSpectatorSlots($this->seats->takenSpectatorSeats($gameTable)) === 0;
        }

        if ($shouldWaitList) {
            $position = $this->participantRepository->getNextWaitingListPosition($gameTable->id);
            $participant->addToWaitingList($position);
        } elseif ($gameTable->autoConfirm) {
            $participant->confirm();
        }
    }

    /**
     * @param  int|null  $waitingListRank  the place among those waiting, for the confirmation email
     */
    private function announceUserRegistration(Participant $participant, ?int $waitingListRank): void
    {
        ParticipantRegistered::dispatch(
            $participant->id->value,
            $participant->gameTableId->value,
            $participant->userId,
            $participant->role->value,
            $participant->status,
            $waitingListRank,
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

    /**
     * @param  int|null  $waitingListRank  the place among those waiting, for the confirmation email
     */
    private function announceGuestRegistration(Participant $participant, ?int $waitingListRank): void
    {
        GuestRegistered::dispatch(
            $participant->id->value,
            $participant->gameTableId->value,
            $participant->email ?? '',
            $participant->firstName ?? '',
            $participant->role->value,
            $participant->cancellationToken ?? '',
            $participant->status,
            $waitingListRank,
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

    private function requestGuestConfirmation(Participant $participant): void
    {
        GuestConfirmationRequested::dispatch(
            $participant->id->value,
            $participant->gameTableId->value,
            $participant->email ?? '',
            $participant->firstName ?? '',
            $participant->confirmationToken ?? '',
            $participant->holdsPlaceWhileUnconfirmed(),
            $participant->holdsPlaceWhileUnconfirmed() ? $participant->confirmationExpiresAt?->format('d/m/Y H:i') : null,
        );
    }

    public function cancel(string $participantId): ParticipantResponseDTO
    {
        $participant = $this->participantRepository->find(new ParticipantId($participantId));

        // An unknown id still fails under the lock, as before
        if ($participant !== null) {
            $this->ensureTableNotStarted($participant);
        }

        return $this->cancelById($participantId, byOrganization: false);
    }

    /**
     * Participants cancel by themselves only before the table starts, also on tables that admit
     * registrations in play. Checked before the lock (the start does not change under it) and
     * never in cancelLocked(), which the panel's cancellations share. A registration that cannot
     * be cancelled anyway is left to cancelLocked() so it reports its status error.
     *
     * @throws CannotCancelException
     */
    private function ensureTableNotStarted(Participant $participant): void
    {
        if (! $participant->canBeCancelled()) {
            return;
        }

        if (! $this->tableAdmitsWithdrawal($participant->gameTableId, new DateTimeImmutable())) {
            throw CannotCancelException::tableStarted($participant->id->value);
        }
    }

    /**
     * Without its table there is no start to compare with: the participant cannot cancel by
     * themselves (the panel still can).
     */
    private function tableAdmitsWithdrawal(GameTableId $gameTableId, DateTimeImmutable $now): bool
    {
        $gameTable = $this->gameTableRepository->find($gameTableId);

        return $gameTable !== null && $gameTable->canParticipantWithdraw($now);
    }

    public function withdrawalRefusal(string $participantId, ?DateTimeImmutable $now = null): ?string
    {
        $participant = $this->participantRepository->find(new ParticipantId($participantId));

        if ($participant === null || ! $participant->canBeCancelled()) {
            return 'not_allowed';
        }

        return $this->tableAdmitsWithdrawal($participant->gameTableId, $now ?? new DateTimeImmutable())
            ? null
            : 'table_started';
    }

    /**
     * The same cancellation as the participant's own (lock, seat freed, promotion); only the
     * emails differ: the participant is told the organisation cancelled it, the directors nothing.
     */
    public function cancelByAdmin(string $participantId): ParticipantResponseDTO
    {
        return $this->cancelById($participantId, byOrganization: true);
    }

    private function cancelById(string $participantId, bool $byOrganization): ParticipantResponseDTO
    {
        [$participant, $wasConfirmed, $promoted] = $this->lock->forParticipant(
            $participantId,
            // Read under the lock: a registration cancelled twice at once frees one seat
            fn (): array => $this->cancelLocked($this->participantRepository->findOrFail(new ParticipantId($participantId))),
        );

        $this->announceCancellation($participant, $participant->userId, $wasConfirmed, $promoted, $byOrganization);

        return $this->toResponse($participant);
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
        $wasPending = $participant->isPending();

        $participant->cancel();
        $this->participantRepository->save($participant);

        // The only automatic promotion: one waiting player per freed player seat (a pending
        // player frees one only where pending players hold seats)
        $promoted = $participant->isPlayer() && ($wasConfirmed || $wasPending)
            ? $this->promoteNext($participant->gameTableId, freedByPending: ! $wasConfirmed)
            : null;

        return [$participant, $wasConfirmed, $promoted];
    }

    private function announceCancellation(Participant $participant, ?string $userId, bool $wasConfirmed, ?Participant $promoted, bool $byOrganization = false): void
    {
        ParticipantCancelled::dispatch(
            $participant->id->value,
            $participant->gameTableId->value,
            $userId,
            $wasConfirmed,
            $byOrganization,
        );

        if ($promoted !== null) {
            $this->announcePromotion($promoted);
        }
    }

    /**
     * Moves the first waiting player into a freed seat, while the waiting list is open and a
     * player seat is free. Runs under the table lock.
     */
    private function promoteNext(GameTableId $gameTableId, bool $freedByPending = false): ?Participant
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

        if ($freedByPending && ! $this->seats->pendingHoldSeats($gameTable)) {
            return null;
        }

        if (! $gameTable->hasCapacity($this->seats->takenPlayerSeats($gameTable))) {
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

        $this->ensureTableNotStarted($participant);

        return $this->cancelById($participant->id->value, byOrganization: false);
    }

    public function confirm(string $participantId): ParticipantResponseDTO
    {
        $participant = $this->lock->forParticipant($participantId, function () use ($participantId): Participant {
            $participant = $this->participantRepository->findOrFail(new ParticipantId($participantId));

            $this->ensureSeatToConfirm($participant);

            $participant->confirm();
            $this->participantRepository->save($participant);

            return $participant;
        });

        ParticipantConfirmed::dispatch(
            $participant->id->value,
            $participant->gameTableId->value,
            $participant->userId,
        );

        return $this->toResponse($participant);
    }

    /**
     * With the waiting list on, a confirmation never takes the table over its player or spectator
     * seats. Someone who already holds a seat (pending where pending registrations hold seats)
     * only needs a free one among the confirmed; anyone else needs a seat free in SeatCounter.
     *
     * @throws TableFullException
     */
    private function ensureSeatToConfirm(Participant $participant): void
    {
        if (! $this->settings->isWaitingListEnabled() || $participant->isConfirmed()) {
            return;
        }

        if (! $participant->isPlayer() && ! $participant->isSpectator()) {
            return;
        }

        $gameTable = $this->gameTableRepository->findOrFail($participant->gameTableId);
        $holdsSeat = $participant->isPending() && $this->seats->pendingHoldSeats($gameTable);

        if ($participant->isPlayer()) {
            $taken = $holdsSeat
                ? $this->participantRepository->countConfirmedPlayers($gameTable->id)
                : $this->seats->takenPlayerSeats($gameTable);

            if (! $gameTable->hasCapacity($taken)) {
                throw TableFullException::forTable($gameTable->id->value);
            }

            return;
        }

        $taken = $holdsSeat
            ? $this->participantRepository->countConfirmedSpectators($gameTable->id)
            : $this->seats->takenSpectatorSeats($gameTable);

        if ($gameTable->availableSpectatorSlots($taken) === 0) {
            throw TableFullException::forSpectators($gameTable->id->value);
        }
    }

    public function reject(string $participantId): ParticipantResponseDTO
    {
        [$participant, $promoted] = $this->lock->forParticipant($participantId, function () use ($participantId): array {
            $participant = $this->participantRepository->findOrFail(new ParticipantId($participantId));
            $wasPendingPlayer = $participant->isPending() && $participant->isPlayer();

            $participant->reject();
            $this->participantRepository->save($participant);

            // Where pending players hold seats, rejecting one frees a seat for the waiting list
            $promoted = $wasPendingPlayer
                ? $this->promoteNext($participant->gameTableId, freedByPending: true)
                : null;

            return [$participant, $promoted];
        });

        ParticipantRejected::dispatch(
            $participant->id->value,
            $participant->gameTableId->value,
            $participant->userId,
        );

        if ($promoted !== null) {
            $this->announcePromotion($promoted);
        }

        return $this->toResponse($participant);
    }

    public function promoteFromWaitingList(string $gameTableId): ?ParticipantResponseDTO
    {
        $participant = $this->lock->forTable($gameTableId, fn (): ?Participant => $this->promoteNext(new GameTableId($gameTableId)));

        if ($participant === null) {
            return null;
        }

        $this->announcePromotion($participant);

        return $this->toResponse($participant);
    }

    /**
     * A confirmed participant who did not come, once the table has started. No emails and no
     * promotion: the session is already under way.
     *
     * @throws InvalidStatusTransitionException before the start or without a confirmed seat
     */
    public function markAsNoShow(string $participantId): ParticipantResponseDTO
    {
        $participant = $this->lock->forParticipant($participantId, function () use ($participantId): Participant {
            $participant = $this->participantRepository->findOrFail(new ParticipantId($participantId));
            $gameTable = $this->gameTableRepository->findOrFail($participant->gameTableId);

            if (! $gameTable->timeSlot->hasStarted(new DateTimeImmutable())) {
                throw InvalidStatusTransitionException::fromTo($participant->status->value, ParticipantStatus::NoShow->value);
            }

            $participant->markAsNoShow();
            $this->participantRepository->save($participant);

            return $participant;
        });

        return $this->toResponse($participant);
    }

    /**
     * @return array<ParticipantResponseDTO>
     */
    public function getByGameTable(string $gameTableId): array
    {
        $participants = $this->participantRepository->getByGameTable(new GameTableId($gameTableId));

        return array_map(
            fn (Participant $p): ParticipantResponseDTO => $this->toResponse($p),
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
            fn (Participant $p): ParticipantResponseDTO => $this->toResponse($p),
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
            fn (Participant $p): ParticipantResponseDTO => $this->toResponse($p),
            $participants,
        );
    }

    public function find(string $participantId): ?ParticipantResponseDTO
    {
        $participant = $this->participantRepository->find(new ParticipantId($participantId));

        if ($participant === null) {
            return null;
        }

        return $this->toResponse($participant);
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

        return $this->toResponse($participant);
    }


    /**
     * Under the table lock, in this order: the usual rules, the account check, the guest already
     * registered with the email (confirmed: a duplicate; unconfirmed: the confirmation email again,
     * at most once every few minutes, and nothing else changes), then the new registration. Only
     * the confirmation email is sent: the organisation and the usual registration email wait for
     * the guest to confirm.
     */
    public function registerGuest(RegisterParticipantDTO $dto): ParticipantResponseDTO
    {
        $now = CarbonImmutable::now()->toDateTimeImmutable();

        [$participant, $rank, $resent, $send] = $this->lock->forTable($dto->gameTableId, function () use ($dto, $now): array {
            $existing = $this->findGuest($dto);
            $unconfirmed = $existing !== null && $existing->isAwaitingEmailConfirmation() ? $existing : null;

            $this->ensureGuestAdmitted($dto, $unconfirmed);
            $this->ensureAccountEmailAdmitted($dto, $now);

            if ($unconfirmed !== null) {
                $send = $unconfirmed->canResendConfirmation($now);

                if ($send) {
                    // The deadline stays: sending the form again never buys more time
                    $unconfirmed->markConfirmationSent($now);
                    $this->participantRepository->save($unconfirmed);
                }

                return [...$this->withRank($unconfirmed), true, $send];
            }

            return [...$this->withRank($this->placeGuest($dto, $existing, requireConfirmation: true, now: $now)), false, true];
        });

        if ($send) {
            $this->requestGuestConfirmation($participant);
        }

        return ParticipantResponseDTO::fromEntity($participant, waitingListRank: $rank, confirmationResent: $resent);
    }

    /**
     * The rules the table page applies to guests (also without email). The duplicate rule is left
     * to registerGuest(): an unconfirmed guest sending the form again gets their email again. Such
     * a guest already holds their place (or waits for one), so a full table does not refuse them.
     *
     * @throws RegistrationNotAllowedException
     */
    private function ensureGuestAdmitted(RegisterParticipantDTO $dto, ?Participant $unconfirmed): void
    {
        try {
            $this->eligibilityService->ensureGuestCanRegister($dto->gameTableId, null, $unconfirmed->role ?? $dto->role);
        } catch (RegistrationNotAllowedException $e) {
            if ($unconfirmed === null || ! in_array($e->reason, ['table_full', 'spectators_full'], true)) {
                throw $e;
            }
        }
    }

    /**
     * A guest email that belongs to an account counts only when the account has verified it (an
     * unverified account may hold someone else's email) and could register as a user at the table
     * (seat refusals aside): already registered, it is a duplicate; otherwise, depending on the
     * setting, the person is asked to sign in or registers as a guest.
     *
     * @throws AlreadyRegisteredException
     * @throws AccountEmailRequiresLoginException
     */
    private function ensureAccountEmailAdmitted(RegisterParticipantDTO $dto, DateTimeImmutable $now): void
    {
        $email = $this->normalisedEmail($dto);
        $account = $email !== null ? $this->accounts?->findByEmail($email) : null;

        if ($account === null || ! $account->emailVerified) {
            return;
        }

        $reason = $this->eligibilityService->userRulesRefusal($dto->gameTableId, $account->userId, $dto->role, $now);

        if ($reason === 'already_registered') {
            throw AlreadyRegisteredException::forGuestEmail((string) $email, $dto->gameTableId);
        }

        if ($reason === null && $this->settings->guestAccountsMode() === GuestAccountsMode::RequireLogin) {
            throw AccountEmailRequiresLoginException::forEmail();
        }
    }

    public function cancelByToken(string $token): ParticipantResponseDTO
    {
        $participant = $this->participantRepository->findByCancellationToken($token)
            ?? throw ParticipantNotFoundException::byToken($token);

        $this->ensureTableNotStarted($participant);

        [$participant, $wasConfirmed, $promoted] = $this->lock->forCancellationToken($token, function () use ($token): array {
            // Read under the lock: a link followed twice at once frees one seat
            $participant = $this->participantRepository->findByCancellationToken($token);

            if ($participant === null) {
                throw ParticipantNotFoundException::byToken($token);
            }

            return $this->cancelLocked($participant);
        });

        $this->announceCancellation($participant, null, $wasConfirmed, $promoted);

        return $this->toResponse($participant);
    }

    public function findByToken(string $token): ?ParticipantResponseDTO
    {
        $participant = $this->participantRepository->findByCancellationToken($token);

        if ($participant === null) {
            return null;
        }

        return $this->toResponse($participant);
    }

    /**
     * @return array<ProfileParticipationDTO>
     */
    public function getByUserForProfile(string $userId): array
    {
        $data = $this->participantRepository->getActiveByUserWithTableData($userId);

        // Every place among those waiting, for all the user's waiting lists, in one query
        $waitingAt = [];
        foreach ($data as $item) {
            if ($item['participant']->isOnWaitingList()) {
                $waitingAt[] = new GameTableId($item['game_table_id']);
            }
        }
        $places = $this->participantRepository->getWaitingListRanks($waitingAt);

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
                // The place among those waiting, not the stored position (which may have gaps)
                waitingListPosition: $item['participant']->isOnWaitingList() ? ($places[$item['participant']->id->value] ?? null) : null,
                isUpcoming: $item['game_table_starts_at'] !== null && $item['game_table_starts_at'] > $now,
            ),
            $data,
        );
    }

    /**
     * The director's choice from the panel: confirmed, whatever the dates. With the waiting list on
     * it never exceeds the player or spectator seats (TableFullException); off, it works as before.
     */
    public function promoteParticipant(string $participantId): ParticipantResponseDTO
    {
        [$participant, $promoted] = $this->lock->forParticipant($participantId, function () use ($participantId): array {
            $participant = $this->participantRepository->findOrFail(new ParticipantId($participantId));

            // Promoted, cancelled or confirmed meanwhile: nothing to do
            if (! $participant->isOnWaitingList()) {
                return [$participant, false];
            }

            // With the waiting list on, not even the director goes over the seats
            $this->ensureSeatToConfirm($participant);

            $participant->promoteFromWaitingList();
            $this->participantRepository->save($participant);

            return [$participant, true];
        });

        if ($promoted) {
            $this->announcePromotion($participant);
        }

        return $this->toResponse($participant);
    }

    public function registerByAdmin(RegisterParticipantDTO $dto): ParticipantResponseDTO
    {
        $userId = $this->requireUserId($dto);

        // Admins skip the eligibility rules: a full table puts the participant in the waiting list
        [$participant, $rank] = $this->lock->forTable($dto->gameTableId, fn (): array => $this->withRank($this->placeUser($dto, $userId)));

        $this->announceUserRegistration($participant, $rank);

        return ParticipantResponseDTO::fromEntity($participant, waitingListRank: $rank);
    }

    public function registerGuestByAdmin(RegisterParticipantDTO $dto): ParticipantResponseDTO
    {
        $now = CarbonImmutable::now()->toDateTimeImmutable();

        // The organisation vouches for the email: no confirmation and no fingerprint
        [$participant, $rank] = $this->lock->forTable(
            $dto->gameTableId,
            fn (): array => $this->withRank($this->placeGuest($dto, $this->findGuest($dto), requireConfirmation: false, now: $now)),
        );

        $this->announceGuestRegistration($participant, $rank);

        return ParticipantResponseDTO::fromEntity($participant, waitingListRank: $rank);
    }
}
