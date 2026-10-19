<?php

declare(strict_types=1);

namespace Modules\GameTables\Infrastructure\Persistence\Eloquent\Repositories;

use DateTimeImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Modules\GameTables\Domain\Entities\Participant;
use Modules\GameTables\Domain\Enums\GuestConfirmationMode;
use Modules\GameTables\Domain\Enums\ParticipantRole;
use Modules\GameTables\Domain\Enums\ParticipantStatus;
use Modules\GameTables\Domain\Exceptions\AlreadyRegisteredException;
use Modules\GameTables\Domain\Exceptions\ParticipantNotFoundException;
use Modules\GameTables\Domain\Repositories\ParticipantRepositoryInterface;
use Modules\GameTables\Domain\ValueObjects\GameTableId;
use Modules\GameTables\Domain\ValueObjects\ParticipantId;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\ParticipantModel;

final readonly class EloquentParticipantRepository implements ParticipantRepositoryInterface
{
    private const string ACTIVE_GUEST_EMAIL_INDEX = 'gametables_participants_active_guest_email_unique';

    private const array ACTIVE_STATUSES = [
        ParticipantStatus::Pending->value,
        ParticipantStatus::Confirmed->value,
        ParticipantStatus::WaitingList->value,
    ];

    /**
     * @throws AlreadyRegisteredException when another active guest already uses the email at the table
     */
    public function save(Participant $participant): void
    {
        try {
            ParticipantModel::query()->updateOrCreate(
                ['id' => $participant->id->value],
                $this->toArray($participant),
            );
        } catch (UniqueConstraintViolationException $exception) {
            if ($this->isActiveGuestEmailViolation($exception) && $participant->email !== null) {
                throw AlreadyRegisteredException::forGuestEmail($participant->email, $participant->gameTableId->value);
            }

            throw $exception;
        }
    }

    /**
     * PostgreSQL names the index; SQLite names the index too when it is an expression index, and
     * the columns otherwise. The token and primary key columns never include game_table_id.
     */
    private function isActiveGuestEmailViolation(UniqueConstraintViolationException $exception): bool
    {
        $message = $exception->getMessage();

        return str_contains($message, self::ACTIVE_GUEST_EMAIL_INDEX)
            || str_contains($message, 'gametables_participants.game_table_id');
    }

    public function find(ParticipantId $id): ?Participant
    {
        $model = ParticipantModel::query()->find($id->value);

        if ($model === null) {
            return null;
        }

        return $this->toEntity($model);
    }

    public function findOrFail(ParticipantId $id): Participant
    {
        $participant = $this->find($id);

        if ($participant === null) {
            throw ParticipantNotFoundException::withId($id->value);
        }

        return $participant;
    }

    public function delete(ParticipantId $id): void
    {
        ParticipantModel::query()->where('id', $id->value)->delete();
    }

    public function findByTableAndUser(GameTableId $gameTableId, string $userId): ?Participant
    {
        $model = ParticipantModel::query()
            ->where('game_table_id', $gameTableId->value)
            ->where('user_id', $userId)
            ->tap($this->activeFirst(...))
            ->first();

        if ($model === null) {
            return null;
        }

        return $this->toEntity($model);
    }

    /**
     * @return array<Participant>
     */
    public function getByGameTable(GameTableId $gameTableId): array
    {
        return ParticipantModel::query()
            ->where('game_table_id', $gameTableId->value)
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(fn (ParticipantModel $model): Participant => $this->toEntity($model))
            ->all();
    }

    /**
     * @return array<Participant>
     */
    public function getByGameTableAndStatus(GameTableId $gameTableId, ParticipantStatus $status): array
    {
        return ParticipantModel::query()
            ->where('game_table_id', $gameTableId->value)
            ->where('status', $status->value)
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(fn (ParticipantModel $model): Participant => $this->toEntity($model))
            ->all();
    }

    /**
     * @return array<Participant>
     */
    public function getByGameTableAndRole(GameTableId $gameTableId, ParticipantRole $role): array
    {
        return ParticipantModel::query()
            ->where('game_table_id', $gameTableId->value)
            ->where('role', $role->value)
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(fn (ParticipantModel $model): Participant => $this->toEntity($model))
            ->all();
    }

    /**
     * @return array<Participant>
     */
    public function getWaitingList(GameTableId $gameTableId): array
    {
        return $this->waitingList($gameTableId)
            ->get()
            ->map(fn (ParticipantModel $model): Participant => $this->toEntity($model))
            ->all();
    }

    /**
     * @return array<Participant>
     */
    public function getByUser(string $userId): array
    {
        return ParticipantModel::query()
            ->where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn (ParticipantModel $model): Participant => $this->toEntity($model))
            ->all();
    }

    /**
     * @return array<Participant>
     */
    public function getConfirmedPlayers(GameTableId $gameTableId): array
    {
        return ParticipantModel::query()
            ->where('game_table_id', $gameTableId->value)
            ->where('status', ParticipantStatus::Confirmed->value)
            ->where('role', ParticipantRole::Player->value)
            ->orderBy('confirmed_at', 'asc')
            ->get()
            ->map(fn (ParticipantModel $model): Participant => $this->toEntity($model))
            ->all();
    }

    /**
     * @return array<Participant>
     */
    public function getConfirmedSpectators(GameTableId $gameTableId): array
    {
        return ParticipantModel::query()
            ->where('game_table_id', $gameTableId->value)
            ->where('status', ParticipantStatus::Confirmed->value)
            ->where('role', ParticipantRole::Spectator->value)
            ->orderBy('confirmed_at', 'asc')
            ->get()
            ->map(fn (ParticipantModel $model): Participant => $this->toEntity($model))
            ->all();
    }

    public function countConfirmedPlayers(GameTableId $gameTableId): int
    {
        return ParticipantModel::query()
            ->where('game_table_id', $gameTableId->value)
            ->where('status', ParticipantStatus::Confirmed->value)
            ->where('role', ParticipantRole::Player->value)
            ->count();
    }

    public function countConfirmedSpectators(GameTableId $gameTableId): int
    {
        return ParticipantModel::query()
            ->where('game_table_id', $gameTableId->value)
            ->where('status', ParticipantStatus::Confirmed->value)
            ->where('role', ParticipantRole::Spectator->value)
            ->count();
    }

    public function countPendingPlayers(GameTableId $gameTableId): int
    {
        return ParticipantModel::query()
            ->where('game_table_id', $gameTableId->value)
            ->where('status', ParticipantStatus::Pending->value)
            ->where('role', ParticipantRole::Player->value)
            ->holdingOrConfirmed()
            ->count();
    }

    public function countPendingSpectators(GameTableId $gameTableId): int
    {
        return ParticipantModel::query()
            ->where('game_table_id', $gameTableId->value)
            ->where('status', ParticipantStatus::Pending->value)
            ->where('role', ParticipantRole::Spectator->value)
            ->holdingOrConfirmed()
            ->count();
    }

    public function countWaiting(GameTableId $gameTableId, ParticipantRole $role): int
    {
        return $this->waitingList($gameTableId, $role)->count();
    }

    public function countUnconfirmedHoldingSeats(GameTableId $gameTableId): int
    {
        return ParticipantModel::query()
            ->where('game_table_id', $gameTableId->value)
            ->whereNull('user_id')
            ->whereNull('email_confirmed_at')
            ->where('confirmation_mode', GuestConfirmationMode::Hold->value)
            ->whereIn('status', [ParticipantStatus::Pending->value, ParticipantStatus::Confirmed->value])
            ->count();
    }

    public function idsOfOverdueHolds(DateTimeImmutable $now): array
    {
        /** @var list<string> */
        return ParticipantModel::query()
            ->whereNull('user_id')
            ->whereNull('email_confirmed_at')
            ->where('confirmation_mode', GuestConfirmationMode::Hold->value)
            ->whereIn('status', self::ACTIVE_STATUSES)
            ->whereNotNull('confirmation_expires_at')
            ->where('confirmation_expires_at', '<=', $now)
            ->orderBy('confirmation_expires_at')
            ->pluck('id')
            ->all();
    }

    public function unconfirmedWaitingByTable(): array
    {
        $byTable = [];

        $rows = ParticipantModel::query()
            ->whereNull('user_id')
            ->whereNull('email_confirmed_at')
            ->where('confirmation_mode', GuestConfirmationMode::Wait->value)
            ->whereIn('status', self::ACTIVE_STATUSES)
            ->orderBy('created_at')
            ->get(['id', 'game_table_id']);

        foreach ($rows as $row) {
            $byTable[$row->game_table_id][] = $row->id;
        }

        return $byTable;
    }

    public function gameTableIdsWithFingerprints(): array
    {
        /** @var list<string> */
        return ParticipantModel::query()
            ->whereNotNull('fingerprint')
            ->distinct()
            ->pluck('game_table_id')
            ->all();
    }

    public function clearFingerprints(array $gameTableIds): int
    {
        if ($gameTableIds === []) {
            return 0;
        }

        return ParticipantModel::query()
            ->whereIn('game_table_id', array_values($gameTableIds))
            ->whereNotNull('fingerprint')
            ->update(['fingerprint' => null]);
    }

    public function getNextWaitingListPosition(GameTableId $gameTableId): int
    {
        $maxPosition = ParticipantModel::query()
            ->where('game_table_id', $gameTableId->value)
            ->where('status', ParticipantStatus::WaitingList->value)
            ->max('waiting_list_position');

        return ($maxPosition ?? 0) + 1;
    }

    public function getFirstInWaitingList(GameTableId $gameTableId, ?ParticipantRole $role = null): ?Participant
    {
        // Guests who have not confirmed their email keep their place but are not promoted by themselves
        $model = $this->waitingList($gameTableId, $role)->withConfirmedEmail()->first();

        if ($model === null) {
            return null;
        }

        return $this->toEntity($model);
    }

    public function getWaitingListRank(ParticipantId $participantId): ?int
    {
        $model = ParticipantModel::query()->find($participantId->value);

        if ($model === null || $model->status !== ParticipantStatus::WaitingList) {
            return null;
        }

        return $this->getWaitingListRanks([new GameTableId($model->game_table_id)])[$model->id] ?? null;
    }

    public function getWaitingListRanks(array $gameTableIds): array
    {
        if ($gameTableIds === []) {
            return [];
        }

        $query = ParticipantModel::query()
            ->whereIn('game_table_id', array_map(fn (GameTableId $id): string => $id->value, $gameTableIds))
            ->where('status', ParticipantStatus::WaitingList->value);

        // Rows come in the waiting list order; each table and role keeps its own count
        $ranks = [];
        $places = [];

        foreach ($this->inWaitingListOrder($query)->get(['id', 'game_table_id', 'role']) as $model) {
            $list = $model->game_table_id.'|'.$model->role->value;
            $places[$list] = ($places[$list] ?? 0) + 1;
            $ranks[$model->id] = $places[$list];
        }

        return $ranks;
    }

    /**
     * The waiting list in its order: stored position, then registration date (the id only makes
     * ties deterministic). Promotions and the places shown both read it.
     *
     * @return Builder<ParticipantModel>
     */
    private function waitingList(GameTableId $gameTableId, ?ParticipantRole $role = null): Builder
    {
        return $this->inWaitingListOrder(ParticipantModel::query()
            ->where('game_table_id', $gameTableId->value)
            ->where('status', ParticipantStatus::WaitingList->value)
            ->when($role !== null, fn (Builder $query) => $query->where('role', $role?->value)));
    }

    /**
     * @param  Builder<ParticipantModel>  $query
     * @return Builder<ParticipantModel>
     */
    private function inWaitingListOrder(Builder $query): Builder
    {
        return $query
            ->orderBy('waiting_list_position', 'asc')
            ->orderBy('created_at', 'asc')
            ->orderBy('id', 'asc');
    }

    /**
     * Older data may hold a cancelled row next to an active one for the same person:
     * the active row is the registration.
     *
     * @param  Builder<ParticipantModel>  $query
     */
    private function activeFirst(Builder $query): void
    {
        $query->orderByRaw(
            'case when status in (?, ?, ?) then 0 else 1 end',
            [ParticipantStatus::Pending->value, ParticipantStatus::Confirmed->value, ParticipantStatus::WaitingList->value],
        )->orderByDesc('created_at');
    }

    public function toEntity(ParticipantModel $model): Participant
    {
        return new Participant(
            id: new ParticipantId($model->id),
            gameTableId: new GameTableId($model->game_table_id),
            userId: $model->user_id,
            role: $model->role,
            status: $model->status,
            waitingListPosition: $model->waiting_list_position,
            notes: $model->notes,
            confirmedAt: $model->confirmed_at !== null
                ? new DateTimeImmutable($model->confirmed_at->toDateTimeString())
                : null,
            cancelledAt: $model->cancelled_at !== null
                ? new DateTimeImmutable($model->cancelled_at->toDateTimeString())
                : null,
            createdAt: $model->created_at !== null
                ? new DateTimeImmutable($model->created_at->toDateTimeString())
                : null,
            firstName: $model->first_name,
            lastName: $model->last_name,
            email: $model->email,
            phone: $model->phone,
            cancellationToken: $model->cancellation_token,
            emailConfirmedAt: $this->immutable($model->email_confirmed_at),
            confirmationExpiresAt: $this->immutable($model->confirmation_expires_at),
            confirmationToken: $model->confirmation_token,
            confirmationSentAt: $this->immutable($model->confirmation_sent_at),
            confirmationMode: $model->confirmation_mode,
            fingerprint: $model->fingerprint,
        );
    }

    private function immutable(?Carbon $value): ?DateTimeImmutable
    {
        return $value !== null ? new DateTimeImmutable($value->toDateTimeString()) : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function toArray(Participant $participant): array
    {
        return [
            'id' => $participant->id->value,
            'game_table_id' => $participant->gameTableId->value,
            'user_id' => $participant->userId,
            'first_name' => $participant->firstName,
            'last_name' => $participant->lastName,
            'email' => $participant->email,
            'phone' => $participant->phone,
            'cancellation_token' => $participant->cancellationToken,
            'role' => $participant->role->value,
            'status' => $participant->status->value,
            'waiting_list_position' => $participant->waitingListPosition,
            'notes' => $participant->notes,
            'confirmed_at' => $participant->confirmedAt,
            'cancelled_at' => $participant->cancelledAt,
            'email_confirmed_at' => $participant->emailConfirmedAt,
            'confirmation_expires_at' => $participant->confirmationExpiresAt,
            'confirmation_token' => $participant->confirmationToken,
            'confirmation_sent_at' => $participant->confirmationSentAt,
            'confirmation_mode' => $participant->confirmationMode?->value,
            'fingerprint' => $participant->fingerprint,
        ];
    }

    public function findByTableAndEmail(GameTableId $gameTableId, string $email): ?Participant
    {
        $model = ParticipantModel::query()
            ->where('game_table_id', $gameTableId->value)
            // Guest emails are stored in lowercase; older rows may keep capitals
            ->whereRaw('lower(email) = ?', [mb_strtolower(trim($email))])
            ->whereNull('user_id')
            ->tap($this->activeFirst(...))
            ->first();

        if ($model === null) {
            return null;
        }

        return $this->toEntity($model);
    }

    public function findByConfirmationToken(string $token): ?Participant
    {
        $model = ParticipantModel::query()
            ->where('confirmation_token', $token)
            ->first();

        if ($model === null) {
            return null;
        }

        return $this->toEntity($model);
    }

    public function findByCancellationToken(string $token): ?Participant
    {
        $model = ParticipantModel::query()
            ->where('cancellation_token', $token)
            ->first();

        if ($model === null) {
            return null;
        }

        return $this->toEntity($model);
    }

    /**
     * @return array<array{participant: Participant, game_table_id: string, game_table_title: string, game_table_slug: string, game_table_starts_at: \DateTimeImmutable|null, game_system_name: string}>
     */
    public function getActiveByUserWithTableData(string $userId): array
    {
        $models = ParticipantModel::query()
            ->with(['gameTable.gameSystem'])
            ->where('user_id', $userId)
            ->whereNotIn('status', [
                ParticipantStatus::Cancelled->value,
                ParticipantStatus::Rejected->value,
                ParticipantStatus::NoShow->value,
                ParticipantStatus::Expired->value,
            ])
            ->get();

        // Sort in PHP to be database-agnostic (works with both PostgreSQL and SQLite)
        $now = new DateTimeImmutable();
        $sorted = $models->sortBy(function (ParticipantModel $model) use ($now): array {
            $startsAt = $model->gameTable?->starts_at;
            if ($startsAt === null) {
                return [1, PHP_INT_MAX]; // Tables without date go last
            }

            $tableDate = new DateTimeImmutable($startsAt->toDateTimeString());
            $isUpcoming = $tableDate > $now;
            $diff = abs($tableDate->getTimestamp() - $now->getTimestamp());

            // Upcoming (0) before past (1), then by distance from now
            return [$isUpcoming ? 0 : 1, $diff];
        })->values();

        return $sorted->map(function (ParticipantModel $model): array {
            $gameTable = $model->gameTable;
            $gameSystem = $gameTable?->gameSystem;

            return [
                'participant' => $this->toEntity($model),
                'game_table_id' => $gameTable?->id ?? '',
                'game_table_title' => $gameTable?->title ?? '',
                'game_table_slug' => $gameTable?->slug ?? '',
                'game_table_starts_at' => $gameTable?->starts_at !== null
                    ? new DateTimeImmutable($gameTable->starts_at->toDateTimeString())
                    : null,
                'game_system_name' => $gameSystem?->name ?? '',
            ];
        })->all();
    }
}
