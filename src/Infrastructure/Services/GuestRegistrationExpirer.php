<?php

declare(strict_types=1);

namespace Modules\GameTables\Infrastructure\Services;

use DateTimeImmutable;
use Illuminate\Support\Facades\Log;
use Modules\GameTables\Application\Services\RegistrationServiceInterface;
use Modules\GameTables\Domain\Entities\GameTable;
use Modules\GameTables\Domain\Repositories\GameTableRepositoryInterface;
use Modules\GameTables\Domain\Repositories\ParticipantRepositoryInterface;
use Modules\GameTables\Domain\ValueObjects\GameTableId;
use Throwable;

/**
 * Unconfirmed guest registrations lapse with the clock: a place held until a deadline lapses at
 * the deadline (the guest is told), a guest waiting without a place lapses when the registrations
 * of the table close (silently). Each row is read again and handled on its own under its table
 * lock: one confirmed meanwhile is left alone, and one that fails is logged without stopping the
 * rest. The fingerprints of tables that have ended are cleared as well.
 */
final readonly class GuestRegistrationExpirer
{
    public function __construct(
        private RegistrationServiceInterface $registrations,
        private ParticipantRepositoryInterface $participants,
        private GameTableRepositoryInterface $tables,
    ) {}

    /**
     * @return array{expired: int, closed: int, fingerprintsCleared: int, failed: int}
     */
    public function run(DateTimeImmutable $now): array
    {
        $expired = 0;
        $closed = 0;
        $failed = 0;

        foreach ($this->participants->idsOfOverdueHolds($now) as $id) {
            match ($this->expire($id, notifyGuest: true, now: $now)) {
                true => $expired++,
                false => null,
                null => $failed++,
            };
        }

        foreach ($this->participants->unconfirmedWaitingByTable() as $gameTableId => $ids) {
            $table = $this->tables->find(new GameTableId($gameTableId));

            if (! $table instanceof GameTable || $now < $table->effectiveRegistrationClosesAt()) {
                continue;
            }

            foreach ($ids as $id) {
                match ($this->expire($id, notifyGuest: false, now: $now)) {
                    true => $closed++,
                    false => null,
                    null => $failed++,
                };
            }
        }

        return [
            'expired' => $expired,
            'closed' => $closed,
            'fingerprintsCleared' => $this->clearPastFingerprints($now),
            'failed' => $failed,
        ];
    }

    /**
     * @return bool|null whether it expired; null when it failed
     */
    private function expire(string $participantId, bool $notifyGuest, DateTimeImmutable $now): ?bool
    {
        try {
            return $this->registrations->expireGuestRegistration($participantId, $notifyGuest, $now);
        } catch (Throwable $e) {
            Log::error('game-tables: guest registration expiry failed', [
                'participant_id' => $participantId,
                'exception' => $e,
            ]);

            return null;
        }
    }

    private function clearPastFingerprints(DateTimeImmutable $now): int
    {
        $ended = [];

        foreach ($this->participants->gameTableIdsWithFingerprints() as $gameTableId) {
            $table = $this->tables->find(new GameTableId($gameTableId));

            if ($table instanceof GameTable && $table->timeSlot->hasEnded($now)) {
                $ended[] = $gameTableId;
            }
        }

        return $this->participants->clearFingerprints($ended);
    }
}
