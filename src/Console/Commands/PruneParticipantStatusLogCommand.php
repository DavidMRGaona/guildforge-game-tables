<?php

declare(strict_types=1);

namespace Modules\GameTables\Console\Commands;

use App\Application\Services\TimezoneServiceInterface;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameTableModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\ParticipantStatusLogModel;

final class PruneParticipantStatusLogCommand extends Command
{
    protected $signature = 'game-tables:prune-participant-status-log';

    protected $description = 'Delete the registration history of tables that started more than 12 months ago';

    public function handle(): int
    {
        $timezone = app(TimezoneServiceInterface::class);
        // Twelve months on the site's calendar, bound in the storage zone
        $cutoff = $timezone->toStorage(CarbonImmutable::now($timezone->displayTimezone())->subMonthsNoOverflow(12));

        // Rows of deleted tables are already gone, by cascade
        $pruned = ParticipantStatusLogModel::query()
            ->whereIn('game_table_id', GameTableModel::query()->select('id')->where('starts_at', '<', $cutoff))
            ->delete();

        $this->info(sprintf('Pruned %d registration history row(s).', $pruned));

        return self::SUCCESS;
    }
}
