<?php

declare(strict_types=1);

namespace Modules\GameTables\Infrastructure\Services;

use DateTimeImmutable;
use Illuminate\Support\Facades\Log;
use Modules\GameTables\Application\Services\GameTableServiceInterface;
use Modules\GameTables\Domain\Enums\TableStatus;
use Modules\GameTables\Domain\Exceptions\GameTableNotFoundException;
use Modules\GameTables\Domain\Exceptions\InvalidStatusTransitionException;
use Modules\GameTables\Domain\Repositories\GameTableRepositoryInterface;
use Modules\GameTables\Domain\ValueObjects\GameTableId;
use Throwable;

/**
 * Moves tables along with the clock, as the panel's «Iniciar» and «Completar» do: scheduled or
 * full tables start at their start time and tables in progress complete at their end (start plus
 * duration). Each table is read again and handled on its own: one changed or deleted by hand
 * meanwhile is skipped, and one that fails is logged without stopping the rest.
 */
final readonly class GameTableStatusUpdater
{
    private const string CHANGED = 'changed';

    private const string NOT_DUE = 'not_due';

    private const string SKIPPED = 'skipped';

    private const string FAILED = 'failed';

    public function __construct(
        private GameTableRepositoryInterface $gameTableRepository,
        private GameTableServiceInterface $gameTableService,
    ) {}

    /**
     * @return array{started: int, completed: int, skipped: int, failed: int}
     */
    public function update(DateTimeImmutable $now): array
    {
        $started = 0;
        $completed = 0;
        $skipped = 0;
        $failed = 0;

        $toStart = $this->gameTableRepository->idsStartedInStatuses([TableStatus::Scheduled, TableStatus::Full], $now);
        foreach ($toStart as $id) {
            match ($this->move($id, TableStatus::InProgress, $now)) {
                self::CHANGED => $started++,
                self::SKIPPED => $skipped++,
                self::FAILED => $failed++,
                self::NOT_DUE => null,
            };
        }

        // Read after the first step, so a table that has already ended goes through both in one run
        $toComplete = $this->gameTableRepository->idsStartedInStatuses([TableStatus::InProgress], $now);
        foreach ($toComplete as $id) {
            match ($this->move($id, TableStatus::Completed, $now)) {
                self::CHANGED => $completed++,
                self::SKIPPED => $skipped++,
                self::FAILED => $failed++,
                self::NOT_DUE => null,
            };
        }

        return ['started' => $started, 'completed' => $completed, 'skipped' => $skipped, 'failed' => $failed];
    }

    /**
     * @return self::CHANGED|self::NOT_DUE|self::SKIPPED|self::FAILED
     */
    private function move(string $id, TableStatus $to, DateTimeImmutable $now): string
    {
        try {
            // Read again: someone may have changed the table by hand since the list was read
            $table = $this->gameTableRepository->find(new GameTableId($id));

            if (!$table instanceof \Modules\GameTables\Domain\Entities\GameTable || ! $table->status->canTransitionTo($to)) {
                return self::SKIPPED;
            }

            $due = $to === TableStatus::InProgress
                ? $table->timeSlot->hasStarted($now)
                : $table->timeSlot->hasEnded($now);

            if (! $due) {
                return self::NOT_DUE;
            }

            if ($to === TableStatus::InProgress) {
                $this->gameTableService->start($id);
            } else {
                $this->gameTableService->complete($id);
            }

            return self::CHANGED;
        } catch (InvalidStatusTransitionException|GameTableNotFoundException) {
            // Changed or deleted by hand between the read and the save
            return self::SKIPPED;
        } catch (Throwable $e) {
            Log::error('game-tables: automatic status update failed', [
                'game_table_id' => $id,
                'to' => $to->value,
                'exception' => $e,
            ]);

            return self::FAILED;
        }
    }
}
