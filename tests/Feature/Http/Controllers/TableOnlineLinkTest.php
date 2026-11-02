<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Feature\Http\Controllers;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Support\Str;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\CampaignModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameSystemModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameTableModel;
use Tests\Support\Modules\ModuleTestCase;

/**
 * The link of a table's online session never reaches the public pages: the table page, the list of
 * tables and the sessions listed on a campaign page.
 */
final class TableOnlineLinkTest extends ModuleTestCase
{
    private const string LINK = 'https://example.org/private-room-4821';

    protected ?string $moduleName = 'game-tables';

    protected bool $autoEnableModule = true;

    private GameSystemModel $gameSystem;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gameSystem = GameSystemModel::create([
            'id' => (string) Str::uuid(),
            'name' => 'Sistema de prueba',
            'slug' => 'sistema-de-prueba',
            'is_active' => true,
        ]);
    }

    public function test_the_table_page_does_not_send_the_link(): void
    {
        $table = $this->onlineTable('mesa-online');

        $this->get('/mesas/'.$table->slug)->assertOk()->assertDontSee('private-room-4821');
        $this->actingAs(UserModel::factory()->create())->get('/mesas/'.$table->slug)->assertOk()->assertDontSee('private-room-4821');
    }

    public function test_the_list_of_tables_does_not_send_the_link(): void
    {
        $this->onlineTable('mesa-online');

        $this->get('/mesas')->assertOk()->assertSee('Mesa online')->assertDontSee('private-room-4821');
    }

    public function test_the_sessions_on_a_campaign_page_do_not_send_the_link(): void
    {
        $campaign = CampaignModel::create([
            'id' => (string) Str::uuid(),
            'game_system_id' => $this->gameSystem->id,
            'created_by' => UserModel::factory()->create()->id,
            'title' => 'Campaña online',
            'slug' => 'campana-online',
            'frequency' => 'weekly',
            'status' => 'recruiting',
            'max_players' => 5,
            'is_published' => true,
        ]);
        $this->onlineTable('sesion-online', $campaign->id);

        $this->get('/campanas/'.$campaign->slug)->assertOk()->assertSee('Mesa online')->assertDontSee('private-room-4821');
    }

    private function onlineTable(string $slug, ?string $campaignId = null): GameTableModel
    {
        return GameTableModel::create([
            'id' => (string) Str::uuid(),
            'game_system_id' => $this->gameSystem->id,
            'campaign_id' => $campaignId,
            'created_by' => UserModel::factory()->create()->id,
            'title' => 'Mesa online',
            'slug' => $slug,
            'starts_at' => now()->addWeek(),
            'duration_minutes' => 240,
            'table_type' => $campaignId !== null ? 'campaign_session' : 'one_shot',
            'table_format' => 'online',
            'online_url' => self::LINK,
            'status' => 'scheduled',
            'min_players' => 1,
            'max_players' => 5,
            'language' => 'es',
            'experience_level' => 'none',
            'character_creation' => 'pre_generated',
            'registration_type' => 'everyone',
            'auto_confirm' => true,
            'is_published' => true,
            'published_at' => now(),
        ]);
    }
}
