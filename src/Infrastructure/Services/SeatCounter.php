<?php

declare(strict_types=1);

namespace Modules\GameTables\Infrastructure\Services;

use Modules\GameTables\Application\DTOs\TableSeatsDTO;
use Modules\GameTables\Domain\Entities\GameTable;
use Modules\GameTables\Domain\Enums\ParticipantRole;
use Modules\GameTables\Domain\Repositories\GameTableRepositoryInterface;
use Modules\GameTables\Domain\Repositories\ParticipantRepositoryInterface;
use Modules\GameTables\Domain\ValueObjects\GameTableId;

/**
 * The one count of taken seats, for players and spectators: the registration rules and the table
 * page both read it. Confirmed registrations always take a seat; pending ones only where
 * GameTable::pendingParticipantsHoldSeats() says so (waiting list on, manual confirmation).
 */
final readonly class SeatCounter
{
    public function __construct(
        private ParticipantRepositoryInterface $participantRepository,
        private GameTableRepositoryInterface $gameTableRepository,
        private GameTableSettingsReader $settings = new GameTableSettingsReader(),
    ) {}

    public function takenPlayerSeats(GameTable $gameTable): int
    {
        $taken = $this->participantRepository->countConfirmedPlayers($gameTable->id);

        if ($this->pendingHoldSeats($gameTable)) {
            $taken += $this->participantRepository->countPendingPlayers($gameTable->id);
        }

        return $taken;
    }

    public function takenSpectatorSeats(GameTable $gameTable): int
    {
        $taken = $this->participantRepository->countConfirmedSpectators($gameTable->id);

        if ($this->pendingHoldSeats($gameTable)) {
            $taken += $this->participantRepository->countPendingSpectators($gameTable->id);
        }

        return $taken;
    }

    public function pendingHoldSeats(GameTable $gameTable): bool
    {
        return $gameTable->pendingParticipantsHoldSeats($this->settings->isWaitingListEnabled());
    }

    public function forTable(string $gameTableId): TableSeatsDTO
    {
        $gameTable = $this->gameTableRepository->findOrFail(new GameTableId($gameTableId));
        $takenPlayerSeats = $this->takenPlayerSeats($gameTable);
        $takenSpectatorSeats = $this->takenSpectatorSeats($gameTable);

        return new TableSeatsDTO(
            takenPlayerSeats: $takenPlayerSeats,
            freePlayerSeats: $gameTable->availablePlayerSlots($takenPlayerSeats),
            takenSpectatorSeats: $takenSpectatorSeats,
            freeSpectatorSeats: $gameTable->availableSpectatorSlots($takenSpectatorSeats),
            waitingPlayers: $this->participantRepository->countWaiting($gameTable->id, ParticipantRole::Player),
        );
    }
}
