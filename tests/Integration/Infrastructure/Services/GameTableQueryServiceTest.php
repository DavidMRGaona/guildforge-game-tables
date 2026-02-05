<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Integration\Infrastructure\Services;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Modules\GameTables\Application\Services\EventWithTablesQueryInterface;
use Modules\GameTables\Domain\Enums\FrontendCreationStatus;
use Modules\GameTables\Domain\Enums\TableFormat;
use Modules\GameTables\Domain\Enums\TableStatus;
use Modules\GameTables\Domain\Enums\TableType;
use Modules\GameTables\Domain\Repositories\GameSystemRepositoryInterface;
use Modules\GameTables\Domain\Repositories\GameTableRepositoryInterface;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameSystemModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameTableModel;
use Modules\GameTables\Infrastructure\Services\GameTableQueryService;
use Tests\TestCase;

final class GameTableQueryServiceTest extends TestCase
{
    use RefreshDatabase;

    private GameTableQueryService $queryService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->queryService = new GameTableQueryService(
            $this->app->make(GameTableRepositoryInterface::class),
            $this->app->make(GameSystemRepositoryInterface::class),
            $this->app->make(EventWithTablesQueryInterface::class),
        );
    }

    public function test_find_published_by_slug_returns_dto_with_frontend_creation_status(): void
    {
        $gameSystem = GameSystemModel::create([
            'id' => (string) Str::uuid(),
            'name' => 'Dungeons & Dragons',
            'slug' => 'dnd',
            'game_master_title' => 'Dungeon Master',
            'is_active' => true,
        ]);

        $gameTable = GameTableModel::create([
            'id' => (string) Str::uuid(),
            'game_system_id' => $gameSystem->id,
            'created_by' => (string) Str::uuid(),
            'title' => 'Epic Adventure',
            'slug' => 'epic-adventure',
            'starts_at' => now()->addWeek(),
            'duration_minutes' => 240,
            'table_type' => TableType::OneShot,
            'table_format' => TableFormat::InPerson,
            'status' => TableStatus::Open,
            'min_players' => 2,
            'max_players' => 6,
            'language' => 'es',
            'auto_confirm' => true,
            'is_published' => true,
            'published_at' => now(),
            'frontend_creation_status' => FrontendCreationStatus::Submitted,
        ]);

        $result = $this->queryService->findPublishedBySlug('epic-adventure');

        $this->assertNotNull($result);
        $this->assertEquals($gameTable->id, $result->id);
        $this->assertEquals('Epic Adventure', $result->title);
        $this->assertNotNull($result->frontendCreationStatus);
        $this->assertEquals(FrontendCreationStatus::Submitted, $result->frontendCreationStatus);
    }

    public function test_find_published_by_slug_returns_dto_with_null_frontend_creation_status(): void
    {
        $gameSystem = GameSystemModel::create([
            'id' => (string) Str::uuid(),
            'name' => 'Pathfinder',
            'slug' => 'pathfinder',
            'game_master_title' => 'Game Master',
            'is_active' => true,
        ]);

        $gameTable = GameTableModel::create([
            'id' => (string) Str::uuid(),
            'game_system_id' => $gameSystem->id,
            'created_by' => (string) Str::uuid(),
            'title' => 'Admin Created Table',
            'slug' => 'admin-created-table',
            'starts_at' => now()->addWeek(),
            'duration_minutes' => 180,
            'table_type' => TableType::Campaign,
            'table_format' => TableFormat::Online,
            'status' => TableStatus::Open,
            'min_players' => 3,
            'max_players' => 5,
            'language' => 'es',
            'auto_confirm' => true,
            'is_published' => true,
            'published_at' => now(),
            'frontend_creation_status' => null,
        ]);

        $result = $this->queryService->findPublishedBySlug('admin-created-table');

        $this->assertNotNull($result);
        $this->assertEquals($gameTable->id, $result->id);
        $this->assertEquals('Admin Created Table', $result->title);
        $this->assertNull($result->frontendCreationStatus);
    }

    public function test_find_published_by_slug_returns_dto_with_approved_frontend_creation_status(): void
    {
        $gameSystem = GameSystemModel::create([
            'id' => (string) Str::uuid(),
            'name' => 'Call of Cthulhu',
            'slug' => 'call-of-cthulhu',
            'game_master_title' => 'Keeper',
            'is_active' => true,
        ]);

        $gameTable = GameTableModel::create([
            'id' => (string) Str::uuid(),
            'game_system_id' => $gameSystem->id,
            'created_by' => (string) Str::uuid(),
            'title' => 'Horror Mystery',
            'slug' => 'horror-mystery',
            'starts_at' => now()->addWeek(),
            'duration_minutes' => 300,
            'table_type' => TableType::OneShot,
            'table_format' => TableFormat::Hybrid,
            'status' => TableStatus::Open,
            'min_players' => 2,
            'max_players' => 4,
            'language' => 'es',
            'auto_confirm' => false,
            'is_published' => true,
            'published_at' => now(),
            'frontend_creation_status' => FrontendCreationStatus::Approved,
        ]);

        $result = $this->queryService->findPublishedBySlug('horror-mystery');

        $this->assertNotNull($result);
        $this->assertEquals($gameTable->id, $result->id);
        $this->assertEquals('Horror Mystery', $result->title);
        $this->assertNotNull($result->frontendCreationStatus);
        $this->assertEquals(FrontendCreationStatus::Approved, $result->frontendCreationStatus);
    }
}