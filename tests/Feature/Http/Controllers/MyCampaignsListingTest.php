<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Feature\Http\Controllers;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\GameTables\Domain\Enums\FrontendCreationStatus;
use Modules\GameTables\Domain\Enums\GameMasterRole;
use Modules\GameTables\Domain\Repositories\CampaignRosterRepositoryInterface;
use Modules\GameTables\Domain\ValueObjects\CampaignId;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\CampaignModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameMasterModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameSystemModel;
use Tests\Support\Modules\ModuleTestCase;

/**
 * «Mis campañas» lists the person's own drafts plus every approved campaign they direct,
 * with the real roster counts; the public pages count the roster without anonymized accounts.
 */
final class MyCampaignsListingTest extends ModuleTestCase
{
    protected ?string $moduleName = 'game-tables';

    protected bool $autoEnableModule = true;

    private UserModel $jane;

    private GameSystemModel $gameSystem;

    private int $members = 0;

    protected function setUp(): void
    {
        parent::setUp();

        app('router')->getRoutes()->refreshNameLookups();
        config()->set('modules.settings.game-tables', [
            'frontend_creation' => [
                'enabled' => true,
                'access_level' => 'permission',
                'required_permission' => 'gametables.create_from_web',
                'allowed_content' => 'both',
                'publication' => ['mode' => 'approval'],
            ],
        ]);

        $this->jane = UserModel::factory()->create(['name' => 'Jane Doe', 'email' => 'jane@example.com']);
        $this->gameSystem = GameSystemModel::create([
            'id' => (string) Str::uuid(),
            'name' => 'Sistema de prueba',
            'slug' => 'sistema-de-prueba',
            'is_active' => true,
        ]);
    }

