<?php

declare(strict_types=1);

namespace Modules\GameTables\Infrastructure\Services;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Modules\GameTables\Application\DTOs\TableRegistrationRowDTO;
use Modules\GameTables\Application\DTOs\TableRegistrationsPageDTO;
use Modules\GameTables\Application\Services\TableRegistrationsQueryInterface;
use Modules\GameTables\Domain\Entities\Participant;
use Modules\GameTables\Domain\Enums\ParticipantStatus;
use Modules\GameTables\Domain\Repositories\GameTableRepositoryInterface;
use Modules\GameTables\Domain\Repositories\ParticipantRepositoryInterface;
use Modules\GameTables\Domain\ValueObjects\GameTableId;
use Modules\GameTables\Domain\ValueObjects\ParticipantId;

final readonly class TableRegistrationsQuery implements TableRegistrationsQueryInterface
{
    public function __construct(
        private GameTableRepositoryInterface $gameTables,
        private ParticipantRepositoryInterface $participants,
    ) {}

    public function page(string $gameTableId): TableRegistrationsPageDTO
    {
        $table = $this->gameTables->findOrFail(new GameTableId($gameTableId));
        $participants = array_values(array_filter(
            $this->participants->getByGameTable($table->id),
            $this->isListed(...),
        ));

        // usort is stable: within a group the repository's order (oldest request first) stays
        usort($participants, static fn (Participant $a, Participant $b): int => self::sortKey($a) <=> self::sortKey($b));

        $names = $this->accountNames($participants);
        $rows = array_map(fn (Participant $participant): TableRegistrationRowDTO => $this->toRow($participant, $names), $participants);

        return new TableRegistrationsPageDTO(
            table: [
                'title' => $table->title,
                'slug' => $table->slug,
                'starts_at' => $table->timeSlot->startsAt->format('c'),
                'auto_confirm' => $table->autoConfirm,
                'max_players' => $table->maxPlayers,
                'confirmed_players' => $this->participants->countConfirmedPlayers($table->id),
            ],
            rows: $rows,
            pendingCount: count(array_filter($participants, static fn (Participant $participant): bool => $participant->isPending())),
        );
    }

    public function row(string $gameTableId, string $participantId): ?TableRegistrationRowDTO
    {
        $participant = $this->participants->find(new ParticipantId($participantId));

        if (!$participant instanceof \Modules\GameTables\Domain\Entities\Participant || $participant->gameTableId->value !== $gameTableId || ! $this->isListed($participant)) {
            return null;
        }

        return $this->toRow($participant, $this->accountNames([$participant]));
    }

    /**
     * Players and spectators: game master rows are not requests to answer.
     */
    private function isListed(Participant $participant): bool
    {
        return $participant->isPlayer() || $participant->isSpectator();
    }

    /**
     * Pending, confirmed, the waiting list by position, then the rest.
     *
     * @return array{int, int}
     */
    private static function sortKey(Participant $participant): array
    {
        return match ($participant->status) {
            ParticipantStatus::Pending => [0, 0],
            ParticipantStatus::Confirmed => [1, 0],
            ParticipantStatus::WaitingList => [2, $participant->waitingListPosition ?? PHP_INT_MAX],
            default => [3, 0],
        };
    }

    /**
     * @param  array<string, string>  $names
     */
    private function toRow(Participant $participant, array $names): TableRegistrationRowDTO
    {
        $awaiting = $participant->isAwaitingEmailConfirmation();
        $pending = $participant->isPending();

        return new TableRegistrationRowDTO(
            id: $participant->id->value,
            name: $participant->userId !== null ? ($names[$participant->userId] ?? '') : $participant->getDisplayName(),
            isGuest: $participant->isGuest(),
            role: $participant->role->value,
            roleLabel: $participant->role->label(),
            status: $participant->status->value,
            statusLabel: $participant->status->label(),
            requestedAt: $participant->createdAt?->format('c'),
            awaitingEmailConfirmation: $awaiting,
            canApprove: $pending && ! $awaiting,
            canReject: $pending,
        );
    }

    /**
     * The account names of the participants, in one read.
     *
     * @param  list<Participant>  $participants
     * @return array<string, string>
     */
    private function accountNames(array $participants): array
    {
        $ids = array_values(array_filter(array_map(static fn (Participant $participant): ?string => $participant->userId, $participants)));

        if ($ids === []) {
            return [];
        }

        return UserModel::query()->whereIn('id', $ids)->pluck('name', 'id')
            ->map(static fn (mixed $name): string => (string) $name)
            ->all();
    }
}
