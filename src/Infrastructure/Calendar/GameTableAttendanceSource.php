<?php

declare(strict_types=1);

namespace Modules\GameTables\Infrastructure\Calendar;

use App\Application\Calendar\Contracts\CalendarAttendanceSourceInterface;
use App\Application\Calendar\DTOs\CalendarEntryReferenceDTO;
use App\Application\Services\TimezoneServiceInterface;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Modules\GameTables\Domain\Enums\ParticipantStatus;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameTableModel;

/**
 * Tables a person takes part in, for the personal calendar (core contract).
 *
 * Counts a table when the person has a confirmed seat (player or spectator; a
 * campaign roster registration is one too, because each session has its own
 * participant row) or directs it, by the rule the table page and the permissions
 * use (GameTableModel::scopeDirectedBy()): an own game master row not excluded, or
 * a current game master of the campaign not excluded from that session. Guests
 * have no account, so they never match. One query, whatever the number of tables.
 */
final readonly class GameTableAttendanceSource implements CalendarAttendanceSourceInterface
{
    public function __construct(
        private TimezoneServiceInterface $timezone,
    ) {}

    /**
     * @return array<CalendarEntryReferenceDTO>
     */
    public function attendedEntries(string $userId, DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        // Same window as the public calendar source: tables that start inside the range.
        $ids = GameTableModel::query()
            ->whereBetween('starts_at', [$this->timezone->toStorage($from), $this->timezone->toStorage($to)])
            ->where(function (Builder $query) use ($userId): void {
                $query
                    ->whereExists(fn ($seat) => $seat
                        ->select(DB::raw('1'))
                        ->from('gametables_participants as p')
                        ->whereColumn('p.game_table_id', 'gametables_tables.id')
                        ->where('p.user_id', $userId)
                        ->where('p.status', ParticipantStatus::Confirmed->value))
                    ->orWhere(fn (Builder $directed) => $directed->directedBy($userId));
            })
            ->pluck('gametables_tables.id');

        return $ids
            ->map(static fn (string $id): CalendarEntryReferenceDTO => new CalendarEntryReferenceDTO('game-table', $id))
            ->all();
    }
}
