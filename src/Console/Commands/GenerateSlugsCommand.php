<?php

declare(strict_types=1);

namespace Modules\GameTables\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\CampaignModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameTableModel;

final class GenerateSlugsCommand extends Command
{
    protected $signature = 'gametables:generate-slugs
                            {--dry-run : Show what would be done without making changes}
                            {--force : Regenerate slugs even for records that already have one}';

    protected $description = 'Generate slugs for existing game tables and campaigns without slugs';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');

        if ($dryRun) {
            $this->warn('DRY RUN - No changes will be made');
            $this->newLine();
        }

        $this->info('Generating slugs for game tables and campaigns...');
        $this->newLine();

        $tablesUpdated = $this->generateSlugsForModel(GameTableModel::class, 'game_table', $dryRun, $force);
        $campaignsUpdated = $this->generateSlugsForModel(CampaignModel::class, 'campaign', $dryRun, $force);

        $this->newLine();
        $this->info('Summary:');
        $this->table(
            ['Entity', 'Records Updated'],
            [
                ['Game tables', $tablesUpdated],
                ['Campaigns', $campaignsUpdated],
            ]
        );

        if ($dryRun) {
            $this->newLine();
            $this->warn('Run without --dry-run to apply changes');
        }

        return Command::SUCCESS;
    }

    /**
     * @param  class-string<GameTableModel|CampaignModel>  $modelClass
     */
    private function generateSlugsForModel(string $modelClass, string $entityType, bool $dryRun, bool $force): int
    {
        $query = $modelClass::query();

        if (! $force) {
            $query->whereNull('slug');
        }

        $records = $query->get();
        $count = 0;

        $this->info(sprintf('Processing %d %ss...', $records->count(), $entityType));

        if ($records->isEmpty()) {
            $this->line('  No records need slug generation.');

            return 0;
        }

        $progressBar = $this->output->createProgressBar($records->count());
        $progressBar->start();

        foreach ($records as $record) {
            /** @var GameTableModel|CampaignModel $record */
            $slug = $record->generateUniqueSlug($record->title, $record->id);

            if ($dryRun) {
                $this->newLine();
                $this->line(sprintf('  [%s] "%s" -> %s', $record->id, $record->title, $slug));
            } else {
                // Direct update to avoid triggering model events
                DB::table($record->getTable())
                    ->where('id', $record->id)
                    ->update(['slug' => $slug]);
            }

            $count++;
            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine();

        return $count;
    }
}
