<?php

declare(strict_types=1);

namespace Modules\GameTables\Infrastructure\Persistence\Eloquent\Repositories;

use DateTimeImmutable;
use Modules\GameTables\Domain\Entities\Participant;
use Modules\GameTables\Domain\Enums\ParticipantRole;
use Modules\GameTables\Domain\Enums\ParticipantStatus;
use Modules\GameTables\Domain\Exceptions\ParticipantNotFoundException;
use Modules\GameTables\Domain\Repositories\ParticipantRepositoryInterface;
use Modules\GameTables\Domain\ValueObjects\GameTableId;
use Modules\GameTables\Domain\ValueObjects\ParticipantId;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\ParticipantModel;

final readonly class EloquentParticipantRepository implements ParticipantRepositoryInterface
{
    public function save(Participant $participant): void
    {
        ParticipantModel::query()->updateOrCreate(
            ['id' => $participant->id->value],
            $this->toArray($participant),
        );
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
        return ParticipantModel::query()
            ->where('game_table_id', $gameTableId->value)
            ->where('status', ParticipantStatus::WaitingList->value)
            ->orderBy('waiting_list_position', 'asc')
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

    public function getNextWaitingListPosition(GameTableId $gameTableId): int
    {
        $maxPosition = ParticipantModel::query()
            ->where('game_table_id', $gameTableId->value)
            ->where('status', ParticipantStatus::WaitingList->value)
            ->max('waiting_list_position');

        return ($maxPosition ?? 0) + 1;
    }

    public function getFirstInWaitingList(GameTableId $gameTableId): ?Participant
    {
        $model = ParticipantModel::query()
            ->where('game_table_id', $gameTableId->value)
            ->where('status', ParticipantStatus::WaitingList->value)
            ->orderBy('waiting_list_position', 'asc')
            ->first();

        if ($model === null) {
            return null;
        }

        return $this->toEntity($model);
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
        );
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
        ];
    }

    public function findByTableAndEmail(GameTableId $gameTableId, string $email): ?Participant
    {
        $model = ParticipantModel::query()
            ->where('game_table_id', $gameTableId->value)
            ->where('email', $email)
            ->whereNull('user_id')
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
}
