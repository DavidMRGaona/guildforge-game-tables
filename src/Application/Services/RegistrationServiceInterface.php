<?php

declare(strict_types=1);

namespace Modules\GameTables\Application\Services;

use DateTimeImmutable;
use Modules\GameTables\Application\DTOs\GuestConfirmationResultDTO;
use Modules\GameTables\Application\DTOs\ParticipantResponseDTO;
use Modules\GameTables\Application\DTOs\ProfileParticipationDTO;
use Modules\GameTables\Application\DTOs\RegisterParticipantDTO;
use Modules\GameTables\Domain\Exceptions\AccountEmailRequiresLoginException;
use Modules\GameTables\Domain\Exceptions\AlreadyRegisteredException;
use Modules\GameTables\Domain\Exceptions\CannotCancelException;
use Modules\GameTables\Domain\Exceptions\InvalidStatusTransitionException;
use Modules\GameTables\Domain\Exceptions\ParticipantAwaitingEmailException;
use Modules\GameTables\Domain\Exceptions\ParticipantNotFoundException;
use Modules\GameTables\Domain\Exceptions\ParticipantNotPendingException;
use Modules\GameTables\Domain\Exceptions\RegistrationNotAllowedException;
use Modules\GameTables\Domain\Exceptions\TableClosedException;
use Modules\GameTables\Domain\Exceptions\TableFullException;

interface RegistrationServiceInterface
{
    /**
     * A roster member asking for a player seat in a session of their campaign rejoins without the
     * table rules (membership, dates of the public registration): while the session admits roster
     * enrolment and a seat is free, their row is reactivated (or created) confirmed. Anyone else,
     * or a spectator request, follows the table rules.
     *
     * @throws RegistrationNotAllowedException when the table does not admit the user (for a roster member: registration_closed)
     * @throws AlreadyRegisteredException when the user already has an active registration
     * @throws TableFullException when a roster member finds no free seat
     */
    public function register(RegisterParticipantDTO $dto): ParticipantResponseDTO;

    /**
     * Enrols the campaign roster (or only $onlyUserId, when a member) in a session, under the table
     * lock, while the session admits roster enrolment: a member without a row, or whose row was
     * cancelled on leaving the roster, gets a player seat confirmed (whatever the table's
     * confirmation), or a place on the waiting list ahead of outsiders when the session is full.
     * An active loose player registration is adopted as it is; any other row (an absence, a plain
     * cancellation, a rejection, an active spectator…) is left untouched. Nothing is announced:
     * the caller does it.
     *
     * @return list<ParticipantResponseDTO> the rows placed now (adopted ones are not)
     */
    public function enrollRoster(string $gameTableId, ?string $onlyUserId = null): array;

    /**
     * The member left the roster: their active roster row in the session is cancelled (reason
     * left_roster) and the freed seat promotes the first waiting player, who is told. No
     * cancellation is announced.
     *
     * @return bool whether a row was cancelled
     */
    public function withdrawRosterMember(string $gameTableId, string $userId): bool;

    /**
     * Clears a member's absence from a session: the same placement as a member rejoining, but the
     * organisation, which did it, is not told; the member gets the usual confirmation.
     *
     * @throws ParticipantNotFoundException when the user has no absence in the session
     * @throws RegistrationNotAllowedException when the session no longer admits roster enrolment (registration_closed)
     * @throws TableFullException when no seat is free
     */
    public function restoreAbsence(string $gameTableId, string $userId): ParticipantResponseDTO;

    /**
     * The participant's own cancellation: refused once the table has started.
     *
     * @throws CannotCancelException when the table has started or the registration is no longer active
     */
    public function cancel(string $participantId): ParticipantResponseDTO;

    /**
     * The signed-in user's own cancellation: refused once the table has started.
     *
     * @throws ParticipantNotFoundException when the user has no registration in the table
     * @throws CannotCancelException when the table has started or the registration is no longer active
     */
    public function cancelByUser(string $gameTableId, string $userId): ParticipantResponseDTO;

