<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Feature\Http\Controllers;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\GameTables\Domain\Enums\CampaignStatus;
use Modules\GameTables\Domain\Enums\GameMasterRole;
use Modules\GameTables\Domain\Enums\TableFormat;
use Modules\GameTables\Domain\Enums\TableStatus;
use Modules\GameTables\Domain\Enums\TableType;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\CampaignModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameMasterModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameSystemModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameTableModel;
use Tests\Support\Modules\ModuleTestCase;

/**
 * The campaign listing builds its response DTO without the fields that only make
 * sense for a creator's own view, so those have to be optional.
 */
final class CampaignIndexTest extends ModuleTestCase
{
    protected ?string $moduleName = 'game-tables';

    protected bool $autoEnableModule = true;

    private UserModel $creator;

    private GameSystemModel $system;

    protected function setUp(): void
    {
        parent::setUp();

        $this->creator = UserModel::create([
            'id' => (string) Str::uuid(),
            'name' => 'Campaign Creator',
            'email' => 'campaign-creator@example.com',
            'password' => 'password',
        ]);

        $this->system = GameSystemModel::create([
            'id' => (string) Str::uuid(),
            'name' => 'Campaign Fixture System',
            'slug' => 'campaign-fixture-system',
            'game_master_title' => 'Game Master',
            'is_active' => true,
        ]);
    }

    public function test_the_listing_renders_a_published_campaign(): void
    {
        $this->createCampaign('Campaña publicada', 'campana-publicada');

        $response = $this->get('/campanas');

        $response->assertStatus(200);
        $response->assertInertia(
            fn (Assert $page) => $page
                ->has('campaigns.data', 1)
                ->where('campaigns.data.0.title', 'Campaña publicada')
        );
    }

    public function test_the_listing_renders_when_there_are_no_campaigns(): void
    {
        $this->get('/campanas')->assertStatus(200);
    }

    public function test_the_listing_renders_a_campaign_with_a_game_master(): void
    {
        // Game masters are serialised through their own resource, a path an empty
        // campaign never exercises.
        $campaign = $this->createCampaign('Campaña con GM', 'campana-con-gm');
        $campaign->gameMasters()->attach($this->createGameMaster()->id, ['sort_order' => 1]);

        $response = $this->get('/campanas');

        $response->assertStatus(200);
        $response->assertInertia(
            fn (Assert $page) => $page
                ->has('campaigns.data', 1)
                ->has('campaigns.data.0.gameMasters', 1)
                ->where('campaigns.data.0.gameMasters.0.displayName', 'Ana Narradora')
        );
    }

    public function test_the_listing_offers_only_systems_with_published_campaigns(): void
    {
        // The game system chips must lead to campaigns, not to tables
        $this->createCampaign('Campaña visible', 'campana-visible');

        $tablesOnly = $this->createGameSystem('Tables Only System', 'tables-only-system');
        $this->createGameTable($tablesOnly);

        $draftOnly = $this->createGameSystem('Draft Campaign System', 'draft-campaign-system');
        $this->createCampaign('Campaña sin publicar', 'campana-sin-publicar', $draftOnly, published: false);

        $cancelledOnly = $this->createGameSystem('Cancelled Campaign System', 'cancelled-campaign-system');
        $this->createCampaign('Campaña cancelada', 'campana-cancelada', $cancelledOnly, CampaignStatus::Cancelled);

        $response = $this->get('/campanas');

        $response->assertStatus(200);
        $systems = $response->viewData('page')['props']['gameSystems'];
        $this->assertSame(
            [['id' => $this->system->id, 'name' => 'Campaign Fixture System', 'count' => 1]],
            $systems,
        );
    }

    public function test_the_systems_filter_narrows_the_listing(): void
    {
        $this->createCampaign('Campaña del sistema', 'campana-del-sistema');
        $other = $this->createGameSystem('Other Campaign System', 'other-campaign-system');
        $this->createCampaign('Campaña de otro sistema', 'campana-de-otro-sistema', $other);

        $response = $this->get("/campanas?systems[]={$other->id}");

        $response->assertInertia(
            fn (Assert $page) => $page
                ->has('campaigns.data', 1)
                ->where('campaigns.data.0.title', 'Campaña de otro sistema')
                ->where('currentFilters.systems', [$other->id])
        );
    }

    public function test_the_status_filter_narrows_the_listing(): void
    {
        $this->createCampaign('Campaña activa', 'campana-activa');
        $this->createCampaign('Campaña reclutando', 'campana-reclutando', status: CampaignStatus::Recruiting);

        $response = $this->get('/campanas?status=recruiting');

        $response->assertInertia(
            fn (Assert $page) => $page
                ->has('campaigns.data', 1)
                ->where('campaigns.data.0.title', 'Campaña reclutando')
                ->where('currentFilters.status', 'recruiting')
        );
    }

    private function createGameSystem(string $name, string $slug): GameSystemModel
    {
        return GameSystemModel::create([
            'id' => (string) Str::uuid(),
            'name' => $name,
            'slug' => $slug,
            'game_master_title' => 'Game Master',
            'is_active' => true,
        ]);
    }

    private function createGameTable(GameSystemModel $system): GameTableModel
    {
        return GameTableModel::create([
            'id' => (string) Str::uuid(),
            'game_system_id' => $system->id,
            'created_by' => $this->creator->id,
            'title' => 'Mesa suelta',
            'slug' => 'mesa-suelta',
            'starts_at' => now()->addWeek(),
            'duration_minutes' => 180,
            'table_type' => TableType::OneShot,
            'table_format' => TableFormat::InPerson,
            'status' => TableStatus::Scheduled,
            'min_players' => 2,
            'max_players' => 6,
            'language' => 'es',
            'auto_confirm' => true,
            'is_published' => true,
            'published_at' => now(),
        ]);
    }

    private function createGameMaster(): GameMasterModel
    {
        return GameMasterModel::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Ana',
            'last_name' => 'Narradora',
            'email' => 'ana@example.com',
            'role' => GameMasterRole::Main,
            'is_name_public' => true,
        ]);
    }

    private function createCampaign(
        string $title,
        string $slug,
        ?GameSystemModel $system = null,
        CampaignStatus $status = CampaignStatus::Active,
        bool $published = true,
    ): CampaignModel {
        return CampaignModel::create([
            'id' => (string) Str::uuid(),
            'game_system_id' => ($system ?? $this->system)->id,
            'created_by' => $this->creator->id,
            'title' => $title,
            'slug' => $slug,
            'status' => $status,
            'max_players' => 5,
            'is_published' => $published,
            'published_at' => $published ? now() : null,
        ]);
    }
}
