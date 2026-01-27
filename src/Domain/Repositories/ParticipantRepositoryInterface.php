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

    public function getNextWaitingListPosition(GameTableId $gameTableId): int;

    public function getFirstInWaitingList(GameTableId $gameTableId): ?Participant;

    public function findByTableAndEmail(GameTableId $gameTableId, string $email): ?Participant;

    public function findByCancellationToken(string $token): ?Participant;
}