    /**
     * Cancelled by the organisation from the panel: the same as cancel() (lock, freed seat,
     * promotion), but the participant is told the organisation cancelled it and the directors
     * get nothing.
     *
     * @throws CannotCancelException when the registration is no longer active
     */
    public function cancelByAdmin(string $participantId): ParticipantResponseDTO;

    /**
     * The organisation approves a pending registration. For a guest whose email is not confirmed
     * it also vouches for the email: a guest without a seat yet (wait mode) is first placed into a
     * seat, and the guest gets the usual registration email instead of the approval one.
     *
     * @throws TableFullException with the waiting list on, when no player or spectator seat is free; for a guest without a seat yet, whenever none is free (nothing changes)
     */
    public function confirm(string $participantId): ParticipantResponseDTO;

    /**
     * Where pending players hold seats (waiting list on, manual confirmation), rejecting a pending
     * player promotes the first waiting one. The optional reason (blank counts as none) goes in the email.
     *
     * @throws ParticipantNotPendingException when the participant is neither pending nor on the waiting list (nothing changes)
     */
    public function reject(string $participantId, ?string $reason = null): ParticipantResponseDTO;

    /**
     * A director approves a pending request from the website («Gestionar inscritos»). Unlike
     * confirm(), under the table lock it requires a pending registration, never vouches for a
     * guest's email and always checks the seats, whatever the waiting list setting: only confirmed
     * registrations count, the one being approved needs a free seat among them.
     *
     * @throws ParticipantNotPendingException when the registration is no longer pending (nothing changes)
     * @throws ParticipantAwaitingEmailException for a guest whose email is not confirmed (nothing changes)
     * @throws TableClosedException when the table has ended or been cancelled (nothing changes)
     * @throws TableFullException when no player or spectator seat is free (nothing changes)
     */
    public function approveAsDirector(string $participantId): ParticipantResponseDTO;

    /**
     * A director rejects a pending request from the website: reject(), but only a pending
     * registration, checked under the table lock (the waiting list is the organisation's).
     *
     * @throws ParticipantNotPendingException when the registration is no longer pending (nothing changes)
     */
    public function rejectAsDirector(string $participantId, ?string $reason = null): ParticipantResponseDTO;

    public function promoteFromWaitingList(string $gameTableId): ?ParticipantResponseDTO;

    /**
     * Promote a specific participant from waiting list (confirmed; unchanged if no longer waiting).
     * For admin use when promoting a specific person instead of the first in queue; a guest whose
     * email is not confirmed may be chosen too, and the promotion vouches for the email: the guest
     * gets the usual registration email (it carries the cancellation link) instead of the
     * promotion one, and the directors nothing.
     *
     * @throws TableFullException with the waiting list on, when no player or spectator seat is free
     */
    public function promoteParticipant(string $participantId): ParticipantResponseDTO;

    /**
     * Register a participant by admin (bypasses eligibility checks).
     */
    public function registerByAdmin(RegisterParticipantDTO $dto): ParticipantResponseDTO;

    /**
     * Register a guest participant by admin (bypasses eligibility checks). The organisation vouches
     * for the email: it needs no confirmation and the usual emails go out at once.
     */
    public function registerGuestByAdmin(RegisterParticipantDTO $dto): ParticipantResponseDTO;

    /**
     * A confirmed participant who did not come, once the table has started (no emails, no
     * promotion). An unconfirmed guest's email counts as confirmed, silently.
     *
     * @throws InvalidStatusTransitionException before the start or without a confirmed seat
     */
    public function markAsNoShow(string $participantId): ParticipantResponseDTO;

    /**
     * @return array<ParticipantResponseDTO>
     */
    public function getByGameTable(string $gameTableId): array;

    /**
     * @return array<ParticipantResponseDTO>
     */
    public function getWaitingList(string $gameTableId): array;

