<?php

declare(strict_types=1);

namespace Modules\GameTables\Domain\Repositories;

use Modules\GameTables\Domain\Entities\Participant;
use Modules\GameTables\Domain\Enums\ParticipantRole;
use Modules\GameTables\Domain\Enums\ParticipantStatus;
use Modules\GameTables\Domain\ValueObjects\GameTableId;
use Modules\GameTables\Domain\ValueObjects\ParticipantId;

interface ParticipantRepositoryInterface
{
    public function save(Participant $participant): void;

    public function find(ParticipantId $id): ?Participant;

    public function findOrFail(ParticipantId $id): Participant;

    public function delete(ParticipantId $id): void;

    public function findByTableAndUser(GameTableId $gameTableId, string $userId): ?Participant;

    /**
     * @return array<Participant>
     */
    public function getByGameTable(GameTableId $gameTableId): array;

    /**
     * @return array<Participant>
     */
    public function getByGameTableAndStatus(GameTableId $gameTableId, ParticipantStatus $status): array;

    /**
     * @return array<Participant>
     */
    public function getByGameTableAndRole(GameTableId $gameTableId, ParticipantRole $role): array;

    /**
     * @return array<Participant>
     */
    public function getWaitingList(GameTableId $gameTableId): array;

    /**
     * @return array<Participant>
     */
    public function getByUser(string $userId): array;

    /**
     * @return array<Participant>
     */
    public function getConfirmedPlayers(GameTableId $gameTableId): array;

    /**
     * @return array<Participant>
     */
    public function getConfirmedSpectators(GameTableId $gameTableId): array;

    public function countConfirmedPlayers(GameTableId $gameTableId): int;

    public function countConfirmedSpectators(GameTableId $gameTableId): int;

    public function findByConfirmationToken(string $token): ?Participant;

    /**
     * Pending players holding a seat: guests with an unconfirmed email and no seat (mode wait) are left out.
     */
    public function countPendingPlayers(GameTableId $gameTableId): int;

    public function countPendingSpectators(GameTableId $gameTableId): int;

    /**
     * Participants with the given role who are on the waiting list now.
     */
    public function countWaiting(GameTableId $gameTableId, ParticipantRole $role): int;

    /**
     * Guests whose email is not confirmed yet and who hold a seat (hold mode, pending or confirmed).
     * Waiting-list rows do not count.
     */
    public function countUnconfirmedHoldingSeats(GameTableId $gameTableId): int;

    public function getNextWaitingListPosition(GameTableId $gameTableId): int;

    /**
     * First in the waiting list, optionally only among the participants with the given role.
     * Guests whose email is not confirmed are skipped (they keep their place).
     * The waiting list order is the stored position, then the registration date.
     */
    public function getFirstInWaitingList(GameTableId $gameTableId, ?ParticipantRole $role = null): ?Participant;

    /**
     * The participant's place (1-based) among those waiting for the same role, in the waiting
     * list order; null when they are not on the waiting list. Stored positions may have gaps.
     */
    public function getWaitingListRank(ParticipantId $participantId): ?int;

    /**
     * The places of everyone waiting at the given tables (as getWaitingListRank() gives them), in
     * one query, for lists that show many: participant id => place.
     *
     * @param  array<GameTableId>  $gameTableIds
     * @return array<string, int>
     */
    public function getWaitingListRanks(array $gameTableIds): array;

    public function findByTableAndEmail(GameTableId $gameTableId, string $email): ?Participant;

    public function findByCancellationToken(string $token): ?Participant;

    /**
     * Get active participations for a user with their game table and game system data.
     * Excludes Cancelled, Rejected, and NoShow statuses.
     *
     * @return array<array{participant: Participant, game_table_id: string, game_table_title: string, game_table_slug: string, game_table_starts_at: \DateTimeImmutable|null, game_system_name: string}>
     */
    public function getActiveByUserWithTableData(string $userId): array;
}
