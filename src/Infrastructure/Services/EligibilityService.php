<?php

declare(strict_types=1);

namespace Modules\GameTables\Infrastructure\Services;

use DateMalformedStringException;
use DateTimeImmutable;
use Modules\GameTables\Application\Services\EligibilityServiceInterface;
use Modules\GameTables\Application\Services\MembershipCheckerInterface;
use Modules\GameTables\Domain\Entities\GameTable;
use Modules\GameTables\Domain\Enums\ParticipantRole;
use Modules\GameTables\Domain\Exceptions\AlreadyRegisteredException;
use Modules\GameTables\Domain\Exceptions\MembersOnlyException;
use Modules\GameTables\Domain\Exceptions\RegistrationClosedException;
use Modules\GameTables\Domain\Exceptions\RegistrationNotAllowedException;
use Modules\GameTables\Domain\Exceptions\TableFullException;
use Modules\GameTables\Domain\Repositories\GameTableRepositoryInterface;
use Modules\GameTables\Domain\Repositories\ParticipantRepositoryInterface;
use Modules\GameTables\Domain\ValueObjects\GameTableId;

final readonly class EligibilityService implements EligibilityServiceInterface
{
    private SeatCounter $seats;

    public function __construct(
        private ParticipantRepositoryInterface $participantRepository,
        private GameTableRepositoryInterface $gameTableRepository,
        private ?MembershipCheckerInterface $membershipChecker = null,
        private GameTableSettingsReader $settings = new GameTableSettingsReader(),
    ) {
        $this->seats = new SeatCounter($participantRepository, $settings);
    }

    public function checkEligibility(
        GameTable $gameTable,
        string $userId,
        bool $isMember,
        ?int $userAge,
        ?DateTimeImmutable $now = null,
        ParticipantRole $role = ParticipantRole::Player,
    ): void {
        $this->evaluate($gameTable, $userId, $isMember, $now, $role);
    }

    /**
     * Every rule of a user registration; refusals are exceptions.
     *
     * @return bool whether the registration goes to the waiting list
     *
     * @throws RegistrationClosedException
     * @throws MembersOnlyException
     * @throws AlreadyRegisteredException
     * @throws TableFullException
     */
    private function evaluate(
        GameTable $gameTable,
        string $userId,
        bool $isMember,
        ?DateTimeImmutable $now,
        ParticipantRole $role,
    ): bool {
        $now ??= new DateTimeImmutable();

        // Check if registration is open
        if (! $gameTable->canRegister()) {
            throw RegistrationClosedException::forTable($gameTable->id->value);
        }

        // Invite tables are filled by the organisation from the panel: nobody signs up by themselves,
        // whatever the dates, the membership or the seats
        if ($gameTable->requiresInvitation()) {
            throw RegistrationClosedException::invitationOnly($gameTable->id->value);
        }

        // The time of the table closes registrations whatever its dates and the early access
        $this->ensureTableTimeAdmits($gameTable, $now);

        // Check registration window
        // Members' early access is part of the membership integration setting
        if (! $gameTable->canUserRegister($isMember, $now, $this->settings->isMembershipIntegrationEnabled())) {
            if ($gameTable->requiresMembership() && ! $isMember) {
                throw MembersOnlyException::forTable($gameTable->id->value);
            }

            if (! $gameTable->isPublicRegistrationOpen($now)) {
                if ($gameTable->registrationOpensAt !== null && $now < $gameTable->registrationOpensAt) {
                    throw RegistrationClosedException::notYetOpen($gameTable->id->value);
                }
                throw RegistrationClosedException::alreadyClosed($gameTable->id->value);
            }
        }

        // Check if already registered
        $existing = $this->participantRepository->findByTableAndUser($gameTable->id, $userId);
        if ($existing !== null && $existing->isActive()) {
            throw AlreadyRegisteredException::forTable($userId, $gameTable->id->value);
        }

        return $this->checkSeats($gameTable, $role, $now);
    }

    /**
     * Spectators need a free spectator seat, whatever the player seats; they have no waiting list.
     * Players need a free player seat or, when the setting is on and the waiting list is still
     * open (not once the table has started, also on tables that admit registrations in play), go
     * to the waiting list.
     * Taken seats are those of SeatCounter, the count the table page shows.
     *
     * @return bool whether a player registration goes to the waiting list
     *
     * @throws TableFullException
     */
    private function checkSeats(GameTable $gameTable, ParticipantRole $role, DateTimeImmutable $now): bool
    {
        if ($role === ParticipantRole::Spectator) {
            $this->checkSpectatorSeats($gameTable);

            return false;
        }

        if ($gameTable->hasCapacity($this->seats->takenPlayerSeats($gameTable))) {
            return false;
        }

        if (! $this->settings->isWaitingListEnabled() || ! $gameTable->isWaitingListOpen($now)) {
            throw TableFullException::forTable($gameTable->id->value);
        }

        return true;
    }

    /**
     * A table closes its registrations at its start, or at its end when it admits registrations
     * in play, whatever its configured dates.
     *
     * @throws RegistrationClosedException
     */
    private function ensureTableTimeAdmits(GameTable $gameTable, DateTimeImmutable $now): void
    {
        if (! $gameTable->isPastRegistrationDeadline($now)) {
            return;
        }

        throw $gameTable->acceptsRegistrationsInProgress
            ? RegistrationClosedException::tableEnded($gameTable->id->value)
            : RegistrationClosedException::tableStarted($gameTable->id->value);
    }

    /**
     * @throws TableFullException
     */
    private function checkSpectatorSeats(GameTable $gameTable): void
    {
        if ($gameTable->availableSpectatorSlots($this->seats->takenSpectatorSeats($gameTable)) === 0) {
            throw TableFullException::forSpectators($gameTable->id->value);
        }
    }

    /**
     * @return array{eligible: bool, reason: string|null, waitingList: bool}
     */
    public function canRegister(
        GameTable $gameTable,
        string $userId,
        bool $isMember,
        ?int $userAge,
        ?DateTimeImmutable $now = null,
        ParticipantRole $role = ParticipantRole::Player,
    ): array {
        try {
            $waitingList = $this->evaluate($gameTable, $userId, $isMember, $now, $role);

            return ['eligible' => true, 'reason' => null, 'waitingList' => $waitingList];
        } catch (RegistrationClosedException|MembersOnlyException|AlreadyRegisteredException|TableFullException $e) {
            return ['eligible' => false, 'reason' => $e->reason, 'waitingList' => false];
        }
    }

    public function hasCapacity(string $gameTableId): bool
    {
        $tableId = new GameTableId($gameTableId);
        $confirmedPlayers = $this->participantRepository->countConfirmedPlayers($tableId);

        // We need to get the game table to check max players
        // For now, we'll return true as this is a helper method
        // The actual check happens in checkEligibility
        return true;
    }

    public function hasSpectatorCapacity(string $gameTableId): bool
    {
        $tableId = new GameTableId($gameTableId);
        $confirmedSpectators = $this->participantRepository->countConfirmedSpectators($tableId);

        // Similar to hasCapacity, actual check happens elsewhere
        return true;
    }

    /**
     * @return array{eligible: bool, reason: string|null, message: string|null, canRegisterAt: string|null, waitingList: bool, spectator: array{eligible: bool, reason: string|null, message: string|null}}
     * @throws DateMalformedStringException
     */
    public function canRegisterById(string $gameTableId, string $userId): array
    {
        $tableId = new GameTableId($gameTableId);
        $gameTable = $this->gameTableRepository->find($tableId);

        if ($gameTable === null) {
            return [
                'eligible' => false,
                'reason' => 'not_found',
                'message' => __('game-tables::messages.errors.table_not_found'),
                'canRegisterAt'  => null,
                'waitingList' => false,
                'spectator' => $this->refusal('not_found'),
            ];
        }

        $isMember = $this->checkMembership($userId);
        $userAge = null; // Age checking would require user profile data

        $now = new DateTimeImmutable();
        // The same calculation as ensureUserCanRegister(), once per role the page offers
        $result = $this->canRegister($gameTable, $userId, $isMember, $userAge, $now);
        $spectator = $this->canRegister($gameTable, $userId, $isMember, $userAge, $now, ParticipantRole::Spectator);

        // Determine when user can register if early access is in effect
        $canRegisterAt = null;
        // No opening date to announce when waiting can never reopen the table: nobody signs up
        // by themselves (invitation only), or it has started or ended
        $neverReopens = in_array($result['reason'], ['invitation_only', 'table_started', 'table_ended'], true);
        if (! $result['eligible'] && ! $neverReopens && $gameTable->registrationOpensAt !== null) {
            if ($isMember && $gameTable->membersEarlyAccessDays > 0 && $this->settings->isMembershipIntegrationEnabled()) {
                $earlyAccessDate = $gameTable->registrationOpensAt->modify("-{$gameTable->membersEarlyAccessDays} days");
                if ($now < $earlyAccessDate) {
                    $canRegisterAt = $earlyAccessDate->format('c');
                }
            } elseif ($now < $gameTable->registrationOpensAt) {
                $canRegisterAt = $gameTable->registrationOpensAt->format('c');
            }
        }

        return [
            'eligible' => $result['eligible'],
            'reason' => $result['reason'],
            'message' => $result['reason'] ? $this->translateReason($result['reason']) : null,
            'canRegisterAt' => $canRegisterAt,
            'waitingList' => $result['waitingList'],
            'spectator' => [
                'eligible' => $spectator['eligible'],
                'reason' => $spectator['reason'],
                'message' => $spectator['reason'] !== null ? $this->translateReason($spectator['reason']) : null,
            ],
        ];
    }

    /**
     * @return array{eligible: bool, reason: string|null, message: string|null, waitingList: bool, spectator: array{eligible: bool, reason: string|null, message: string|null}}
     */
    public function guestEligibility(string $gameTableId): array
    {
        // The email is not known yet: the server checks it when the form is sent
        $player = $this->canGuestRegister($gameTableId, null);
        $spectator = $this->canGuestRegister($gameTableId, null, ParticipantRole::Spectator);

        return [
            ...$player,
            'spectator' => [
                'eligible' => $spectator['eligible'],
                'reason' => $spectator['reason'],
                'message' => $spectator['message'],
            ],
        ];
    }

    /**
     * @return array{eligible: false, reason: string, message: string}
     */
    private function refusal(string $reason): array
    {
        return ['eligible' => false, 'reason' => $reason, 'message' => $this->translateReason($reason)];
    }

    private function checkMembership(string $userId): bool
    {
        return $this->membershipChecker?->isActiveMember($userId) ?? false;
    }

    private function translateReason(string $reason): string
    {
        return match ($reason) {
            'registration_closed' => __('game-tables::messages.errors.registration_closed'),
            'registration_not_open' => __('game-tables::messages.errors.registration_not_open'),
            'members_only' => __('game-tables::messages.errors.members_only'),
            'invitation_only' => __('game-tables::messages.errors.invitation_only'),
            'table_started' => __('game-tables::messages.errors.table_started'),
            'table_ended' => __('game-tables::messages.errors.table_ended'),
            'already_registered' => __('game-tables::messages.errors.already_registered'),
            'guest_already_registered' => __('game-tables::messages.errors.guest_already_registered'),
            'table_full' => __('game-tables::messages.errors.table_full'),
            'spectators_full' => __('game-tables::messages.errors.spectators_full'),
            'not_found' => __('game-tables::messages.errors.table_not_found'),
            'guests_not_allowed' => __('game-tables::messages.errors.guests_not_allowed'),
            default => $reason,
        };
    }

    /**
     * @return array{eligible: bool, reason: string|null, message: string|null, waitingList: bool}
     */
    public function canGuestRegister(string $gameTableId, ?string $email, ParticipantRole $role = ParticipantRole::Player): array
    {
        $tableId = new GameTableId($gameTableId);
        $gameTable = $this->gameTableRepository->find($tableId);

        if ($gameTable === null) {
            return $this->guestRefusal('not_found');
        }

        // Only allow guest registration for "everyone" tables
        if ($gameTable->registrationType->requiresMembership() || $gameTable->registrationType->requiresInvitation()) {
            return $this->guestRefusal('guests_not_allowed');
        }

        // Check if registration is open
        if (! $gameTable->canRegister()) {
            return $this->guestRefusal('registration_closed');
        }

        $now = new DateTimeImmutable();

        // The time of the table closes registrations whatever its dates
        try {
            $this->ensureTableTimeAdmits($gameTable, $now);
        } catch (RegistrationClosedException $e) {
            return $this->guestRefusal($e->reason);
        }

        // Check registration window for public (guests don't get early access)
        if (! $gameTable->isPublicRegistrationOpen($now)) {
            if ($gameTable->registrationOpensAt !== null && $now < $gameTable->registrationOpensAt) {
                return $this->guestRefusal('registration_not_open');
            }

            return $this->guestRefusal('registration_closed');
        }

        // Check if a guest with this email is already registered (or waiting)
        if ($email !== null) {
            $existing = $this->participantRepository->findByTableAndEmail($tableId, $email);
            if ($existing !== null && $existing->isActive()) {
                return $this->guestRefusal('guest_already_registered');
            }
        }

        try {
            $waitingList = $this->checkSeats($gameTable, $role, $now);
        } catch (TableFullException $e) {
            return $this->guestRefusal($e->reason);
        }

        return [
            'eligible' => true,
            'reason' => null,
            'message' => null,
            'waitingList' => $waitingList,
        ];
    }

    /**
     * @return array{eligible: false, reason: string, message: string, waitingList: false}
     */
    private function guestRefusal(string $reason): array
    {
        return [...$this->refusal($reason), 'waitingList' => false];
    }

    public function ensureUserCanRegister(string $gameTableId, string $userId, ParticipantRole $role = ParticipantRole::Player): void
    {
        $gameTable = $this->gameTableRepository->find(new GameTableId($gameTableId));

        if ($gameTable === null) {
            throw RegistrationNotAllowedException::because('not_found', $this->translateReason('not_found'));
        }

        // The same check that decides what the table page offers
        $result = $this->canRegister($gameTable, $userId, $this->checkMembership($userId), null, new DateTimeImmutable(), $role);

        if (! $result['eligible']) {
            $reason = $result['reason'] ?? 'registration_closed';

            throw RegistrationNotAllowedException::because($reason, $this->translateReason($reason));
        }
    }

    public function ensureGuestCanRegister(string $gameTableId, string $email, ParticipantRole $role = ParticipantRole::Player): void
    {
        $result = $this->canGuestRegister($gameTableId, $email, $role);

        if (! $result['eligible']) {
            $reason = $result['reason'] ?? 'registration_closed';

            throw RegistrationNotAllowedException::because($reason, $result['message'] ?? $this->translateReason($reason));
        }
    }
}
