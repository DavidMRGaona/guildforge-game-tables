<?php

declare(strict_types=1);

namespace Modules\GameTables\Infrastructure\Services;

use Closure;
use Illuminate\Support\Facades\DB;
use Modules\GameTables\Application\Services\RegistrationLockInterface;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameTableModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\ParticipantModel;

/**
 * A row lock (SELECT … FOR UPDATE) on the table, held until the transaction ends.
 * SQLite has no row locks, but it runs one write transaction at a time.
 */
final readonly class DatabaseRegistrationLock implements RegistrationLockInterface
{
    public function forTable(string $gameTableId, Closure $callback): mixed
    {
        return DB::transaction(function () use ($gameTableId, $callback): mixed {
            GameTableModel::query()->whereKey($gameTableId)->lockForUpdate()->value('id');

            return $callback();
        });
    }

    public function forParticipant(string $participantId, Closure $callback): mixed
    {
        return $this->forTableOf(ParticipantModel::query()->whereKey($participantId)->value('game_table_id'), $callback);
    }

    public function forCancellationToken(string $token, Closure $callback): mixed
    {
        return $this->forTableOf(ParticipantModel::query()->where('cancellation_token', $token)->value('game_table_id'), $callback);
    }

    /**
     * Without a participant there is nothing to lock: the callback reports it as not found.
     *
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    private function forTableOf(mixed $gameTableId, Closure $callback): mixed
    {
        if (! is_string($gameTableId)) {
            return DB::transaction($callback);
        }

        return $this->forTable($gameTableId, $callback);
    }
}
