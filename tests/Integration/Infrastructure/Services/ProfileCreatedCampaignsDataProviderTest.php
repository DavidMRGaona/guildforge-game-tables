<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Integration\Infrastructure\Services;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\CampaignJoinRequestModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\CampaignModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameSystemModel;
use Modules\GameTables\Infrastructure\Services\ProfileCreatedCampaignsDataProvider;
use Tests\Support\Modules\ModuleTestCase;

final class ProfileCreatedCampaignsDataProviderTest extends ModuleTestCase
{
    protected ?string $moduleName = 'game-tables';

    protected bool $autoEnableModule = true;

    public function test_created_campaigns_show_how_many_players_are_in_the_roster(): void
    {
        $creator = $this->user();
        $campaign = $this->campaign($creator);
        $this->joinRoster($campaign, $this->user());
        $this->joinRoster($campaign, $this->user());
        $this->joinRoster($campaign, $this->user(anonymized: true));

        $data = app(ProfileCreatedCampaignsDataProvider::class)->getDataForUser((string) $creator->id);

        // Same count as «Mis campañas»: roster members whose account was not anonymized
        $this->assertSame(2, $data['campaigns'][0]['currentPlayers']);
        $this->assertSame(4, $data['campaigns'][0]['maxPlayers']);
    }

    public function test_a_campaign_without_roster_shows_no_players(): void
    {
        $creator = $this->user();
        $this->campaign($creator);

        $data = app(ProfileCreatedCampaignsDataProvider::class)->getDataForUser((string) $creator->id);

        $this->assertSame(0, $data['campaigns'][0]['currentPlayers']);
    }

    public function test_an_unpublished_campaign_shows_its_review_state_and_is_editable_only_as_a_draft(): void
    {
        $creator = $this->user();
        $this->campaign($creator, ['is_published' => false, 'frontend_creation_status' => 'pending_review', 'title' => 'En revisión']);
        $this->campaign($creator, ['is_published' => false, 'frontend_creation_status' => 'draft', 'title' => 'Borrador']);

        $drafts = collect(app(ProfileCreatedCampaignsDataProvider::class)->getDataForUser((string) $creator->id)['drafts'])->keyBy('title');

        // As «Mis campañas»: a campaign sent to review is waiting for the moderators, not editable
        $this->assertSame('pending_review', $drafts['En revisión']['status']);
        $this->assertSame(__('game-tables::messages.enums.frontend_creation_status.pending_review'), $drafts['En revisión']['statusLabel']);
        $this->assertFalse($drafts['En revisión']['canEdit']);
        $this->assertSame('draft', $drafts['Borrador']['status']);
        $this->assertTrue($drafts['Borrador']['canEdit']);
    }

    public function test_a_rejected_draft_shows_the_moderators_reason_while_the_author_may_change_it(): void
    {
        $creator = $this->user();
        $this->campaign($creator, ['is_published' => false, 'frontend_creation_status' => 'draft', 'title' => 'Rechazada', 'moderation_notes' => "Falta la sinopsis.\nRevisa el sistema."]);
        $this->campaign($creator, ['is_published' => false, 'frontend_creation_status' => 'pending_review', 'title' => 'En revisión', 'moderation_notes' => 'Nota antigua']);
        $this->campaign($creator, ['is_published' => false, 'frontend_creation_status' => 'draft', 'title' => 'Borrador']);

        $drafts = collect(app(ProfileCreatedCampaignsDataProvider::class)->getDataForUser((string) $creator->id)['drafts'])->keyBy('title');

        // As «Mis campañas»: the reason only while the draft is back in the author's hands
        $this->assertSame("Falta la sinopsis.\nRevisa el sistema.", $drafts['Rechazada']['rejectionReason']);
        $this->assertNull($drafts['En revisión']['rejectionReason']);
        $this->assertNull($drafts['Borrador']['rejectionReason']);
    }

