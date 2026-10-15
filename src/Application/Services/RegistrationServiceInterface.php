<?php

declare(strict_types=1);

namespace Modules\GameTables\Application\Services;

use Modules\GameTables\Application\DTOs\ParticipantResponseDTO;
use Modules\GameTables\Application\DTOs\ProfileParticipationDTO;
use Modules\GameTables\Application\DTOs\RegisterParticipantDTO;

interface RegistrationServiceInterface
{
    public function register(RegisterParticipantDTO $dto): ParticipantResponseDTO;

    public function cancel(string $participantId): ParticipantResponseDTO;

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
     * Register a guest participant by admin (bypasses eligibility checks).
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
     * Register a guest participant.
     */
    public function registerGuest(RegisterParticipantDTO $dto): ParticipantResponseDTO;

    /**
     * Cancel a registration using a cancellation token.
     */
    public function cancelByToken(string $token): ParticipantResponseDTO;

    /**
     * Find a participant by cancellation token.
     */
    public function findByToken(string $token): ?ParticipantResponseDTO;

    /**
     * Get user participations for profile page.
     * Returns participations excluding Cancelled, Rejected statuses.
     * Sorted: upcoming first (ASC by date), then past (DESC by date).
     *
     * @return array<ProfileParticipationDTO>
     */
    public function getByUserForProfile(string $userId): array;
}
