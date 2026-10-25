<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Feature\Http\Controllers;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\GameTables\Domain\Enums\CampaignStatus;
use Modules\GameTables\Domain\Enums\TableFormat;
use Modules\GameTables\Domain\Enums\TableStatus;
use Modules\GameTables\Domain\Enums\TableType;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\CampaignModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameSystemModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameTableModel;
use Tests\Support\Modules\ModuleTestCase;

final class GameTablesSeoTest extends ModuleTestCase
{
    protected ?string $moduleName = 'game-tables';

    protected bool $autoEnableModule = true;

    private UserModel $creator;

    private GameSystemModel $system;

    protected function setUp(): void
    {
        parent::setUp();
        app()->setLocale('es');
        config(['app.name' => 'Example guild', 'cloudinary.cloud_name' => 'demo', 'cloudinary.prefix' => 'guildforge']);
        Storage::fake('images');

        $this->creator = UserModel::create([
            'id' => (string) Str::uuid(),
            'name' => 'Table Creator',
            'email' => 'creator@example.com',
            'password' => 'password',
        ]);
        $this->system = GameSystemModel::create([
            'id' => (string) Str::uuid(),
            'name' => 'SEO Fixture System',
            'slug' => 'seo-fixture-system',
            'game_master_title' => 'Game Master',
            'is_active' => true,
        ]);
    }

    public function test_table_page_has_its_schedule_image_and_event_data(): void
    {
        GameTableModel::create([
            'id' => (string) Str::uuid(),
            'game_system_id' => $this->system->id,
            'created_by' => $this->creator->id,
            'title' => 'La cripta olvidada',
            'slug' => 'la-cripta-olvidada',
            'synopsis' => '<p>Una aventura corta</p>',
            'image_public_id' => 'gametables/cripta.jpg',
            'location' => 'Local de la asociación',
            // app.timezone is Europe/Madrid: venue local time
            'starts_at' => '2026-05-09 17:00:00',
            'duration_minutes' => 240,
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

        $response = $this->get('/mesas/la-cripta-olvidada');

        $response->assertOk();
        $response->assertSee('<title>La cripta olvidada - Example guild</title>', false);
        $response->assertSee('content="9 de mayo de 2026, 17:00 · Local de la asociación · Una aventura corta"', false);
        $response->assertSee('/guildforge/gametables/cripta" data-seo-server>', false);
        $response->assertSee('"endDate":"2026-05-09T21:00:00+02:00"', false);
    }

    public function test_campaign_page_has_its_metadata_without_json_ld(): void
    {
        CampaignModel::create([
            'id' => (string) Str::uuid(),
            'game_system_id' => $this->system->id,
            'created_by' => $this->creator->id,
            'title' => 'La marca del este',
            'slug' => 'la-marca-del-este',
            'description' => 'Campaña de exploración',
            'image_public_id' => 'campaigns/marca.jpg',
            'status' => CampaignStatus::Active,
            'max_players' => 5,
            'is_published' => true,
            'published_at' => now(),
        ]);

        $response = $this->get('/campanas/la-marca-del-este');

        $response->assertSee('<title>La marca del este - Example guild</title>', false);
        $response->assertSee('<meta name="description" content="Campaña de exploración" data-seo-server>', false);
        $response->assertDontSee('application/ld+json', false);
    }

    public function test_section_pages_have_their_titles(): void
    {
        $this->get('/mesas')->assertSee('<title>Mesas de rol - Example guild</title>', false);
        $this->get('/mesas/calendario')->assertSee('<title>Calendario de mesas - Example guild</title>', false);
        $this->get('/campanas')->assertSee('<title>Campañas - Example guild</title>', false);
    }

    public function test_my_tables_is_noindex(): void
    {
        $this->actingAs($this->creator)
            ->get('/mesas/mis-mesas')
            ->assertSee('<meta name="robots" content="noindex, nofollow" data-seo-server>', false);
    }
}
