<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Integration\Infrastructure\Services;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
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

    private function campaign(UserModel $creator): CampaignModel
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
        ]);
    }
}
