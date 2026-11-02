<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Integration\Persistence;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Support\Str;
use Modules\GameTables\Application\Services\CampaignQueryServiceInterface;
use Modules\GameTables\Application\Services\GameTableQueryServiceInterface;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\CampaignModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameSystemModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameTableModel;
use Tests\Support\Modules\ModuleTestCase;

/**
 * The rule of the public listings (published, neither draft nor cancelled), shared by the listings and
 * the site search: both must keep showing the same tables and campaigns.
 */
final class PubliclyListedScopeTest extends ModuleTestCase
{
    protected ?string $moduleName = 'game-tables';

    protected bool $autoEnableModule = true;

    private GameSystemModel $system;

    protected function setUp(): void
    {
        parent::setUp();

        $this->system = GameSystemModel::create(['id' => (string) Str::uuid(), 'name' => 'Sistema de ejemplo', 'slug' => Str::random(10), 'is_active' => true]);
    }

    public function test_listed_tables_are_published_and_neither_draft_nor_cancelled(): void
    {
        $listed = [
            $this->table(['status' => 'scheduled', 'title' => 'Programada']),
            $this->table(['status' => 'full', 'title' => 'Completa']),
            $this->table(['status' => 'in_progress', 'title' => 'En curso']),
            $this->table(['status' => 'completed', 'title' => 'Terminada']),
            $this->table(['status' => 'scheduled', 'title' => 'Aprobada', 'frontend_creation_status' => 'approved']),
        ];
        $this->hiddenTables();

        $this->assertEqualsCanonicalizing(
            array_map(static fn (GameTableModel $table): string => $table->id, $listed),
            GameTableModel::query()->publiclyListed()->pluck('id')->all(),
        );
    }

    public function test_the_table_listing_follows_the_rule_and_keeps_its_date_cut(): void
    {
        $upcoming = $this->table(['status' => 'scheduled', 'title' => 'Próxima']);
        $this->table(['status' => 'completed', 'title' => 'Pasada', 'starts_at' => now()->subWeek()]);
        $this->hiddenTables();

        $listing = $this->app->make(GameTableQueryServiceInterface::class);

        $this->assertSame([$upcoming->id], array_map(static fn ($table): string => $table->id, $listing->getPublishedTablesPaginated(1, 50)));
        $this->assertSame(1, $listing->getPublishedTablesTotal());
    }

    public function test_listed_campaigns_are_published_and_not_cancelled(): void
    {
        $listed = [
            $this->campaign(['status' => 'recruiting']),
            $this->campaign(['status' => 'active']),
            $this->campaign(['status' => 'on_hold']),
            $this->campaign(['status' => 'completed']),
        ];
        $this->hiddenCampaigns();

        $this->assertEqualsCanonicalizing(
            array_map(static fn (CampaignModel $campaign): string => $campaign->id, $listed),
            CampaignModel::query()->publiclyListed()->pluck('id')->all(),
        );
    }

    public function test_the_campaign_listing_follows_the_rule(): void
    {
        $listed = [$this->campaign(['status' => 'recruiting']), $this->campaign(['status' => 'completed'])];
        $this->hiddenCampaigns();

        $listing = $this->app->make(CampaignQueryServiceInterface::class);

        $this->assertEqualsCanonicalizing(
            array_map(static fn (CampaignModel $campaign): string => $campaign->id, $listed),
            array_map(static fn ($campaign): string => $campaign->id, $listing->getPublishedCampaignsPaginated(1, 50)),
        );
        $this->assertSame(2, $listing->getPublishedCampaignsTotal());
    }

    private function hiddenTables(): void
    {
        $this->table(['status' => 'draft', 'title' => 'Borrador']);
        $this->table(['status' => 'cancelled', 'title' => 'Cancelada']);
        $this->table(['status' => 'scheduled', 'title' => 'Sin publicar', 'is_published' => false]);
        $this->table(['status' => 'draft', 'title' => 'Pendiente', 'is_published' => false, 'frontend_creation_status' => 'pending_review']);
        $this->table(['status' => 'draft', 'title' => 'Rechazada', 'is_published' => false, 'frontend_creation_status' => 'rejected']);
    }

    private function hiddenCampaigns(): void
    {
        $this->campaign(['status' => 'cancelled']);
        $this->campaign(['status' => 'recruiting', 'is_published' => false]);
        $this->campaign(['status' => 'recruiting', 'is_published' => false, 'frontend_creation_status' => 'pending_review']);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function table(array $attributes = []): GameTableModel
    {
        return GameTableModel::create(array_merge([
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
        ], $attributes));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function campaign(array $attributes = []): CampaignModel
    {
        return CampaignModel::create(array_merge([
            'id' => (string) Str::uuid(),
            'game_system_id' => $this->system->id,
            'created_by' => UserModel::factory()->create()->id,
            'title' => 'Campaña de ejemplo',
            'slug' => 'campana-'.Str::lower(Str::random(8)),
            'status' => 'recruiting',
            'is_published' => true,
        ], $attributes));
    }
}
