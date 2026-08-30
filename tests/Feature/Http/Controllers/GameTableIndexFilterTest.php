<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Feature\Http\Controllers;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\GameTables\Domain\Enums\TableFormat;
use Modules\GameTables\Domain\Enums\TableStatus;
use Modules\GameTables\Domain\Enums\TableType;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameSystemModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameTableModel;
use Tests\Support\Modules\ModuleTestCase;

/**
 * The game system filter is multi-valued, so it travels as a query parameter the
 * listing has to decode. These tests pin both accepted shapes: a comma-separated
 * string, and the bracketed array a client may send instead.
 */
final class GameTableIndexFilterTest extends ModuleTestCase
{
    protected ?string $moduleName = 'game-tables';

    protected bool $autoEnableModule = true;

    private UserModel $creator;

    private GameSystemModel $dnd;

    private GameSystemModel $vampire;

    protected function setUp(): void
    {
        parent::setUp();

        $this->creator = UserModel::create([
            'id' => (string) Str::uuid(),
            'name' => 'Table Creator',
            'email' => 'creator@example.com',
            'password' => 'password',
        ]);

        // Slugs are unique-constrained and the module seeds its own systems,
        // so these fixtures use names of their own.
        $this->dnd = $this->createGameSystem('Filter Fixture Alpha', 'filter-fixture-alpha');
        $this->vampire = $this->createGameSystem('Filter Fixture Beta', 'filter-fixture-beta');

        $this->createGameTable($this->dnd, 'Mesa Alpha', 'mesa-alpha');
        $this->createGameTable($this->vampire, 'Mesa Beta', 'mesa-beta');
    }

    public function test_without_filters_every_published_table_is_listed(): void
    {
        $this->get('/mesas')->assertInertia(
            fn (Assert $page) => $page->has('tables.data', 2)
        );
    }

    public function test_comma_separated_systems_filter_the_listing(): void
    {
        $response = $this->get("/mesas?systems={$this->dnd->id}");

        $response->assertInertia(
            fn (Assert $page) => $page
                ->has('tables.data', 1)
                ->where('tables.data.0.title', 'Mesa Alpha')
                ->where('currentFilters.systems', [$this->dnd->id])
        );
    }

    public function test_bracketed_array_systems_filter_the_listing(): void
    {
        // What the filter component sends: router.visit serialises an array of ids
        // as systems[]=..., which the listing must decode just like the CSV form.
        $response = $this->get("/mesas?systems[]={$this->vampire->id}");

        $response->assertInertia(
            fn (Assert $page) => $page
                ->has('tables.data', 1)
                ->where('tables.data.0.title', 'Mesa Beta')
                ->where('currentFilters.systems', [$this->vampire->id])
        );
    }

    public function test_several_systems_are_combined_as_alternatives(): void
    {
        $response = $this->get("/mesas?systems={$this->dnd->id},{$this->vampire->id}");

        $response->assertInertia(
            fn (Assert $page) => $page->has('tables.data', 2)
        );
    }

    public function test_an_unknown_system_yields_no_tables(): void
    {
        $response = $this->get('/mesas?systems='.Str::uuid()->toString());

        $response->assertInertia(
            fn (Assert $page) => $page->has('tables.data', 0)
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

    private function createGameTable(GameSystemModel $system, string $title, string $slug): GameTableModel
    {
        return GameTableModel::create([
            'id' => (string) Str::uuid(),
            'game_system_id' => $system->id,
            'created_by' => $this->creator->id,
            'title' => $title,
            'slug' => $slug,
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
}