    public function test_an_approved_web_campaign_stays_listed_with_its_counts(): void
    {
        $campaign = $this->campaign('Campaña aprobada', $this->jane, FrontendCreationStatus::Approved);
        $this->addMember($campaign);
        $this->addMember($campaign);
        $this->pendingRequest($campaign);

        $this->actingAs($this->jane)
            ->get(route('campaigns.my-campaigns'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('campaigns', 1)
                ->where('campaigns.0.title', 'Campaña aprobada')
                ->where('campaigns.0.current_players', 2)
                ->where('campaigns.0.pending_requests', 1)
                ->where('campaigns.0.directed_by_viewer', true)
                ->where('campaigns.0.can_add_session', true)
                ->where('campaigns.0.can_edit', false)
                ->where('campaigns.0.roster_access', 'anyone')
                ->where('campaigns.0.roster_access_label', 'Cualquier cuenta'));
    }

    public function test_a_draft_of_the_user_is_still_listed_and_editable(): void
    {
        $this->campaign('Borrador', $this->jane, FrontendCreationStatus::Draft, isPublished: false);

        $this->actingAs($this->jane)
            ->get(route('campaigns.my-campaigns'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('campaigns', 1)
                ->where('campaigns.0.can_edit', true)
                ->where('campaigns.0.directed_by_viewer', false)
                ->where('campaigns.0.can_add_session', false));
    }

    public function test_a_panel_campaign_where_the_user_is_game_master_is_listed(): void
    {
        $campaign = $this->campaign('Campaña del panel', UserModel::factory()->create(['name' => 'Mary Major']), null);
        $this->makeGameMaster($campaign, $this->jane);

        $this->actingAs($this->jane)
            ->get(route('campaigns.my-campaigns'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('campaigns', 1)
                ->where('campaigns.0.title', 'Campaña del panel')
                ->where('campaigns.0.directed_by_viewer', true)
                ->where('campaigns.0.can_edit', false));
    }

    public function test_a_panel_campaign_created_by_the_user_without_being_game_master_is_not_listed(): void
    {
        $this->campaign('Campaña ajena al GM', $this->jane, null);

        $this->actingAs($this->jane)
            ->get(route('campaigns.my-campaigns'))
            ->assertInertia(fn (Assert $page) => $page->has('campaigns', 0));
    }

    public function test_another_persons_draft_is_not_listed(): void
    {
        $this->campaign('Borrador ajeno', UserModel::factory()->create(['name' => 'Mary Major']), FrontendCreationStatus::Draft, isPublished: false);

        $this->actingAs($this->jane)
            ->get(route('campaigns.my-campaigns'))
            ->assertInertia(fn (Assert $page) => $page->has('campaigns', 0));
    }

    public function test_a_cancelled_directed_campaign_is_listed_without_adding_sessions(): void
    {
        $campaign = $this->campaign('Campaña cancelada', $this->jane, FrontendCreationStatus::Approved, status: 'cancelled');
        $this->addMember($campaign);

        $this->actingAs($this->jane)
            ->get(route('campaigns.my-campaigns'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('campaigns', 1)
                ->where('campaigns.0.current_players', 1)
                ->where('campaigns.0.can_add_session', false));
    }

    public function test_a_campaign_directed_twice_is_listed_once(): void
    {
        $campaign = $this->campaign('Campaña doble', $this->jane, FrontendCreationStatus::Approved);
        $this->makeGameMaster($campaign, $this->jane);

        $this->actingAs($this->jane)
            ->get(route('campaigns.my-campaigns'))
            ->assertInertia(fn (Assert $page) => $page->has('campaigns', 1));
    }

    public function test_adding_sessions_is_off_when_web_creation_is_disabled(): void
    {
        config()->set('modules.settings.game-tables.frontend_creation.enabled', false);
        $this->campaign('Campaña aprobada', $this->jane, FrontendCreationStatus::Approved);

        $this->actingAs($this->jane)
            ->get(route('campaigns.my-campaigns'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('campaigns', 1)
                ->where('campaigns.0.can_add_session', false));
    }

    public function test_the_public_pages_count_the_roster_without_anonymized_members(): void
    {
        $campaign = $this->campaign('Campaña pública', UserModel::factory()->create(['name' => 'Mary Major']), null);
        $this->addMember($campaign);
        $this->addMember($campaign);
        $gone = $this->addMember($campaign);
        DB::table('users')->where('id', $gone->id)->update(['anonymized_at' => CarbonImmutable::now()]);

        $this->get('/campanas')
            ->assertInertia(fn (Assert $page) => $page
                ->where('campaigns.data.0.currentPlayers', 2)
                ->where('campaigns.data.0.spotsAvailable', 3));

        $this->get('/campanas/'.$campaign->slug)
            ->assertInertia(fn (Assert $page) => $page
                ->where('campaign.currentPlayers', 2)
                ->where('campaign.spotsAvailable', 3));
    }

    private function addMember(CampaignModel $campaign): UserModel
    {
        $this->members++;
        $member = UserModel::factory()->create([
            'name' => 'John Doe',
            'email' => 'member'.$this->members.'@example.com',
        ]);
        app(CampaignRosterRepositoryInterface::class)->add(new CampaignId($campaign->id), $member->id, CarbonImmutable::now());

        return $member;
    }

    private function pendingRequest(CampaignModel $campaign): void
    {
        $this->members++;
        $requester = UserModel::factory()->create([
            'name' => 'Richard Roe',
            'email' => 'requester'.$this->members.'@example.com',
        ]);
        $now = CarbonImmutable::now();

        DB::table('gametables_campaign_join_requests')->insert([
            'id' => (string) Str::uuid(),
            'campaign_id' => $campaign->id,
            'user_id' => $requester->id,
            'status' => 'pending',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function makeGameMaster(CampaignModel $campaign, UserModel $user): void
    {
        $gm = GameMasterModel::create([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'role' => GameMasterRole::Main,
            'notify_by_email' => true,
            'is_name_public' => true,
        ]);
        $campaign->gameMasters()->attach($gm->id, ['sort_order' => 1]);
    }

    private function campaign(
        string $title,
        UserModel $creator,
        ?FrontendCreationStatus $creationStatus,
        bool $isPublished = true,
        string $status = 'recruiting',
    ): CampaignModel {
        return CampaignModel::create([
            'id' => (string) Str::uuid(),
            'game_system_id' => $this->gameSystem->id,
            'created_by' => $creator->id,
            'title' => $title,
            'slug' => Str::slug($title).'-'.Str::lower(Str::random(6)),
            'frequency' => 'weekly',
            'status' => $status,
            'max_players' => 5,
            'is_published' => $isPublished,
            'frontend_creation_status' => $creationStatus,
        ]);
    }
}
