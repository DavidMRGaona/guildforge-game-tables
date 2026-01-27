<?php

declare(strict_types=1);

namespace Modules\GameTables\Application\Services;

use Modules\GameTables\Application\DTOs\ParticipantResponseDTO;
use Modules\GameTables\Application\DTOs\RegisterParticipantDTO;

interface RegistrationServiceInterface
{
    public function register(RegisterParticipantDTO $dto): ParticipantResponseDTO;

    public function cancel(string $participantId): ParticipantResponseDTO;

    public function cancelByUser(string $gameTableId, string $userId): ParticipantResponseDTO;

    public function confirm(string $participantId): ParticipantResponseDTO;

    public function reject(string $participantId): ParticipantResponseDTO;

    public function promoteFromWaitingList(string $gameTableId): ?ParticipantResponseDTO;

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
}
