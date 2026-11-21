<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Feature\Http\Controllers;

use App\Application\Services\TimezoneServiceInterface;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\GameTables\Database\Seeders\GenresSeeder;
use Modules\GameTables\Domain\Enums\TableFormat;
use Modules\GameTables\Domain\Enums\TableStatus;
use Modules\GameTables\Domain\Enums\TableType;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\CampaignModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameSystemModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameTableModel;
use Symfony\Component\HttpFoundation\Response;
use Tests\Support\Modules\ModuleTestCase;

/**
 * The calendar (/mesas/calendario) filters like the list: the same parameters, read by the same
 * rules, within the month shown (#314).
 */
final class GameTableCalendarFiltersTest extends ModuleTestCase
{
    protected ?string $moduleName = 'game-tables';

    protected bool $autoEnableModule = true;

    private UserModel $creator;

    private GameSystemModel $alphaSystem;

    private GameSystemModel $betaSystem;

    /** Mid-month of next month, at an hour no time zone moves to another day */
    private CarbonImmutable $day;

    protected function setUp(): void
    {
        parent::setUp();

        // database/ is outside the module's autoloaded src/
        require_once dirname(__DIR__, 4).'/database/seeders/GenresSeeder.php';
        $this->seed(GenresSeeder::class);

        $this->creator = UserModel::factory()->create();
        $this->alphaSystem = $this->createGameSystem('Calendar Fixture Alpha', 'calendar-fixture-alpha');
        $this->betaSystem = $this->createGameSystem('Calendar Fixture Beta', 'calendar-fixture-beta');
        $this->day = CarbonImmutable::now()->addMonthNoOverflow()->startOfMonth()->addDays(14)->setTime(12, 0);

        $this->createGameTable('Mesa de terror', $this->alphaSystem, ['genres' => ['horror'], 'table_format' => TableFormat::Online, 'online_url' => 'https://example.com/mesa']);
        $this->createGameTable('Mesa de fantasía', $this->betaSystem, ['genres' => ['fantasy']]);
    }

    public function test_the_calendar_offers_the_filters_of_the_list(): void
    {
        $this->calendar()
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('tables', 2)
                ->has('gameSystems')
                ->has('events')
                ->where('genres', [
                    ['value' => 'fantasy', 'label' => 'Fantasía'],
                    ['value' => 'horror', 'label' => 'Terror'],
                ])
                ->where('campaign', null)
                ->where('currentFilters', [
                    'systems' => [],
                    'format' => null,
                    'status' => null,
                    'event' => null,
                    'campaign' => null,
                    'genre' => null,
                ])
                ->where('currentMonth', $this->day->format('Y-m')));
    }

    public function test_each_filter_narrows_the_month(): void
    {
        foreach ([
            'systems='.$this->alphaSystem->id,
            'format=online',
            'genre=horror',
        ] as $filter) {
            $this->calendar($filter)
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->where('tables', fn (Collection $tables): bool => $tables->pluck('title')->all() === ['Mesa de terror']));
        }

        $this->calendar('status=cancelled')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('tables', 0)->where('currentFilters.status', 'cancelled'));
    }

    public function test_a_campaign_shows_only_its_tables_of_the_month(): void
    {
        $campaign = $this->createCampaign('Campaña de prueba');
        $this->createGameTable('Sesión de la campaña', $this->alphaSystem, [
            'campaign_id' => $campaign->id,
            'starts_at' => $this->day->subDays(10),
        ]);

        $this->calendar('campaign='.$campaign->id)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('tables', fn (Collection $tables): bool => $tables->pluck('title')->all() === ['Sesión de la campaña'])
                ->where('campaign', ['id' => $campaign->id, 'title' => 'Campaña de prueba'])
                ->where('currentFilters.campaign', $campaign->id));
    }

    public function test_a_hidden_campaign_or_malformed_ids_are_no_filter(): void
    {
        $hidden = $this->createCampaign('Campaña oculta', ['is_published' => false]);

        foreach (['campaign='.$hidden->id, 'campaign=no-es-un-uuid', 'systems=no-es-un-uuid', 'genre=Terror!'] as $filter) {
            $this->calendar($filter)
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->has('tables', 2)
                    ->where('campaign', null)
                    ->where('currentFilters.campaign', null)
                    ->where('currentFilters.systems', [])
                    ->where('currentFilters.genre', null));
        }
    }

    public function test_a_malformed_month_shows_the_current_one(): void
    {
        foreach (['abc', '2026-13', '2026-1', '2026-11-05', ''] as $month) {
            $this->get('/mesas/calendario?month='.urlencode($month))
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page->where('currentMonth', app(TimezoneServiceInterface::class)->today()->format('Y-m')));
        }

        $this->get('/mesas/calendario?month[]=2026-11')->assertOk();
    }

    /**
     * @return TestResponse<Response>
     */
    private function calendar(string $query = ''): TestResponse
    {
        return $this->get('/mesas/calendario?month='.$this->day->format('Y-m').($query !== '' ? '&'.$query : ''));
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

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createGameTable(string $title, GameSystemModel $system, array $attributes = []): GameTableModel
    {
        return GameTableModel::create([
            'id' => (string) Str::uuid(),
            'game_system_id' => $system->id,
            'created_by' => $this->creator->id,
            'title' => $title,
            'slug' => Str::slug($title).'-'.Str::random(4),
            'starts_at' => $this->day,
            'duration_minutes' => 180,
            'table_type' => TableType::OneShot,
            'table_format' => TableFormat::InPerson,
            'location' => 'Local de la asociación',
            'status' => TableStatus::Scheduled,
            'min_players' => 2,
            'max_players' => 6,
            'language' => 'es',
            'auto_confirm' => true,
            'is_published' => true,
            'published_at' => now(),
            ...$attributes,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createCampaign(string $title, array $attributes = []): CampaignModel
    {
        return CampaignModel::create([
            'id' => (string) Str::uuid(),
            'game_system_id' => $this->alphaSystem->id,
            'created_by' => $this->creator->id,
            'title' => $title,
            'slug' => Str::slug($title),
            'frequency' => 'weekly',
            'status' => 'active',
            'max_players' => 5,
            'is_published' => true,
            ...$attributes,
        ]);
    }
}