    /**
     * @return array<ParticipantResponseDTO>
     */
    public function getByUser(string $userId): array;

    public function find(string $participantId): ?ParticipantResponseDTO;

    public function findByTableAndUser(string $gameTableId, string $userId): ?ParticipantResponseDTO;

    /**
     * A guest registration from the public form, left unconfirmed until the guest follows the
     * emailed link: only the confirmation email goes out. Sending the form again while unconfirmed
     * returns the same registration (confirmationResent) and sends the email again at most once
     * every few minutes.
     *
     * @throws RegistrationNotAllowedException when the table does not admit the guest
     * @throws AccountEmailRequiresLoginException when the email belongs to an account that should sign in
     * @throws AlreadyRegisteredException when the email already has a confirmed registration at the table, or its account is registered
     */
    public function registerGuest(RegisterParticipantDTO $dto): ParticipantResponseDTO;

    /**
     * Cancel a registration using a cancellation token (the guest's own cancellation): refused
     * once the table has started.
     *
     * @throws ParticipantNotFoundException when no registration has the token
     * @throws CannotCancelException when the table has started or the registration is no longer active
     */
    public function cancelByToken(string $token): ParticipantResponseDTO;

    /**
     * Why the participant can no longer cancel the registration themselves (the table page or the
     * emailed link): 'not_allowed' when it is not active or does not exist, 'table_started' once
     * the table has started; null while they can. The panel's cancellations have no such limit.
     */
    public function withdrawalRefusal(string $participantId, ?DateTimeImmutable $now = null): ?string;

    /**
     * Find a participant by cancellation token.
     */
    public function findByToken(string $token): ?ParticipantResponseDTO;

    /**
     * The guest follows the link of the confirmation email. Under the table lock: an unknown,
     * cleared or inactive token is Invalid; a confirmed email AlreadyConfirmed; once registrations
     * have closed the registration expires (Closed), and past its deadline too (Invalid). Otherwise
     * the email is confirmed: a held place is kept (from the waiting list it takes a free seat), a
     * guest without one is placed now, or expires when no room is left (FilledUp). Only a
     * Confirmed registration is announced: the guest's registration email and the organisation.
     * An expired registration is announced as the expiry command does (the guest is emailed only
     * when it held a place).
     */
    public function confirmGuestEmail(string $token): GuestConfirmationResultDTO;

    /**
     * «Marcar email como confirmado» from the panel: the same as the emailed link, under the table
     * lock, whatever the dates (the organisation vouches for the email). A held place is kept (from
     * the waiting list it takes a free seat); a guest without one is placed now. The guest gets the
     * usual registration email; the organisation nothing. A registration that is not awaiting
     * confirmation is returned unchanged.
     *
     * @throws TableFullException when a guest without a place finds no room (nothing changes)
     */
    public function confirmGuestEmailByAdmin(string $participantId): ParticipantResponseDTO;

    /**
     * Expires a guest registration that is still unconfirmed and due: past its deadline, or with
     * the registrations of its table closed. Under the table lock, as a cancellation: the seat is
     * freed for the waiting list (with its notices). The organisation is never told.
     *
     * @param  bool  $notifyGuest  whether the guest gets the expiry email
     * @return bool false when the row is no longer unconfirmed (confirmed, cancelled, deleted) or not due
     */
    public function expireGuestRegistration(string $participantId, bool $notifyGuest = true, ?DateTimeImmutable $now = null): bool;

    /**
     * Find a participant by guest confirmation token.
     */
    public function findByConfirmationToken(string $token): ?ParticipantResponseDTO;

    /**
     * Get user participations for profile page.
     * Returns participations excluding Cancelled, Rejected statuses.
     * Sorted: upcoming first (ASC by date), then past (DESC by date).
     *
     * @return array<ProfileParticipationDTO>
     */
    public function getByUserForProfile(string $userId): array;
}
