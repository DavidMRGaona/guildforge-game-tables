<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Feature\Media;

use App\Application\Media\Services\MediaCatalogInterface;
use App\Infrastructure\Media\Jobs\RefreshMediaUsagesJob;
use App\Infrastructure\Media\Persistence\Eloquent\Models\MediaUsageModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\CampaignModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameSystemModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameTableModel;
use Tests\Support\Media\InMemoryMediaCatalog;
use Tests\Support\Modules\ModuleTestCase;

/**
 * The media usage index follows tables and campaigns as they are saved: a refresh is queued after the
 * commit, and running it rewrites the uses of that record.
 */
final class GameTablesMediaIndexTest extends ModuleTestCase
{
    protected ?string $moduleName = 'game-tables';

    protected bool $autoEnableModule = true;

    private InMemoryMediaCatalog $catalog;

    private GameSystemModel $system;

    protected function setUp(): void
    {
        parent::setUp();

        $this->useUtcStorage();
        // Models remove replaced images from the disk when saved
        Storage::fake('images');
        $this->catalog = new InMemoryMediaCatalog;
        $this->app->instance(MediaCatalogInterface::class, $this->catalog);
        $this->system = GameSystemModel::create(['id' => (string) Str::uuid(), 'name' => 'Sistema de ejemplo', 'slug' => Str::random(10), 'is_active' => true]);
    }

    private function table(?string $image): GameTableModel
    {
        return GameTableModel::create([
            'id' => (string) Str::uuid(),
            'game_system_id' => $this->system->id,
            'created_by' => UserModel::factory()->create()->id,
            'title' => 'Mesa de ejemplo',
            'slug' => 'mesa-'.Str::lower(Str::random(8)),
            'starts_at' => now()->addWeek(),
            'duration_minutes' => 240,
            'table_type' => 'one_shot',
            'table_format' => 'online',
            'status' => 'scheduled',
            'min_players' => 1,
            'max_players' => 5,
            'max_spectators' => 2,
            'language' => 'es',
            'registration_type' => 'everyone',
            'is_published' => true,
            'image_public_id' => $image,
        ]);
    }

    private function campaign(?string $image): CampaignModel
    {
        return CampaignModel::create([
            'id' => (string) Str::uuid(),
            'game_system_id' => $this->system->id,
            'created_by' => UserModel::factory()->create()->id,
            'title' => 'Campaña de ejemplo',
            'slug' => 'campana-'.Str::lower(Str::random(8)),
            'status' => 'recruiting',
            'is_published' => true,
            'image_public_id' => $image,
        ]);
    }

    private function queued(string $type, string $id): int
    {
        return Bus::dispatched(RefreshMediaUsagesJob::class, fn (RefreshMediaUsagesJob $job): bool => $job->type === $type
            && $job->recordId === $id
            && $job->afterCommit === true)->count();
    }

    public function test_creating_changing_the_image_and_deleting_a_table_queue_a_refresh(): void
    {
        Bus::fake();

        $table = $this->table('game-tables/2026/11/a.jpg');
        $this->assertSame(1, $this->queued('gametables.tables', $table->id));

        $table->update(['image_public_id' => 'game-tables/2026/11/b.jpg']);
        $this->assertSame(2, $this->queued('gametables.tables', $table->id));

        $table->delete();
        $this->assertSame(3, $this->queued('gametables.tables', $table->id));
    }

    public function test_saving_a_table_without_touching_its_image_queues_nothing(): void
    {
        $table = $this->table('game-tables/2026/11/a.jpg');
        Bus::fake();

        $table->update(['max_players' => 6]);

        $this->assertSame(0, $this->queued('gametables.tables', $table->id));
    }

    public function test_deleting_a_table_loaded_fresh_queues_a_refresh(): void
    {
        $table = $this->table('game-tables/2026/11/a.jpg');
        $fresh = GameTableModel::query()->findOrFail($table->id);
        Bus::fake();

        $fresh->delete();

        $this->assertSame(1, $this->queued('gametables.tables', $table->id));
    }

    public function test_deleting_a_campaign_loaded_fresh_queues_a_refresh(): void
    {
        $campaign = $this->campaign('campaigns/2026/11/a.jpg');
        $fresh = CampaignModel::query()->findOrFail($campaign->id);
        Bus::fake();

        $fresh->delete();

        $this->assertSame(1, $this->queued('gametables.campaigns', $campaign->id));
    }

    public function test_creating_changing_the_image_and_deleting_a_campaign_queue_a_refresh(): void
    {
        Bus::fake();

        $campaign = $this->campaign('campaigns/2026/11/a.jpg');
        $this->assertSame(1, $this->queued('gametables.campaigns', $campaign->id));

        $campaign->update(['image_public_id' => 'campaigns/2026/11/b.jpg']);
        $this->assertSame(2, $this->queued('gametables.campaigns', $campaign->id));

        $campaign->delete();
        $this->assertSame(3, $this->queued('gametables.campaigns', $campaign->id));
    }

    public function test_the_refresh_rewrites_the_uses_of_a_table(): void
    {
        Bus::fake();
        $table = $this->table('game-tables/2026/11/a.jpg');
        $this->catalog->add('game-tables/2026/11/a', bytes: 4096);

        $this->app->call([new RefreshMediaUsagesJob('gametables.tables', $table->id), 'handle']);

        $usage = MediaUsageModel::query()->where('source_type', 'gametables.tables')->firstOrFail();
        $this->assertSame('game-tables/2026/11/a', $usage->file_id);
        $this->assertSame($table->id, $usage->record_id);
        $this->assertSame('Mesa de ejemplo', $usage->title);

        $table->delete();
        $this->app->call([new RefreshMediaUsagesJob('gametables.tables', $table->id), 'handle']);

        $this->assertFalse(MediaUsageModel::query()->where('record_id', $table->id)->exists());
    }

    public function test_the_refresh_rewrites_the_uses_of_a_campaign(): void
    {
        Bus::fake();
        $campaign = $this->campaign('campaigns/2026/11/a.jpg');
        $this->catalog->add('campaigns/2026/11/a', bytes: 4096);

        $this->app->call([new RefreshMediaUsagesJob('gametables.campaigns', $campaign->id), 'handle']);

        $usage = MediaUsageModel::query()->where('source_type', 'gametables.campaigns')->firstOrFail();
        $this->assertSame('campaigns/2026/11/a', $usage->file_id);
        $this->assertSame('Campaña de ejemplo', $usage->title);
    }
}
