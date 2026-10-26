<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Feature\Http\Controllers;

use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\GameTables\Domain\Entities\GameTable;
use Modules\GameTables\Domain\Enums\TableFormat;
use Modules\GameTables\Domain\Enums\TableStatus;
use Modules\GameTables\Domain\Enums\TableType;
use Modules\GameTables\Domain\Repositories\GameTableRepositoryInterface;
use Modules\GameTables\Domain\ValueObjects\CampaignId;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\CampaignModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameSystemModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameTableModel;
use Tests\Support\Modules\ModuleTestCase;

/**
 * Tables starting at the same time (same slot of an event) are ordered by title and
 * then by id. PostgreSQL returns tied rows in no fixed order, which changes after any
 * update; the fixtures are inserted in the opposite order on purpose, because SQLite
 * would otherwise hand them back in insertion order and hide the missing tiebreak.
 */
final class TableListTiebreakTest extends ModuleTestCase
{
    protected ?string $moduleName = 'game-tables';

    protected bool $autoEnableModule = true;

    private UserModel $creator;

    private GameSystemModel $system;

    private CarbonImmutable $startsAt;

    protected function setUp(): void
    {
        parent::setUp();

        app('router')->getRoutes()->refreshNameLookups();
        config()->set('modules.settings.game-tables', []);

        $this->creator = UserModel::factory()->create(['name' => 'John Doe', 'email' => 'john@example.com']);
        $this->system = GameSystemModel::create([
            'id' => (string) Str::uuid(),
            'name' => 'Tiebreak Fixture',
            'slug' => 'tiebreak-fixture',
            'is_active' => true,
        ]);
        // Mid-month, so the calendar month holds it in every zone
        $this->startsAt = CarbonImmutable::now()->addMonthNoOverflow()->setDay(15)->setTime(18, 0);
    }

    public function test_the_listing_orders_tied_tables_by_title_then_id(): void
    {
        $this->table('Zeta');
        $this->table('Alfa', ['id' => 'ffffffff-ffff-4fff-bfff-ffffffffffff', 'slug' => 'alfa-2']);
        $this->table('Alfa', ['id' => '00000000-0000-4000-8000-000000000000']);
        $this->table('Media');

        $this->get('/mesas')->assertInertia(fn (Assert $page) => $page
            ->where('tables.data.0.id', '00000000-0000-4000-8000-000000000000')
            ->where('tables.data.1.id', 'ffffffff-ffff-4fff-bfff-ffffffffffff')
            ->where('tables.data.2.title', 'Media')
            ->where('tables.data.3.title', 'Zeta'));
    }

    public function test_tied_tables_are_split_across_pages_without_repeating_or_skipping(): void
    {
        // One more than a page, inserted from the last title to the first
        foreach (range(13, 1) as $number) {
            $this->table(sprintf('Mesa %02d', $number));
        }

        $this->get('/mesas')->assertInertia(fn (Assert $page) => $page
            ->has('tables.data', 12)
            ->where('tables.data.0.title', 'Mesa 01')
            ->where('tables.data.11.title', 'Mesa 12'));

        $this->get('/mesas?page=2')->assertInertia(fn (Assert $page) => $page
            ->has('tables.data', 1)
            ->where('tables.data.0.title', 'Mesa 13'));
    }

    public function test_the_calendar_orders_tied_tables_by_title(): void
    {
        $this->table('Zeta');
        $this->table('Alfa');

        $this->get('/mesas/calendario?month='.$this->startsAt->format('Y-m'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('tables.0.title', 'Alfa')
                ->where('tables.1.title', 'Zeta'));
    }

    public function test_the_campaign_page_orders_tied_sessions_by_title(): void
    {
        $campaign = CampaignModel::create([
            'id' => (string) Str::uuid(),
            'game_system_id' => $this->system->id,
            'created_by' => $this->creator->id,
            'title' => 'La cripta olvidada',
            'slug' => 'la-cripta-olvidada',
            'status' => 'active',
            'frontend_creation_status' => 'approved',
            'is_published' => true,
            'accepts_new_players' => true,
            'max_players' => 4,
            'roster_access' => 'anyone',
        ]);
        $this->table('Zeta', ['campaign_id' => $campaign->id]);
        $this->table('Alfa', ['campaign_id' => $campaign->id]);

        $this->get('/campanas/la-cripta-olvidada')->assertInertia(fn (Assert $page) => $page
            ->where('campaign.gameTables.0.title', 'Alfa')
            ->where('campaign.gameTables.1.title', 'Zeta'));
    }

    public function test_the_repository_orders_tied_tables_by_title(): void
    {
        $event = EventModel::factory()->published()->create();
        $campaign = CampaignModel::create([
            'id' => (string) Str::uuid(),
            'game_system_id' => $this->system->id,
            'created_by' => $this->creator->id,
            'title' => 'Campaña de prueba',
            'slug' => 'campana-de-prueba',
            'status' => 'active',
            'is_published' => true,
            'max_players' => 4,
        ]);
        $this->table('Zeta', ['event_id' => $event->id, 'campaign_id' => $campaign->id]);
        $this->table('Alfa', ['event_id' => $event->id, 'campaign_id' => $campaign->id]);

        $repository = app(GameTableRepositoryInterface::class);
        $titles = static fn (array $tables): array => array_map(
            static fn (GameTable $table): string => $table->title,
            $tables,
        );

        $this->assertSame(['Alfa', 'Zeta'], $titles($repository->getPublished()));
        $this->assertSame(['Alfa', 'Zeta'], $titles($repository->getByStatus(TableStatus::Scheduled)));
        $this->assertSame(['Alfa', 'Zeta'], $titles($repository->getByEvent($event->id)));
        $this->assertSame(['Alfa', 'Zeta'], $titles($repository->getByCampaign(new CampaignId($campaign->id))));
        $this->assertSame(['Alfa', 'Zeta'], $titles($repository->getUpcoming()));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function table(string $title, array $attributes = []): GameTableModel
    {
        return GameTableModel::create([
            'id' => (string) Str::uuid(),
            'game_system_id' => $this->system->id,
            'created_by' => $this->creator->id,
            'title' => $title,
            'slug' => Str::slug($title),
            'starts_at' => $this->startsAt,
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
            ...$attributes,
        ]);
    }
}
