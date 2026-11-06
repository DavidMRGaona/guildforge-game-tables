<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Integration\Infrastructure\Persistence\Eloquent\Repositories;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Support\Str;
use Modules\GameTables\Domain\Repositories\GameSystemRepositoryInterface;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\CampaignModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameSystemModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameTableModel;
use Tests\Support\Modules\ModuleTestCase;

/**
 * The game system filters of the public lists: active systems with how many tables or campaigns of
 * the public listing each has (the `publiclyListed()` scope), systems with none left out.
 */
final class EloquentGameSystemRepositoryCountsTest extends ModuleTestCase
{
    protected ?string $moduleName = 'game-tables';

    protected bool $autoEnableModule = true;

    public function test_only_listed_tables_count(): void
    {
        $system = $this->system('Sistema de ejemplo');
        $this->table($system, 'scheduled');
        $this->table($system, 'full');
        $this->table($system, 'draft');
        $this->table($system, 'cancelled');
        $this->table($system, 'scheduled', published: false);
        $this->table($this->system('Sistema sin mesas listadas'), 'cancelled');

        $this->assertSame(
            [['id' => $system->id, 'name' => 'Sistema de ejemplo', 'count' => 2]],
            $this->app->make(GameSystemRepositoryInterface::class)->getActiveWithPublishedTableCount(),
        );
    }

    public function test_only_listed_campaigns_count(): void
    {
        $system = $this->system('Sistema de ejemplo');
        $this->campaign($system, 'recruiting');
        $this->campaign($system, 'cancelled');
        $this->campaign($system, 'recruiting', published: false);
        $this->campaign($this->system('Sistema sin campañas listadas'), 'cancelled');

        $this->assertSame(
            [['id' => $system->id, 'name' => 'Sistema de ejemplo', 'count' => 1]],
            $this->app->make(GameSystemRepositoryInterface::class)->getActiveWithPublishedCampaignCount(),
        );
    }

    private function system(string $name): GameSystemModel
    {
        return GameSystemModel::create(['id' => (string) Str::uuid(), 'name' => $name, 'slug' => Str::random(10), 'is_active' => true]);
    }

    private function table(GameSystemModel $system, string $status, bool $published = true): void
    {
        GameTableModel::create([
            'id' => (string) Str::uuid(),
            'game_system_id' => $system->id,
            'created_by' => UserModel::factory()->create()->id,
            'title' => 'Mesa de ejemplo',
            'slug' => 'mesa-'.Str::lower(Str::random(8)),
            'starts_at' => now()->addWeek(),
            'duration_minutes' => 240,
            'table_type' => 'one_shot',
            'table_format' => 'online',
            'status' => $status,
            'min_players' => 1,
            'max_players' => 5,
            'max_spectators' => 2,
            'language' => 'es',
            'registration_type' => 'everyone',
            'is_published' => $published,
        ]);
    }

    private function campaign(GameSystemModel $system, string $status, bool $published = true): void
    {
        CampaignModel::create([
            'id' => (string) Str::uuid(),
            'game_system_id' => $system->id,
            'created_by' => UserModel::factory()->create()->id,
            'title' => 'Campaña de ejemplo',
            'slug' => 'campana-'.Str::lower(Str::random(8)),
            'status' => $status,
            'is_published' => $published,
        ]);
    }
}
