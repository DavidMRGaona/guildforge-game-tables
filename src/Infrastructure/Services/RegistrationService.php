<?php

declare(strict_types=1);

namespace Modules\GameTables\Infrastructure\Services;

use Carbon\CarbonImmutable;
use Closure;
use DateTimeImmutable;
use InvalidArgumentException;
use Modules\GameTables\Application\DTOs\AccountDTO;
use Modules\GameTables\Application\DTOs\GuestConfirmationResultDTO;
use Modules\GameTables\Application\DTOs\ParticipantResponseDTO;
use Modules\GameTables\Application\DTOs\ProfileParticipationDTO;
use Modules\GameTables\Application\DTOs\RegisterParticipantDTO;
use Modules\GameTables\Application\Services\AccountLookupInterface;
use Modules\GameTables\Application\Services\EligibilityServiceInterface;
use Modules\GameTables\Application\Services\ParticipantActorContextInterface;
use Modules\GameTables\Application\Services\RegistrationLockInterface;
use Modules\GameTables\Application\Services\RegistrationServiceInterface;
use Modules\GameTables\Domain\Entities\GameTable;
use Modules\GameTables\Domain\Entities\Participant;
use Modules\GameTables\Domain\Enums\GuestAccountsMode;
use Modules\GameTables\Domain\Enums\GuestConfirmationMode;
use Modules\GameTables\Domain\Enums\GuestConfirmationOutcome;
use Modules\GameTables\Domain\Enums\ParticipantCancellationReason;
use Modules\GameTables\Domain\Enums\ParticipantRole;
use Modules\GameTables\Domain\Enums\ParticipantStatus;
use Modules\GameTables\Domain\Events\GuestConfirmationRequested;
use Modules\GameTables\Domain\Events\GuestRegistered;
use Modules\GameTables\Domain\Events\GuestRegistrationExpired;
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
use Modules\GameTables\Domain\Exceptions\ParticipantNotPendingException;
use Modules\GameTables\Domain\Exceptions\TableFullException;
use Modules\GameTables\Domain\Repositories\CampaignRosterRepositoryInterface;
use Modules\GameTables\Domain\Repositories\GameTableRepositoryInterface;
use Modules\GameTables\Domain\Repositories\ParticipantRepositoryInterface;
use Modules\GameTables\Domain\ValueObjects\GameTableId;
use Modules\GameTables\Domain\ValueObjects\ParticipantId;
use Throwable;

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
        // Who the status history names: the one the observer reads (bound scoped), never a copy
        private ParticipantActorContextInterface $actor,
        private GameTableSettingsReader $settings = new GameTableSettingsReader(),
        // Without it, guest emails are not checked against accounts
        private ?AccountLookupInterface $accounts = null,
        // Without it, nobody is enrolled from a roster and register() never takes the roster path
        private ?CampaignRosterRepositoryInterface $roster = null,
    ) {
        $this->seats = new SeatCounter($participantRepository, $settings);
        $this->confirmationPolicy = new GuestConfirmationPolicy($participantRepository, $settings);
    }

    /**
     * Under the table lock. An active guest registration made with the account's email is taken
     * over (takeOverGuest()) instead of adding a second row; otherwise the usual registration.
     */
    public function register(RegisterParticipantDTO $dto): ParticipantResponseDTO
    {
        $userId = $this->requireUserId($dto);
        $now = CarbonImmutable::now()->toDateTimeImmutable();

        [$participant, $rank, $announce, $promoted, $linked] = $this->lock->forTable($dto->gameTableId, function () use ($dto, $userId, $now): array {
            [$guest, $account, $promoted] = $this->guestOfAccount($dto->gameTableId, $userId, $now);

            if ($guest !== null && $account !== null) {
                [$participant, $wasAwaiting, $promoted] = $this->takeOverGuest($guest, $account, $now);

                return [...$this->withRank($participant), $wasAwaiting, $promoted, true];
            }

            // A roster member taking their seat back skips the table rules
            $rejoin = $this->rosterRejoinOf($dto, $userId);

            if ($rejoin !== null) {
                return [...$this->withRank($this->rejoinLocked($rejoin[0], $rejoin[1], $dto, $userId, $now)), true, $promoted, false];
            }

            // The same rules the table page applies, checked under the lock
            $this->eligibilityService->ensureUserCanRegister($dto->gameTableId, $userId, $dto->role);

            return [...$this->withRank($this->placeUser($dto, $userId, $now, recordFingerprint: true)), true, $promoted, false];
        });

        if ($promoted !== null) {
            $this->announcePromotion($promoted);
        }

        // A guest row the organisation already heard of (email confirmed) is not announced again
        if ($announce) {
            $this->announceUserRegistration($participant, $rank);
        }

        return ParticipantResponseDTO::fromEntity($participant, waitingListRank: $rank, linkedFromGuest: $linked);
    }

    /**
     * The session and the user's own row when the request takes the roster path: a roster member
     * asking for a player seat, with no row yet, an active one (refused as a duplicate) or a
     * cancelled one (an absence, leaving the roster or their own cancellation). A rejected, no-show
     * or expired row is the organisation's say: the usual rules apply, as to anyone else. Null
     * otherwise. Runs under the table lock.
     *
     * @return array{GameTable, Participant|null}|null
     */
    private function rosterRejoinOf(RegisterParticipantDTO $dto, string $userId): ?array
    {
        if ($this->roster === null || $dto->role !== ParticipantRole::Player) {
            return null;
        }

        $gameTable = $this->gameTableRepository->find(new GameTableId($dto->gameTableId));

        if ($gameTable === null || ! $this->isRosterMember($gameTable, $userId)) {
            return null;
        }

        $existing = $this->participantRepository->findByTableAndUser($gameTable->id, $userId);

        if ($existing !== null && ! $existing->isActive() && ! $existing->isCancelled()) {
            return null;
        }

        return [$gameTable, $existing];
    }

    /**
     * Whether the user is in the roster of the session's campaign (read as it stands now).
     */
    private function isRosterMember(GameTable $gameTable, string $userId): bool
    {
        return $this->roster !== null
            && $gameTable->campaignId !== null
            && $this->roster->contains($gameTable->campaignId, $userId);
    }

    /**
     * A roster member asks for their seat again (after an absence, or with no row yet): only while
     * the session admits roster enrolment and a seat is free, never onto the waiting list. Runs
     * under the table lock.
     *
     * @throws RegistrationNotAllowedException registration_closed
     * @throws AlreadyRegisteredException
     * @throws TableFullException
     */
    private function rejoinLocked(GameTable $gameTable, ?Participant $existing, RegisterParticipantDTO $dto, string $userId, DateTimeImmutable $now): Participant
    {
        $this->ensureAdmitsRoster($gameTable, $now);

        if ($existing !== null && $existing->isActive()) {
            throw AlreadyRegisteredException::forTable($userId, $gameTable->id->value);
        }

        $participant = $this->seatRosterMember($gameTable, $existing, $userId, $now);
        $participant->updateNotes($dto->notes);
        $participant->recordFingerprint($dto->fingerprint);
        $this->participantRepository->save($participant);

        return $participant;
    }

    /**
     * @throws RegistrationNotAllowedException registration_closed
     */
    private function ensureAdmitsRoster(GameTable $gameTable, DateTimeImmutable $now): void
    {
        if (! $gameTable->admitsRosterEnrollment($now)) {
            throw RegistrationNotAllowedException::because('registration_closed', __('game-tables::messages.errors.registration_closed'));
        }
    }

    /**
     * A free player seat, confirmed whatever the table's confirmation, for the member's inactive
     * row (reactivated, same id) or a new one. Not saved: the caller saves it.
     *
     * @throws TableFullException with nothing changed
     */
    private function seatRosterMember(GameTable $gameTable, ?Participant $existing, string $userId, DateTimeImmutable $now): Participant
    {
        if (! $gameTable->hasCapacity($this->seats->takenPlayerSeats($gameTable))) {
            throw TableFullException::forTable($gameTable->id->value);
        }

        $participant = $this->rosterRow($gameTable, $existing, $userId, $now);
        $participant->confirm();

        return $participant;
    }

    /**
     * The member's inactive row reactivated (same id) or a new pending one, as a roster player row.
     */
    private function rosterRow(GameTable $gameTable, ?Participant $existing, string $userId, DateTimeImmutable $now): Participant
    {
        $participant = $existing ?? new Participant(
            id: ParticipantId::generate(),
            gameTableId: $gameTable->id,
            userId: $userId,
            role: ParticipantRole::Player,
            status: ParticipantStatus::Pending,
            createdAt: $now,
            fromRoster: true,
        );

        if ($existing !== null) {
            $participant->reactivate();
        }

        $participant->markFromRoster();
        $participant->changeRole(ParticipantRole::Player);

        return $participant;
    }

    public function enrollRoster(string $gameTableId, ?string $onlyUserId = null): array
    {
        if ($this->roster === null) {
            return [];
        }

        $now = CarbonImmutable::now()->toDateTimeImmutable();

        [$placed, $promotions] = $this->lock->forTable($gameTableId, function () use ($gameTableId, $onlyUserId, $now): array {
            $gameTable = $this->gameTableRepository->find(new GameTableId($gameTableId));

            if ($gameTable?->campaignId === null || ! $gameTable->admitsRosterEnrollment($now)) {
                return [[], []];
            }

            $placed = [];
            $promotions = [];

            foreach ($this->rosterMembers($gameTable, $onlyUserId) as $userId) {
                [$participant, $promoted] = $this->enrollMemberLocked($gameTable, $userId, $now);

                if ($participant !== null) {
                    $placed[] = $this->withRank($participant);
                }

                if ($promoted !== null) {
                    $promotions[] = $promoted;
                }
            }

            return [$placed, $promotions];
        });

        // Someone else moved into a seat meanwhile (a lapsed guest row, a guest row taken over) is
        // told as usual; the members placed are announced by the caller
        foreach ($promotions as $promoted) {
            $this->announcePromotion($promoted);
        }

        return array_map(
            fn (array $row): ParticipantResponseDTO => ParticipantResponseDTO::fromEntity($row[0], waitingListRank: $row[1]),
            $placed,
        );
    }

    /**
     * Read under the table lock: the whole roster, or only $onlyUserId while a member.
     *
     * @return list<string>
     */
    private function rosterMembers(GameTable $gameTable, ?string $onlyUserId): array
    {
        if ($onlyUserId !== null) {
            return $this->isRosterMember($gameTable, $onlyUserId) ? [$onlyUserId] : [];
        }

        return $this->roster !== null && $gameTable->campaignId !== null
            ? $this->roster->memberIds($gameTable->campaignId)
            : [];
    }

    /**
     * One member in one session, idempotent (there is no unique key on table and user): an active
     * loose player row is adopted as it is; a rejected, no-show, expired or absent row stands, and
     * nothing else is done; otherwise an active guest row made with the member's verified account
     * email is adopted (taken over as their own registration would take it), a row cancelled on
     * leaving the roster is reactivated, no row gets a new one, and a plain cancellation of their
     * own stays. Runs under the table lock.
     *
     * @return array{Participant|null, Participant|null} the row placed now (null when nothing was
     *                                                    placed) and anyone else promoted meanwhile
     */
    private function enrollMemberLocked(GameTable $gameTable, string $userId, DateTimeImmutable $now): array
    {
        $existing = $this->participantRepository->findByTableAndUser($gameTable->id, $userId);

        if ($existing !== null && $existing->isActive()) {
            $this->adoptAsRosterRow($existing);

            return [null, null];
        }

        // The organisation's decision (rejected, no-show, expired) or an absence stands: not even a
        // guest row of the member's is taken over, as that would delete it
        if ($existing !== null && (! $existing->isCancelled() || $existing->isSessionAbsence())) {
            return [null, null];
        }

        // Also lapses the member's overdue guest row, whose seat may promote someone
        [$guest, $account, $promoted] = $this->guestOfAccount($gameTable->id->value, $userId, $now);

        if ($guest !== null && $account !== null) {
            return $this->adoptGuestLocked($guest, $account, $now);
        }

        if ($existing !== null && ! $existing->leftRoster()) {
            return [null, $promoted];
        }

        $participant = $this->rosterRow($gameTable, $existing, $userId, $now);
        $this->placeRoster($participant, $gameTable);
        $this->participantRepository->save($participant);

        return [$participant, $promoted];
    }

    /**
     * An active player registration of the member's keeps its status and gets the roster's
     * priority; any other role is left as it is.
     */
    private function adoptAsRosterRow(Participant $participant): void
    {
        if ($participant->isPlayer() && ! $participant->fromRoster) {
            $participant->markFromRoster();
            $this->participantRepository->save($participant);
        }
    }

    /**
     * The member's active guest row becomes theirs instead of a second row. Only a verified account
     * email proves it is theirs; unverified, or a row that held nothing and finds no room, it stays
     * the guest's and the member is not enrolled (registering by themselves takes it over as
     * usual). A row whose email was unconfirmed (never announced) counts as placed now; one that
     * was already confirmed is adopted as it is. Runs under the table lock.
     *
     * @return array{Participant|null, Participant|null} the row placed now and anyone promoted
     */
    private function adoptGuestLocked(Participant $guest, AccountDTO $account, DateTimeImmutable $now): array
    {
        if (! $account->emailVerified) {
            return [null, null];
        }

        try {
            [$participant, $wasAwaiting, $promoted] = $this->attachGuestLocked($guest, $account->userId, $now);
        } catch (TableFullException) {
            return [null, null];
        }

        $this->adoptAsRosterRow($participant);

        return [$wasAwaiting && $participant->isPlayer() ? $participant : null, $promoted];
    }

    /**
     * A free player seat, confirmed whatever the table's confirmation; with none free, the waiting
     * list, where roster rows come before outsiders.
     */
    private function placeRoster(Participant $participant, GameTable $gameTable): void
    {
        if ($gameTable->hasCapacity($this->seats->takenPlayerSeats($gameTable))) {
            $participant->confirm();

            return;
        }

        $participant->addToWaitingList($this->participantRepository->getNextWaitingListPosition($gameTable->id));
    }

    public function withdrawRosterMember(string $gameTableId, string $userId): bool
    {
        [$cancelled, $promoted] = $this->lock->forTable($gameTableId, function () use ($gameTableId, $userId): array {
            $participant = $this->participantRepository->findByTableAndUser(new GameTableId($gameTableId), $userId);

            if ($participant === null || ! $participant->fromRoster || ! $participant->canBeCancelled()) {
                return [false, null];
            }

            [, , $promoted] = $this->cancelLocked($participant, ParticipantCancellationReason::LeftRoster);

            return [true, $promoted];
        });

        // Leaving the roster is announced by the caller: no cancellation emails per session
        if ($promoted !== null) {
            $this->announcePromotion($promoted);
        }

        return $cancelled;
    }

    public function restoreAbsence(string $gameTableId, string $userId): ParticipantResponseDTO
    {
        $now = CarbonImmutable::now()->toDateTimeImmutable();

        $participant = $this->lock->forTable($gameTableId, function () use ($gameTableId, $userId, $now): Participant {
            $existing = $this->participantRepository->findByTableAndUser(new GameTableId($gameTableId), $userId);

            if ($existing === null || ! $existing->isSessionAbsence()) {
                throw ParticipantNotFoundException::forUserInTable($userId, $gameTableId);
            }

            $gameTable = $this->gameTableRepository->findOrFail($existing->gameTableId);

            // An absence left behind by someone who has since left the roster is not theirs to restore
            if (! $this->isRosterMember($gameTable, $userId)) {
                throw ParticipantNotFoundException::forUserInTable($userId, $gameTableId);
            }

            $this->ensureAdmitsRoster($gameTable, $now);

            $participant = $this->seatRosterMember($gameTable, $existing, $userId, $now);
            $this->participantRepository->save($participant);

            return $participant;
        });

        // The organisation cleared the absence itself: only the member is told
        $this->announceUserRegistration($participant, null, notifyOrganization: false);

        return $this->toResponse($participant);
    }

    /**
     * The active guest registration at the table made with the user's account email. One past its
     * deadline lapses here instead, even if the expiry command has not run yet (as when the guest
     * sends the form again): the user then registers as anyone else. Runs under the table lock.
     *
     * @return array{Participant|null, AccountDTO|null, Participant|null} the guest row, the account
     *                                                                     and the one promoted into
     *                                                                     the seat a lapsed row freed
     */
    private function guestOfAccount(string $gameTableId, string $userId, DateTimeImmutable $now): array
    {
        $account = $this->accounts?->findById($userId);
        $guest = $account !== null
            ? $this->participantRepository->findByTableAndEmail(new GameTableId($gameTableId), $account->email)
            : null;

        if ($guest === null || ! $guest->isActive()) {
            return [null, $account, null];
        }

        if ($guest->isConfirmationOverdue($now)) {
            return [null, $account, $this->expireLocked($guest, $now)];
        }

        return [$guest, $account, null];
    }

    /**
     * In this order: the rules of a user registration (seats aside), then the account's email,
     * which only proves the guest registration is the user's once verified; then the row becomes
     * the user's (attachGuestLocked()). Refused, the guest row stays as it was. Runs under the
     * table lock.
     *
     * @return array{Participant, bool, Participant|null} as attachGuestLocked()
     *
     * @throws RegistrationNotAllowedException when the user could not register with their account
     * @throws AlreadyRegisteredException when the account's email is not verified
     * @throws TableFullException with nothing changed, when an unplaced row finds no room
     */
    private function takeOverGuest(Participant $guest, AccountDTO $account, DateTimeImmutable $now): array
    {
        $gameTableId = $guest->gameTableId->value;
        $this->ensureUserRulesAdmit($gameTableId, $account->userId, $guest->role);

        if (! $account->emailVerified) {
            throw AlreadyRegisteredException::emailRegisteredAsGuest($gameTableId);
        }

        return $this->attachGuestLocked($guest, $account->userId, $now);
    }

    /**
     * The guest row becomes the user's, whose verified account email proves it is theirs: it keeps
     * its place and role and loses the guest's email and tokens; an unconfirmed email counts as
     * confirmed, and a row that held nothing yet (wait mode) is placed as the link would place it.
     * Runs under the table lock.
     *
     * @return array{Participant, bool, Participant|null} the participant, whether its email was
     *                                                     unconfirmed (the organisation never heard
     *                                                     of it) and anyone promoted into a seat
     *
     * @throws AlreadyRegisteredException when the user has an active registration of their own
     * @throws TableFullException with nothing changed, when an unplaced row finds no room
     */
    private function attachGuestLocked(Participant $guest, string $userId, DateTimeImmutable $now): array
    {
        $gameTableId = $guest->gameTableId->value;
        $wasAwaiting = $guest->isAwaitingEmailConfirmation();
        $promoted = null;

        if ($wasAwaiting) {
            $placed = $this->confirmGuestEmailLocked($guest, $this->gameTableRepository->findOrFail($guest->gameTableId), $now);

            if ($placed === null) {
                throw $this->tableFull($guest);
            }

            [$guest, $promoted] = $placed;
        }

        // One row per user and table: a registration of their own that no longer holds anything
        // gives way to the one taken over
        $own = $this->participantRepository->findByTableAndUser($guest->gameTableId, $userId);

        if ($own !== null) {
            // Never reached in the normal flow: the user's rules refuse an active row as
            // already_registered and the roster enrolment adopts it first. It stays so a change to
            // either can never leave two active rows
            if ($own->isActive()) {
                throw AlreadyRegisteredException::forTable($userId, $gameTableId);
            }

            $this->participantRepository->delete($own->id);
        }

        $guest->attachToAccount($userId, $now);
        $this->participantRepository->save($guest);

        return [$guest, $wasAwaiting, $promoted];
    }

    /**
     * The rules of a user registration with their usual refusal, except the seats (as
     * userRulesRefusal()): the guest row taken over already holds its place or is placed by the
     * caller.
     *
     * @throws RegistrationNotAllowedException
     */
    private function ensureUserRulesAdmit(string $gameTableId, string $userId, ParticipantRole $role): void
    {
        try {
            $this->eligibilityService->ensureUserCanRegister($gameTableId, $userId, $role);
        } catch (RegistrationNotAllowedException $e) {
            if (! in_array($e->reason, ['table_full', 'spectators_full'], true)) {
                throw $e;
            }
        }
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
     *
     * @param  bool  $recordFingerprint  the user's own web registration stores the fingerprint
     *                                   (to pair it with an earlier guest row); the panel's never
     */
    private function placeUser(RegisterParticipantDTO $dto, string $userId, DateTimeImmutable $now, bool $recordFingerprint = false): Participant
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
            // Not the roster path (rosterRow() marks its own rows): a row left by an absence or by
            // leaving the roster is an ordinary registration now
            $participant->unmarkFromRoster();
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
                createdAt: $now,
            );
        }

        if ($recordFingerprint) {
            $participant->recordFingerprint($dto->fingerprint);
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
     * @param  bool  $notifyOrganization  false when the organisation itself restored the registration
     */
    private function announceUserRegistration(Participant $participant, ?int $waitingListRank, bool $notifyOrganization = true): void
    {
        ParticipantRegistered::dispatch(
            $participant->id->value,
            $participant->gameTableId->value,
            $participant->userId,
            $participant->role->value,
            $participant->status,
            $waitingListRank,
            $notifyOrganization,
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
     * @param  bool  $notifyOrganization  false when the organisation itself acted from the panel
     */
    private function announceGuestRegistration(Participant $participant, ?int $waitingListRank, bool $notifyOrganization = true): void
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
            $notifyOrganization,
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
        // No reason, not even an outer scope's: the panel's cancellation never asks for one
        return $this->asCurrentActor(null, fn (): ParticipantResponseDTO => $this->cancelById($participantId, byOrganization: true));
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
    private function cancelLocked(Participant $participant, ?ParticipantCancellationReason $reason = null): array
    {
        if (! $participant->canBeCancelled()) {
            throw CannotCancelException::notAllowed($participant->id->value);
        }

        $wasConfirmed = $participant->isConfirmed();
        $wasPending = $participant->isPending();

        // A roster row cancelled without a stated reason is the member missing this session
        $reason ??= $participant->fromRoster ? ParticipantCancellationReason::SessionAbsence : null;

        $participant->cancel($reason);
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
     * Pins the history's actor for an organisation's action: the one an outer scope named (a
     * director's request), otherwise the signed-in user, with exactly the reason given.
     *
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    private function asCurrentActor(?string $reason, Closure $callback): mixed
    {
        return $this->actor->run($this->actor->actorUserId(), $reason, $callback);
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
        if ($gameTable === null || ! $gameTable->isWaitingListOpen(CarbonImmutable::now()->toDateTimeImmutable())) {
            return null;
        }

        if ($freedByPending && ! $this->seats->pendingHoldSeats($gameTable)) {
            return null;
        }

        if (! $gameTable->hasCapacity($this->seats->takenPlayerSeats($gameTable))) {
            return null;
        }

        // A table with manual confirmation keeps the director's say: the promoted player waits as
        // pending, except a roster member, who is always confirmed. Whoever freed the seat, the
        // history names the system: the promotion is the module's own doing
        $this->actor->system(function () use ($participant, $gameTable): void {
            $participant->promoteFromWaitingList($participant->fromRoster || $gameTable->autoConfirm);
            $this->participantRepository->save($participant);
        });

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
        $now = CarbonImmutable::now()->toDateTimeImmutable();

        [$participant, $vouched] = $this->lock->forParticipant($participantId, function () use ($participantId, $now): array {
            $participant = $this->participantRepository->findOrFail(new ParticipantId($participantId));
            $vouched = $participant->isAwaitingEmailConfirmation();

            if ($vouched) {
                $this->vouchForGuestBeforeApproval($participant, $now);
            }

            $this->ensureSeatToConfirm($participant);

            // A guest just placed on a table that confirms by itself is confirmed already
            if (! $participant->isConfirmed()) {
                $participant->confirm();
            }

            $this->participantRepository->save($participant);

            return [$participant, $vouched];
        });

        if ($vouched) {
            // The guest's registration email, which was waiting for the confirmation, stands for
            // the approval one (automatic); the organisation approved it, so it is not told
            $this->announceGuestRegistration($participant, null, notifyOrganization: false);

            return $this->toResponse($participant);
        }

        ParticipantConfirmed::dispatch(
            $participant->id->value,
            $participant->gameTableId->value,
            $participant->userId,
        );

        return $this->toResponse($participant);
    }

    /**
     * Approving an unconfirmed guest vouches for the email. A guest without a seat yet (wait mode)
     * is placed first as the link places it, but only into a seat: approving never sends it to the
     * waiting list nor goes over the seats. Runs under the table lock.
     *
     * @throws TableFullException with nothing changed, when no seat is free
     */
    private function vouchForGuestBeforeApproval(Participant $participant, DateTimeImmutable $now): void
    {
        if (! $this->waitsWithoutSeat($participant)) {
            $participant->confirmEmail($now);

            return;
        }

        $gameTable = $this->gameTableRepository->findOrFail($participant->gameTableId);

        if ($this->confirmGuestEmailLocked($participant, $gameTable, $now, seatOnly: true) === null) {
            throw $this->tableFull($participant);
        }
    }

    private function tableFull(Participant $participant): TableFullException
    {
        return $participant->isSpectator()
            ? TableFullException::forSpectators($participant->gameTableId->value)
            : TableFullException::forTable($participant->gameTableId->value);
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

    public function reject(string $participantId, ?string $reason = null): ParticipantResponseDTO
    {
        $reason = $reason === null || trim($reason) === '' ? null : trim($reason);

        [$participant, $promoted] = $this->asCurrentActor($reason ?? $this->actor->reason(), fn (): array => $this->lock->forParticipant($participantId, function () use ($participantId): array {
            $participant = $this->participantRepository->findOrFail(new ParticipantId($participantId));
            $wasPendingPlayer = $participant->isPending() && $participant->isPlayer();

            if (! $participant->isPending() && $participant->status !== ParticipantStatus::WaitingList) {
                throw ParticipantNotPendingException::forParticipant($participantId);
            }

            $participant->reject();
            $this->participantRepository->save($participant);

            // Where pending players hold seats, rejecting one frees a seat for the waiting list
            $promoted = $wasPendingPlayer
                ? $this->promoteNext($participant->gameTableId, freedByPending: true)
                : null;

            return [$participant, $promoted];
        }));

        ParticipantRejected::dispatch(
            $participant->id->value,
            $participant->gameTableId->value,
            $participant->userId,
            $reason,
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
     * promotion: the session is already under way. An unconfirmed guest's email counts as
     * confirmed (the organisation vouches for it), without the registration email.
     *
     * @throws InvalidStatusTransitionException before the start or without a confirmed seat
     */
    public function markAsNoShow(string $participantId): ParticipantResponseDTO
    {
        $now = CarbonImmutable::now()->toDateTimeImmutable();

        $participant = $this->lock->forParticipant($participantId, function () use ($participantId, $now): Participant {
            $participant = $this->participantRepository->findOrFail(new ParticipantId($participantId));
            $gameTable = $this->gameTableRepository->findOrFail($participant->gameTableId);

            if (! $gameTable->timeSlot->hasStarted($now)) {
                throw InvalidStatusTransitionException::fromTo($participant->status->value, ParticipantStatus::NoShow->value);
            }

            if ($participant->isAwaitingEmailConfirmation()) {
                $participant->confirmEmail($now);
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
     * at most once every few minutes, and nothing else changes), then the new registration, which
     * alone can be refused for want of a seat. Only the confirmation email is sent: the
     * organisation and the usual registration email wait for the guest to confirm.
     */
    public function registerGuest(RegisterParticipantDTO $dto): ParticipantResponseDTO
    {
        $now = CarbonImmutable::now()->toDateTimeImmutable();

        [$participant, $rank, $resent, $send, $promoted] = $this->lock->forTable($dto->gameTableId, function () use ($dto, $now): array {
            $existing = $this->findGuest($dto);

            // Past its deadline the row has lapsed, even if the expiry command has not run yet: the
            // submission is a new registration, never an email with a deadline already gone
            $promoted = $existing !== null && $existing->isConfirmationOverdue($now)
                ? $this->expireLocked($existing, $now)
                : null;

            $unconfirmed = $existing !== null && $existing->isAwaitingEmailConfirmation() ? $existing : null;
            $role = $unconfirmed->role ?? $dto->role;

            $seatRefusal = $this->ensureGuestAdmitted($dto->gameTableId, $role);
            $this->ensureAccountEmailAdmitted($dto, $role, $now);

            if ($unconfirmed !== null) {
                $send = $unconfirmed->canResendConfirmation($now);

                if ($send) {
                    // The deadline stays: sending the form again never buys more time
                    $unconfirmed->markConfirmationSent($now);
                    $this->participantRepository->save($unconfirmed);
                }

                return [...$this->withRank($unconfirmed), true, $send, $promoted];
            }

            // An unconfirmed guest already holds their place (or waits for one): only a new
            // registration needs a free seat. A confirmed duplicate is refused in placeGuest().
            if ($seatRefusal !== null && ($existing === null || ! $existing->isActive())) {
                throw $seatRefusal;
            }

            return [...$this->withRank($this->placeGuest($dto, $existing, requireConfirmation: true, now: $now)), false, true, $promoted];
        });

        if ($promoted !== null) {
            $this->announcePromotion($promoted);
        }

        if ($send) {
            $this->requestGuestConfirmation($participant);
        }

        return ParticipantResponseDTO::fromEntity($participant, waitingListRank: $rank, confirmationResent: $resent);
    }

    /**
     * The unconfirmed registration lapses (an overdue row, or a link that can no longer confirm)
     * and its seat goes to the waiting list as on a cancellation: a confirmed player frees one, a
     * pending one where pending players hold seats. A guest who held nothing (wait mode) frees
     * nothing. No email here: the caller decides. Runs under the table lock.
     *
     * @return Participant|null the one promoted into the freed seat
     */
    private function expireLocked(Participant $participant, DateTimeImmutable $now): ?Participant
    {
        $wasConfirmed = $participant->isConfirmed();
        $heldSeat = $participant->isPlayer()
            && ($wasConfirmed || $participant->isPending())
            && ! $this->waitsWithoutSeat($participant);

        $participant->expire($now);
        $this->participantRepository->save($participant);

        return $heldSeat ? $this->promoteNext($participant->gameTableId, freedByPending: ! $wasConfirmed) : null;
    }

    public function expireGuestRegistration(string $participantId, bool $notifyGuest = true, ?DateTimeImmutable $now = null): bool
    {
        $now ??= CarbonImmutable::now()->toDateTimeImmutable();

        // A lapse is the clock's doing, whoever happens to trigger the check
        [$participant, $promoted] = $this->actor->system(fn (): array => $this->lock->forParticipant($participantId, function () use ($participantId, $now): array {
            // Read under the lock: confirmed, cancelled or sent again as a new registration meanwhile
            $participant = $this->participantRepository->find(new ParticipantId($participantId));

            if ($participant === null || ! $participant->isAwaitingEmailConfirmation() || ! $this->lapsesNow($participant, $now)) {
                return [null, null];
            }

            return [$participant, $this->expireLocked($participant, $now)];
        }));

        if ($participant === null) {
            return false;
        }

        // The promotion first: an expiry email that fails never costs the promoted player theirs
        if ($promoted !== null) {
            $this->announcePromotion($promoted);
        }

        $this->announceExpiry($participant, $notifyGuest);

        return true;
    }

    /**
     * Past its deadline, or with the registrations of its table closed.
     */
    private function lapsesNow(Participant $participant, DateTimeImmutable $now): bool
    {
        if ($participant->isConfirmationOverdue($now)) {
            return true;
        }

        $gameTable = $this->gameTableRepository->find($participant->gameTableId);

        return $gameTable !== null && $now >= $gameTable->effectiveRegistrationClosesAt();
    }

    /**
     * Whether the (now expired) registration had held its place until a deadline, as hold mode does.
     */
    private function heldPlace(Participant $participant): bool
    {
        return $participant->confirmationMode === GuestConfirmationMode::Hold;
    }

    /**
     * The expiry is already committed: a listener that fails (the guest's email) is reported and
     * never turns the confirmation page or the expiry command into an error.
     */
    private function announceExpiry(Participant $participant, bool $notifyGuest): void
    {
        try {
            GuestRegistrationExpired::dispatch(
                $participant->id->value,
                $participant->gameTableId->value,
                $participant->email ?? '',
                $participant->firstName ?? '',
                $notifyGuest,
            );
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    private function waitsWithoutSeat(Participant $participant): bool
    {
        return $participant->isAwaitingEmailConfirmation() && ! $participant->holdsPlaceWhileUnconfirmed();
    }

    /**
     * The rules the table page applies to guests (also without email). The duplicate rule is left
     * to registerGuest(): an unconfirmed guest sending the form again gets their email again. A
     * seat refusal is returned, not thrown: seats are the last rule, after the account and the
     * duplicate checks.
     *
     * @throws RegistrationNotAllowedException for any other refusal
     */
    private function ensureGuestAdmitted(string $gameTableId, ParticipantRole $role): ?RegistrationNotAllowedException
    {
        try {
            $this->eligibilityService->ensureGuestCanRegister($gameTableId, null, $role);
        } catch (RegistrationNotAllowedException $e) {
            if (! in_array($e->reason, ['table_full', 'spectators_full'], true)) {
                throw $e;
            }

            return $e;
        }

        return null;
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
    private function ensureAccountEmailAdmitted(RegisterParticipantDTO $dto, ParticipantRole $role, DateTimeImmutable $now): void
    {
        $email = $this->normalisedEmail($dto);
        $account = $email !== null ? $this->accounts?->findByEmail($email) : null;

        if ($account === null || ! $account->emailVerified) {
            return;
        }

        $reason = $this->eligibilityService->userRulesRefusal($dto->gameTableId, $account->userId, $role, $now);

        if ($reason === 'already_registered') {
            throw AlreadyRegisteredException::forGuestEmail((string) $email, $dto->gameTableId);
        }

        if ($reason === null && $this->settings->guestAccountsMode() === GuestAccountsMode::RequireLogin) {
            throw AccountEmailRequiresLoginException::forEmail();
        }
    }

    public function confirmGuestEmail(string $token): GuestConfirmationResultDTO
    {
        // Unknown, or cleared on cancelling, expiring or a takeover: nothing to lock
        $found = $this->participantRepository->findByConfirmationToken($token);

        if ($found === null) {
            return new GuestConfirmationResultDTO(GuestConfirmationOutcome::Invalid);
        }

        $now = CarbonImmutable::now()->toDateTimeImmutable();

        [$outcome, $participant, $rank, $promoted, $expired] = $this->lock->forParticipant(
            $found->id->value,
            function () use ($token, $now): array {
                // Read under the lock: a link followed twice at once confirms once
                $participant = $this->participantRepository->findByConfirmationToken($token);

                if ($participant === null || ! $participant->isActive()) {
                    return [GuestConfirmationOutcome::Invalid, $participant, null, null, false];
                }

                if ($participant->emailConfirmedAt !== null) {
                    return [GuestConfirmationOutcome::AlreadyConfirmed, $participant, $this->rankOf($participant), null, false];
                }

                $gameTable = $this->gameTableRepository->findOrFail($participant->gameTableId);

                if ($now >= $gameTable->effectiveRegistrationClosesAt() || $gameTable->isPastRegistrationDeadline($now)) {
                    return [GuestConfirmationOutcome::Closed, $participant, null, $this->expireLocked($participant, $now), true];
                }

                // Past the deadline, even if the expiry command has not run yet
                if ($participant->isConfirmationOverdue($now)) {
                    return [GuestConfirmationOutcome::Invalid, $participant, null, $this->expireLocked($participant, $now), true];
                }

                $placed = $this->confirmGuestEmailLocked($participant, $gameTable, $now);

                if ($placed === null) {
                    return [GuestConfirmationOutcome::FilledUp, $participant, null, $this->expireLocked($participant, $now), true];
                }

                [$participant, $promoted] = $placed;

                return [GuestConfirmationOutcome::Confirmed, $participant, $this->rankOf($participant), $promoted, false];
            },
        );

        // The promotion first: an expiry email that fails never costs the promoted player theirs
        if ($promoted !== null) {
            $this->announcePromotion($promoted);
        }

        // Expired on the spot: announced as the expiry command does, besides what the page says
        if ($expired && $participant !== null) {
            $this->announceExpiry($participant, notifyGuest: $this->heldPlace($participant));
        }

        if ($outcome === GuestConfirmationOutcome::Confirmed) {
            $this->announceGuestRegistration($participant, $rank);
        }

        return new GuestConfirmationResultDTO(
            outcome: $outcome,
            participant: $participant !== null ? ParticipantResponseDTO::fromEntity($participant, waitingListRank: $rank) : null,
            gameTableId: $found->gameTableId->value,
        );
    }

    /**
     * Confirms the email of an unconfirmed guest and places the registration as its mode says.
     * Shared by the emailed link and the panel, which only differ in what they do when there is no
     * room. Runs under the table lock.
     *
     * - hold: it keeps what it held (seat, pending approval or waiting list position); from the
     *   waiting list it takes a free seat when no confirmed player is ahead of it.
     * - wait: it is placed now: a seat (confirmed or pending, as the table confirms), else the end
     *   of the waiting list when it is on and open. Spectators only get a seat.
     *
     * @param  bool  $seatOnly  a wait row is placed only into a seat, never into the waiting list
     *                          (the panel's approval)
     * @return array{Participant, Participant|null}|null the participant and anyone promoted into a
     *                                                   seat other than them; null, with nothing
     *                                                   changed, when a wait row finds no room
     */
    private function confirmGuestEmailLocked(Participant $participant, GameTable $gameTable, DateTimeImmutable $now, bool $seatOnly = false): ?array
    {
        if ($this->waitsWithoutSeat($participant)) {
            // Counted while the row is still unconfirmed: once confirmed, a pending row counts its own seat
            if (! $this->hasRoomForGuest($participant, $gameTable, $now, $seatOnly)) {
                return null;
            }

            $this->seat($participant, $gameTable);
            $participant->confirmEmail($now);
            $this->participantRepository->save($participant);

            return [$participant, null];
        }

        $participant->confirmEmail($now);
        $this->participantRepository->save($participant);

        if (! $participant->isOnWaitingList()) {
            return [$participant, null];
        }

        // The first confirmed player waiting takes a free seat: maybe this one
        $promoted = $this->promoteNext($participant->gameTableId);

        if ($promoted !== null && $promoted->id->equals($participant->id)) {
            return [$promoted, null];
        }

        return [$participant, $promoted];
    }

    /**
     * A seat, or (unless $seatOnly) a place at the end of an open waiting list for a player; the
     * same counts as seat().
     */
    private function hasRoomForGuest(Participant $participant, GameTable $gameTable, DateTimeImmutable $now, bool $seatOnly = false): bool
    {
        if ($participant->isPlayer()) {
            return $gameTable->hasCapacity($this->seats->takenPlayerSeats($gameTable))
                || (! $seatOnly && $this->settings->isWaitingListEnabled() && $gameTable->isWaitingListOpen($now));
        }

        if ($participant->isSpectator()) {
            return $gameTable->availableSpectatorSlots($this->seats->takenSpectatorSeats($gameTable)) > 0;
        }

        return true;
    }

    public function confirmGuestEmailByAdmin(string $participantId): ParticipantResponseDTO
    {
        $now = CarbonImmutable::now()->toDateTimeImmutable();

        [$participant, $rank, $promoted, $confirmed] = $this->lock->forParticipant(
            $participantId,
            function () use ($participantId, $now): array {
                // Read under the lock: confirmed by the link (or cancelled) meanwhile
                $participant = $this->participantRepository->findOrFail(new ParticipantId($participantId));

                if (! $participant->isAwaitingEmailConfirmation()) {
                    return [$participant, $this->rankOf($participant), null, false];
                }

                $gameTable = $this->gameTableRepository->findOrFail($participant->gameTableId);
                $placed = $this->confirmGuestEmailLocked($participant, $gameTable, $now);

                // Unlike the link, the registration is not expired: it stays unconfirmed
                if ($placed === null) {
                    throw $this->tableFull($participant);
                }

                [$participant, $promoted] = $placed;

                return [$participant, $this->rankOf($participant), $promoted, true];
            },
        );

        if ($promoted !== null) {
            $this->announcePromotion($promoted);
        }

        // As the link announces it, but the organisation, which vouched for it, is not told
        if ($confirmed) {
            $this->announceGuestRegistration($participant, $rank, notifyOrganization: false);
        }

        return ParticipantResponseDTO::fromEntity($participant, waitingListRank: $rank);
    }

    public function findByConfirmationToken(string $token): ?ParticipantResponseDTO
    {
        $participant = $this->participantRepository->findByConfirmationToken($token);

        return $participant !== null ? $this->toResponse($participant) : null;
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
     * The director may pick a guest whose email is not confirmed (the automatic promotion skips
     * them): the promotion vouches for the email.
     */
    public function promoteParticipant(string $participantId): ParticipantResponseDTO
    {
        $now = CarbonImmutable::now()->toDateTimeImmutable();

        [$participant, $promoted, $vouched] = $this->lock->forParticipant($participantId, function () use ($participantId, $now): array {
            $participant = $this->participantRepository->findOrFail(new ParticipantId($participantId));

            // Promoted, cancelled or confirmed meanwhile: nothing to do
            if (! $participant->isOnWaitingList()) {
                return [$participant, false, false];
            }

            $vouched = $participant->isAwaitingEmailConfirmation();

            if ($vouched) {
                $participant->confirmEmail($now);
            }

            // With the waiting list on, not even the director goes over the seats
            $this->ensureSeatToConfirm($participant);

            $participant->promoteFromWaitingList();
            $this->participantRepository->save($participant);

            return [$participant, true, $vouched];
        });

        if ($promoted && $vouched) {
            // One email to the guest: the registration email, which carries the cancellation link
            // (the promotion one does not); the directors promoted them, so they are not told
            $this->announceGuestRegistration($participant, null, notifyOrganization: false);
        } elseif ($promoted) {
            $this->announcePromotion($participant);
        }

        return $this->toResponse($participant);
    }

    public function registerByAdmin(RegisterParticipantDTO $dto): ParticipantResponseDTO
    {
        $userId = $this->requireUserId($dto);

        $now = CarbonImmutable::now()->toDateTimeImmutable();

        // Admins skip the eligibility rules: a full table puts the participant in the waiting list.
        // No fingerprint: the connection is the organisation's, not the participant's
        [$participant, $rank] = $this->lock->forTable($dto->gameTableId, fn (): array => $this->withRank($this->placeUser($dto, $userId, $now)));

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
