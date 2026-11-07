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
 * participant row) or is one of its game masters (own row, or inherited from the
 * campaign unless excluded for that session). Guests have no account, so they
 * never match. One query, whatever the number of tables.
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
                    ->orWhereExists(fn ($own) => $own
                        ->select(DB::raw('1'))
                        ->from('gametables_table_gm as tgm')
                        ->join('gametables_game_masters as gm', 'gm.id', '=', 'tgm.game_master_id')
                        ->whereColumn('tgm.game_table_id', 'gametables_tables.id')
                        ->where('gm.user_id', $userId)
                        ->where('tgm.excluded', false))
                    ->orWhere(function (Builder $inherited) use ($userId): void {
                        $inherited
                            ->whereExists(fn ($campaign) => $campaign
                                ->select(DB::raw('1'))
                                ->from('gametables_campaign_gm as cgm')
                                ->join('gametables_game_masters as gm', 'gm.id', '=', 'cgm.game_master_id')
                                ->whereColumn('cgm.campaign_id', 'gametables_tables.campaign_id')
                                ->where('gm.user_id', $userId)
                                // Excluded from this session only
                                ->whereNotExists(fn ($excluded) => $excluded
                                    ->select(DB::raw('1'))
                                    ->from('gametables_table_gm as ex')
                                    ->whereColumn('ex.game_table_id', 'gametables_tables.id')
                                    ->whereColumn('ex.game_master_id', 'gm.id')
                                    ->where('ex.excluded', true)));
                    });
            })
            ->pluck('gametables_tables.id');

        return $ids
            ->map(static fn (string $id): CalendarEntryReferenceDTO => new CalendarEntryReferenceDTO('game-table', $id))
            ->all();
    }
}