    public function test_a_published_campaign_the_author_directs_links_to_its_roster_with_the_pending_requests(): void
    {
        $creator = $this->user();
        $directed = $this->campaign($creator, ['frontend_creation_status' => 'approved', 'title' => 'Dirigida']);
        $this->joinRequest($directed, $this->user(), 'pending');
        $this->joinRequest($directed, $this->user(), 'pending');
        $this->joinRequest($directed, $this->user(), 'rejected');
        $this->joinRequest($directed, $this->user(anonymized: true), 'pending');
        // Created in the panel: its creator does not direct it (CampaignDirection)
        $this->campaign($creator, ['frontend_creation_status' => null, 'title' => 'Del panel']);
        $this->campaign($creator, ['is_published' => false, 'frontend_creation_status' => 'draft', 'title' => 'Borrador']);

        $data = app(ProfileCreatedCampaignsDataProvider::class)->getDataForUser((string) $creator->id);
        $published = collect($data['campaigns'])->keyBy('title');

        // As «Mis campañas»: pending requests of accounts that were not anonymized
        $this->assertSame(route('campaigns.roster', $directed->id), $published['Dirigida']['rosterUrl']);
        $this->assertSame(2, $published['Dirigida']['pendingRequests']);
        $this->assertNull($published['Del panel']['rosterUrl']);
        $this->assertSame(0, $published['Del panel']['pendingRequests']);
        // The roster belongs to published campaigns, not to the drafts list
        $this->assertNull($data['drafts'][0]['rosterUrl']);
        $this->assertSame(0, $data['drafts'][0]['pendingRequests']);
    }

    public function test_a_panel_campaign_the_creator_directs_as_game_master_links_to_its_roster(): void
    {
        $creator = $this->user();
        $campaign = $this->campaign($creator, ['frontend_creation_status' => null, 'title' => 'Del panel']);
        $gameMasterId = (string) Str::uuid();
        DB::table('gametables_game_masters')->insert([
            'id' => $gameMasterId,
            'user_id' => $creator->id,
            'role' => 'main',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('gametables_campaign_gm')->insert([
            'campaign_id' => $campaign->id,
            'game_master_id' => $gameMasterId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $data = app(ProfileCreatedCampaignsDataProvider::class)->getDataForUser((string) $creator->id);

        $this->assertSame(route('campaigns.roster', $campaign->id), $data['campaigns'][0]['rosterUrl']);
    }

    private function joinRequest(CampaignModel $campaign, UserModel $user, string $status): void
    {
        CampaignJoinRequestModel::create([
            'id' => (string) Str::uuid(),
            'campaign_id' => $campaign->id,
            'user_id' => $user->id,
            'status' => $status,
            'message' => null,
        ]);
    }

    private function joinRoster(CampaignModel $campaign, UserModel $user): void
    {
        DB::table('gametables_campaign_players')->insert([
            'campaign_id' => $campaign->id,
            'user_id' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function user(bool $anonymized = false): UserModel
    {
        return UserModel::create([
            'id' => (string) Str::uuid(),
            'name' => 'Roster User',
            'email' => 'roster-'.bin2hex(random_bytes(4)).'@example.com',
            'password' => 'password',
            'anonymized_at' => $anonymized ? now() : null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function campaign(UserModel $creator, array $overrides = []): CampaignModel
    {
        $system = GameSystemModel::create([
            'id' => (string) Str::uuid(),
            'name' => 'Fixture System',
            'slug' => 'fixture-system-'.bin2hex(random_bytes(4)),
            'game_master_title' => 'Game Master',
            'is_active' => true,
        ]);

        return CampaignModel::create([
            'id' => (string) Str::uuid(),
            'game_system_id' => $system->id,
            'created_by' => $creator->id,
            'title' => 'La cripta olvidada',
            'slug' => 'la-cripta-olvidada-'.bin2hex(random_bytes(4)),
            'status' => 'active',
            'max_players' => 4,
            'is_published' => true,
            ...$overrides,
        ]);
    }
}
