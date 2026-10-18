<?php

declare(strict_types=1);

namespace Modules\GameTables\Console\Commands;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Modules\GameTables\Infrastructure\Services\GameTableSettingsReader;
use Modules\GameTables\Infrastructure\Services\GameTableStatusUpdater;

final class UpdateTableStatusesCommand extends Command
{
    protected $signature = 'game-tables:update-statuses';

    protected $description = 'Start the tables whose start time has come and complete the ones that have ended';

    public function handle(GameTableSettingsReader $settings, GameTableStatusUpdater $updater): int
    {
        if (! $settings->isAutoStatusEnabled()) {
            $this->info('Automatic status updates are disabled.');

            return self::SUCCESS;
        }

        // In the application timezone, the one starts_at is saved in
        $counts = $updater->update(CarbonImmutable::now());

        // A table that failed is in the log; the others have been updated anyway
        $this->info(sprintf(
            'Started %d table(s), completed %d, skipped %d, failed %d.',
            $counts['started'],
            $counts['completed'],
            $counts['skipped'],
            $counts['failed'],
        ));

        return self::SUCCESS;
    }
}
