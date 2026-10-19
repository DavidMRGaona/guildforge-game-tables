<?php

declare(strict_types=1);

namespace Modules\GameTables\Application\Services;

use DateTimeImmutable;
use Modules\GameTables\Application\DTOs\GuestConfirmationResultDTO;
use Modules\GameTables\Application\DTOs\ParticipantResponseDTO;
use Modules\GameTables\Application\DTOs\ProfileParticipationDTO;
use Modules\GameTables\Application\DTOs\RegisterParticipantDTO;

interface RegistrationServiceInterface
{
    public function register(RegisterParticipantDTO $dto): ParticipantResponseDTO;

    /**
     * The participant's own cancellation: refused once the table has started.
     *
     * @throws \Modules\GameTables\Domain\Exceptions\CannotCancelException when the table has started or the registration is no longer active
     */
    public function cancel(string $participantId): ParticipantResponseDTO;

    /**
     * The signed-in user's own cancellation: refused once the table has started.
     *
     * @throws \Modules\GameTables\Domain\Exceptions\ParticipantNotFoundException when the user has no registration in the table
     * @throws \Modules\GameTables\Domain\Exceptions\CannotCancelException when the table has started or the registration is no longer active
     */
    public function cancelByUser(string $gameTableId, string $userId): ParticipantResponseDTO;

    /**
     * Cancelled by the organisation from the panel: the same as cancel() (lock, freed seat,
     * promotion), but the participant is told the organisation cancelled it and the directors
     * get nothing.
     *
     * @throws \Modules\GameTables\Domain\Exceptions\CannotCancelException when the registration is no longer active
     */
    public function cancelByAdmin(string $participantId): ParticipantResponseDTO;

    /**
     * @throws \Modules\GameTables\Domain\Exceptions\TableFullException with the waiting list on, when no player or spectator seat is free
     */
    public function confirm(string $participantId): ParticipantResponseDTO;

    /**
     * Where pending players hold seats (waiting list on, manual confirmation), rejecting a pending
     * player promotes the first waiting one.
     */
    public function reject(string $participantId): ParticipantResponseDTO;

    public function promoteFromWaitingList(string $gameTableId): ?ParticipantResponseDTO;

    /**
     * Promote a specific participant from waiting list (confirmed; unchanged if no longer waiting).
     * For admin use when promoting a specific person instead of the first in queue.
     *
     * @throws \Modules\GameTables\Domain\Exceptions\TableFullException with the waiting list on, when no player or spectator seat is free
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
     * A confirmed participant who did not come, once the table has started (no emails, no promotion).
     *
     * @throws \Modules\GameTables\Domain\Exceptions\InvalidStatusTransitionException before the start or without a confirmed seat
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
     * @throws \Modules\GameTables\Domain\Exceptions\RegistrationNotAllowedException when the table does not admit the guest
     * @throws \Modules\GameTables\Domain\Exceptions\AccountEmailRequiresLoginException when the email belongs to an account that should sign in
     * @throws \Modules\GameTables\Domain\Exceptions\AlreadyRegisteredException when the email already has a confirmed registration at the table, or its account is registered
     */
    public function registerGuest(RegisterParticipantDTO $dto): ParticipantResponseDTO;

    /**
     * Cancel a registration using a cancellation token (the guest's own cancellation): refused
     * once the table has started.
     *
     * @throws \Modules\GameTables\Domain\Exceptions\ParticipantNotFoundException when no registration has the token
     * @throws \Modules\GameTables\Domain\Exceptions\CannotCancelException when the table has started or the registration is no longer active
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
     */
    public function confirmGuestEmail(string $token): GuestConfirmationResultDTO;

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
