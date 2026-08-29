<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Feature\Calendar;

use App\Application\Calendar\Services\CalendarSourceRegistryInterface;
use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Support\Str;
use Modules\GameTables\Domain\Enums\TableFormat;
use Modules\GameTables\Domain\Enums\TableStatus;
use Modules\GameTables\Domain\Enums\TableType;
use Modules\GameTables\Infrastructure\Calendar\GameTableCalendarSource;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameSystemModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameTableModel;
use Tests\Support\Modules\ModuleTestCase;

final class PublicCalendarIntegrationTest extends ModuleTestCase
{
    protected ?string $moduleName = 'game-tables';

    protected bool $autoEnableModule = true;

    private GameSystemModel $gameSystem;

    private UserModel $creator;

    protected function setUp(): void
    {
        parent::setUp();

        // Belt-and-braces: the module's own registerCalendarSources() hook (invoked by
        // ModuleTestCase::enableModule() -> ModuleLoader::bootModule()) should already
        // register this source, but the registry dedupes by class-string, so registering
        // it explicitly here keeps the test robust even if that wiring changes.
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

    public function test_published_scheduled_table_appears_with_game_table_source_type_and_accent_color(): void
    {
        $this->createGameTable([
            'title' => 'Published Table',
            'slug' => 'published-table',
            'status' => TableStatus::Scheduled,
            'is_published' => true,
            'starts_at' => '2026-09-15 10:00:00',
        ]);

        $response = $this->getJson('/eventos/calendario?start=2026-09-01&end=2026-09-30');

        $response->assertStatus(200);
        $response->assertJsonFragment([
            'title' => 'Published Table',
            'sourceType' => 'game-table',
            'color' => 'accent',
        ]);
    }

    public function test_draft_table_does_not_appear_in_public_calendar(): void
    {
        $this->createGameTable([
            'title' => 'Draft Table',
            'slug' => 'draft-table',
            'status' => TableStatus::Draft,
            'is_published' => false,
            'starts_at' => '2026-09-15 10:00:00',
        ]);

        $response = $this->getJson('/eventos/calendario?start=2026-09-01&end=2026-09-30');

        $response->assertStatus(200);
        $response->assertJsonMissing(['title' => 'Draft Table']);
    }

    public function test_cancelled_table_does_not_appear_in_public_calendar(): void
    {
        $this->createGameTable([
            'title' => 'Cancelled Table',
            'slug' => 'cancelled-table',
            'status' => TableStatus::Cancelled,
            'is_published' => true,
            'starts_at' => '2026-09-15 10:00:00',
        ]);

        $response = $this->getJson('/eventos/calendario?start=2026-09-01&end=2026-09-30');

        $response->assertStatus(200);
        $response->assertJsonMissing(['title' => 'Cancelled Table']);
    }

    public function test_unpublished_table_does_not_appear_in_public_calendar(): void
    {
        $this->createGameTable([
            'title' => 'Unpublished Table',
            'slug' => 'unpublished-table',
            'status' => TableStatus::Scheduled,
            'is_published' => false,
            'starts_at' => '2026-09-15 10:00:00',
        ]);

        $response = $this->getJson('/eventos/calendario?start=2026-09-01&end=2026-09-30');

        $response->assertStatus(200);
        $response->assertJsonMissing(['title' => 'Unpublished Table']);
    }

    public function test_mixed_day_returns_both_core_event_and_game_table_with_different_source_types(): void
    {
        EventModel::factory()->published()->create([
            'title' => 'Mixed Day Event',
            'start_date' => '2026-09-15 09:00:00',
            'end_date' => '2026-09-15 11:00:00',
        ]);

        $this->createGameTable([
            'title' => 'Mixed Day Table',
            'slug' => 'mixed-day-table',
            'status' => TableStatus::Scheduled,
            'is_published' => true,
            'starts_at' => '2026-09-15 18:00:00',
        ]);

        $response = $this->getJson('/eventos/calendario?start=2026-09-01&end=2026-09-30');

        $response->assertStatus(200);
        $response->assertJsonFragment([
            'title' => 'Mixed Day Event',
            'sourceType' => 'event',
            'color' => 'primary',
        ]);
        $response->assertJsonFragment([
            'title' => 'Mixed Day Table',
            'sourceType' => 'game-table',
            'color' => 'accent',
        ]);

        $json = $response->json();
        $sourceTypes = array_unique(array_column($json, 'sourceType'));
        sort($sourceTypes);

        $this->assertSame(['event', 'game-table'], $sourceTypes);
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
