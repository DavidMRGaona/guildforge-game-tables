<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Feature\Calendar;

use App\Application\Calendar\Services\CalendarSourceRegistryInterface;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\GameTables\Domain\Enums\TableFormat;
use Modules\GameTables\Domain\Enums\TableStatus;
use Modules\GameTables\Domain\Enums\TableType;
use Modules\GameTables\Infrastructure\Calendar\GameTableCalendarSource;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameSystemModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameTableModel;
use Tests\Support\Modules\ModuleTestCase;

/**
 * A table the public calendar shows can be added to a personal calendar: as an
 * iCalendar download through the core and with links on its page.
 */
final class AddToCalendarTest extends ModuleTestCase
{
    protected ?string $moduleName = 'game-tables';

    protected bool $autoEnableModule = true;

    private GameSystemModel $gameSystem;

    private UserModel $creator;

    protected function setUp(): void
    {
        parent::setUp();

        app(CalendarSourceRegistryInterface::class)->register(GameTableCalendarSource::class);

        $this->creator = UserModel::create([
            'id' => (string) Str::uuid(),
            'name' => 'Table Creator',
            'email' => 'creator@example.com',
            'password' => 'password',
        ]);

        $this->gameSystem = GameSystemModel::create([
            'id' => (string) Str::uuid(),
            'name' => 'Dungeons & Dragons',
            'slug' => 'dnd',
            'game_master_title' => 'Dungeon Master',
            'is_active' => true,
        ]);
    }

    public function test_a_listed_table_downloads_as_icalendar(): void
    {
        $table = $this->createGameTable([
            'title' => 'La cripta perdida',
            'slug' => 'la-cripta-perdida',
            'starts_at' => '2026-12-05 17:00:00',
            'duration_minutes' => 240,
            'location' => 'Sala 2',
        ]);

        $response = $this->get("/calendario/game-table/{$table->id}.ics");

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/calendar; charset=utf-8');
        $this->assertStringContainsString("\r\nSUMMARY:La cripta perdida\r\n", $response->content());
        $this->assertStringContainsString("\r\nLOCATION:Sala 2\r\n", $response->content());
        $this->assertStringContainsString("\r\nURL:".url('/mesas/la-cripta-perdida')."\r\n", $response->content());
        $this->assertMatchesRegularExpression('/\r\nDTEND:\d{8}T\d{6}Z\r\n/', $response->content());
    }

    public function test_a_draft_table_is_not_found(): void
    {
        $table = $this->createGameTable(['status' => TableStatus::Draft, 'is_published' => false]);

        $this->get("/calendario/game-table/{$table->id}.ics")->assertNotFound();
    }

    public function test_an_unpublished_table_is_not_found(): void
    {
        $table = $this->createGameTable(['is_published' => false]);

        $this->get("/calendario/game-table/{$table->id}.ics")->assertNotFound();
    }

    public function test_a_cancelled_table_is_not_found(): void
    {
        $table = $this->createGameTable(['status' => TableStatus::Cancelled]);

        $this->get("/calendario/game-table/{$table->id}.ics")->assertNotFound();
    }

    public function test_an_id_that_is_not_a_uuid_is_not_found(): void
    {
        $this->get('/calendario/game-table/not-a-uuid.ics')->assertNotFound();
    }

    public function test_calendar_entries_of_tables_carry_the_download_link(): void
    {
        $table = $this->createGameTable(['starts_at' => '2026-09-15 10:00:00']);

        $response = $this->getJson('/eventos/calendario?start=2026-09-01&end=2026-09-30');

        $response->assertOk();
        $response->assertJsonPath('0.calendarLinks.ics', url("/calendario/game-table/{$table->id}.ics"));
    }

    public function test_the_table_page_offers_add_to_calendar_links(): void
    {
        $table = $this->createGameTable(['slug' => 'la-cripta-perdida']);

        $this->get('/mesas/la-cripta-perdida')->assertInertia(
            fn (Assert $page) => $page
                ->component('GameTables/Show', shouldExist: false)
                ->where('calendarLinks.ics', url("/calendario/game-table/{$table->id}.ics"))
                ->where('calendarLinks.google', fn (string $url): bool => str_starts_with($url, 'https://calendar.google.com/'))
        );
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createGameTable(array $overrides = []): GameTableModel
    {
        $defaults = [
            'id' => (string) Str::uuid(),
            'game_system_id' => $this->gameSystem->id,
            'created_by' => $this->creator->id,
            'title' => 'Default Table',
            'slug' => 'default-table',
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
        ];

        return GameTableModel::create(array_merge($defaults, $overrides));
    }
}
