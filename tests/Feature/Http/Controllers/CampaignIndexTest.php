<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Feature\Http\Controllers;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\GameTables\Domain\Enums\CampaignStatus;
use Modules\GameTables\Domain\Enums\GameMasterRole;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\CampaignModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameMasterModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameSystemModel;
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

    private function createCampaign(string $title, string $slug): CampaignModel
    {
        return CampaignModel::create([
            'id' => (string) Str::uuid(),
            'game_system_id' => $this->system->id,
            'created_by' => $this->creator->id,
            'title' => $title,
            'slug' => $slug,
            'status' => CampaignStatus::Active,
            'max_players' => 5,
            'is_published' => true,
            'published_at' => now(),
        ]);
    }
}
