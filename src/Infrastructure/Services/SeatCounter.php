<?php

declare(strict_types=1);

namespace Modules\GameTables\Infrastructure\Services;

use Illuminate\Database\Eloquent\Builder;
use Modules\GameTables\Application\DTOs\GameTableResponseDTO;
use Modules\GameTables\Application\DTOs\TableSeatsDTO;
use Modules\GameTables\Domain\Entities\GameTable;
use Modules\GameTables\Domain\Enums\ParticipantRole;
use Modules\GameTables\Domain\Enums\ParticipantStatus;
use Modules\GameTables\Domain\Repositories\ParticipantRepositoryInterface;
use Modules\GameTables\Domain\ValueObjects\GameTableId;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameTableModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\ParticipantModel;

/**
 * The one count of taken seats, for players and spectators: the registration rules, the table
 * page and the table lists (cards, calendars, profile) all read it. Confirmed registrations always
 * take a seat; pending ones only where GameTable::pendingParticipantsHoldSeats() says so (waiting
 * list on, manual confirmation).
 */
final readonly class SeatCounter
{
    private const string CONFIRMED_PLAYERS = 'confirmed_players_count';

    private const string PENDING_PLAYERS = 'pending_players_count';

    public function __construct(
        private ParticipantRepositoryInterface $participantRepository,
        private GameTableSettingsReader $settings = new GameTableSettingsReader,
    ) {}

    public function takenPlayerSeats(GameTable $gameTable): int
    {
        return $this->takenPlayerSeatsById($gameTable->id, $gameTable->autoConfirm);
    }

    public function takenSpectatorSeats(GameTable $gameTable): int
    {
        return $this->takenSpectatorSeatsById($gameTable->id, $gameTable->autoConfirm);
    }

    public function pendingHoldSeats(GameTable $gameTable): bool
    {
        return $gameTable->pendingParticipantsHoldSeats($this->settings->isWaitingListEnabled());
    }

    /**
     * The seats of a table the page already holds, without loading it again.
     */
    public function forTable(GameTableResponseDTO $table): TableSeatsDTO
    {
        $id = new GameTableId($table->id);
        $takenPlayerSeats = $this->takenPlayerSeatsById($id, $table->autoConfirm);
        $takenSpectatorSeats = $this->takenSpectatorSeatsById($id, $table->autoConfirm);

        return new TableSeatsDTO(
            takenPlayerSeats: $takenPlayerSeats,
            freePlayerSeats: max(0, $table->maxPlayers - $takenPlayerSeats),
            takenSpectatorSeats: $takenSpectatorSeats,
            freeSpectatorSeats: max(0, $table->maxSpectators - $takenSpectatorSeats),
            waitingPlayers: $this->participantRepository->countWaiting($id, ParticipantRole::Player),
        );
    }

    /**
     * Adds to a table list query the counts takenPlayerSeatsOf() reads, as subqueries of the same
     * query, so a list does not query once per table.
     *
     * @param  Builder<GameTableModel>  $query
     * @return Builder<GameTableModel>
     */
    public static function withPlayerSeatCounts(Builder $query): Builder
    {
        return $query->withCount([
            'participants as '.self::CONFIRMED_PLAYERS => fn (Builder $participants): Builder => $participants
                ->where('role', ParticipantRole::Player->value)
                ->where('status', ParticipantStatus::Confirmed->value),
            'participants as '.self::PENDING_PLAYERS => fn (Builder $participants): Builder => $participants
                ->where('role', ParticipantRole::Player->value)
                ->where('status', ParticipantStatus::Pending->value)
                ->holdingOrConfirmed(),
        ]);
    }

    /**
     * The taken player seats of a listed table: from the counts of withPlayerSeatCounts(), else
     * from its loaded participants, else from the database.
     */
    public function takenPlayerSeatsOf(GameTableModel $table): int
    {
        $attributes = $table->getAttributes();

        if (array_key_exists(self::CONFIRMED_PLAYERS, $attributes) && array_key_exists(self::PENDING_PLAYERS, $attributes)) {
            return $this->taken($table->auto_confirm, (int) $attributes[self::CONFIRMED_PLAYERS], (int) $attributes[self::PENDING_PLAYERS]);
        }

        if ($table->relationLoaded('participants')) {
            $players = $table->participants->filter(fn (ParticipantModel $participant): bool => $participant->role === ParticipantRole::Player);

            return $this->taken(
                $table->auto_confirm,
                $players->filter(fn (ParticipantModel $participant): bool => $participant->status === ParticipantStatus::Confirmed)->count(),
                $players->filter(fn (ParticipantModel $participant): bool => $participant->status === ParticipantStatus::Pending && ! $participant->isUnconfirmedWaiting())->count(),
            );
        }

        return $this->takenPlayerSeatsById(new GameTableId($table->id), $table->auto_confirm);
    }

    private function takenPlayerSeatsById(GameTableId $id, bool $autoConfirm): int
    {
        $confirmed = $this->participantRepository->countConfirmedPlayers($id);

        return $this->holdSeats($autoConfirm)
            ? $confirmed + $this->participantRepository->countPendingPlayers($id)
            : $confirmed;
    }

    private function takenSpectatorSeatsById(GameTableId $id, bool $autoConfirm): int
    {
        $confirmed = $this->participantRepository->countConfirmedSpectators($id);

        return $this->holdSeats($autoConfirm)
            ? $confirmed + $this->participantRepository->countPendingSpectators($id)
            : $confirmed;
    }

    private function taken(bool $autoConfirm, int $confirmed, int $pending): int
    {
        return $this->holdSeats($autoConfirm) ? $confirmed + $pending : $confirmed;
    }

    private function holdSeats(bool $autoConfirm): bool
    {
        return GameTable::pendingHoldSeatsWhen($this->settings->isWaitingListEnabled(), $autoConfirm);
    }
}
