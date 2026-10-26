<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Feature\Http\Controllers;

use App\Application\Calendar\Services\CalendarSourceRegistryInterface;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\GameTables\Domain\Enums\CampaignStatus;
use Modules\GameTables\Infrastructure\Calendar\GameTableCalendarSource;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\CampaignModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameSystemModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameTableModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\ParticipantModel;
use Modules\GameTables\Infrastructure\Services\ProfileCreatedTablesDataProvider;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\Modules\ModuleTestCase;

/**
 * The table cards (/mesas, the module calendar, a campaign's tables, the author's profile) and the
 * core calendar show the player seats the table page shows: the server's one count, where
 * confirmed players always take a seat and pending ones only with the waiting list on and on
 * tables with manual confirmation.
 */
final class ListSeatsTest extends ModuleTestCase
{
    protected ?string $moduleName = 'game-tables';

    protected bool $autoEnableModule = true;

    private GameSystemModel $gameSystem;

    private UserModel $creator;

    protected function setUp(): void
    {
        parent::setUp();

        app('router')->getRoutes()->refreshNameLookups();
        app(CalendarSourceRegistryInterface::class)->register(GameTableCalendarSource::class);
        config()->set('modules.settings.game-tables', []);

        $this->creator = UserModel::factory()->create();
        $this->gameSystem = GameSystemModel::create([
            'id' => (string) Str::uuid(),
            'name' => 'Sistema de prueba',
            'slug' => 'sistema-de-prueba',
            'is_active' => true,
        ]);
    }

    /**
     * @return array<string, array{bool}>
     */
    public static function waitingListSetting(): array
    {
        return [
            'waiting list off' => [false],
            'waiting list on' => [true],
        ];
    }

    // Characterization: confirmed players take the seats shown

    #[DataProvider('waitingListSetting')]
    public function test_confirmed_players_take_the_seats_shown_on_every_card(bool $waitingList): void
    {
        $this->setWaitingList($waitingList);
        $table = $this->table(['max_players' => 4]);
        $this->participants($table, 'player', 'confirmed', 3);

        $this->assertSeatsEverywhere($table, taken: 3, free: 1, full: false);
    }

    #[DataProvider('waitingListSetting')]
    public function test_a_table_with_every_player_seat_confirmed_is_full_on_every_card(bool $waitingList): void
    {
        $this->setWaitingList($waitingList);
        $table = $this->table(['max_players' => 3]);
        $this->participants($table, 'player', 'confirmed', 3);

        $this->assertSeatsEverywhere($table, taken: 3, free: 0, full: true);
    }

    public function test_with_the_waiting_list_on_pending_players_hold_seats_on_manual_tables_on_every_card(): void
    {
        $this->setWaitingList(true);
        $table = $this->table(['auto_confirm' => false, 'max_players' => 3]);
        $this->participants($table, 'player', 'confirmed', 1);
        $this->participants($table, 'player', 'pending', 2);

        $this->assertSeatsEverywhere($table, taken: 3, free: 0, full: true);
    }

    public function test_the_listing_does_not_query_once_per_table(): void
    {
        $this->setWaitingList(true);
        $first = $this->table(['auto_confirm' => false]);
        $this->participants($first, 'player', 'pending', 1);
        $queriesForOne = $this->queriesFor('/mesas');
        $calendarForOne = $this->queriesFor('/eventos/calendario?'.$this->calendarRange());

        foreach ([true, false, true] as $autoConfirm) {
            $table = $this->table(['auto_confirm' => $autoConfirm]);
            $this->participants($table, 'player', 'confirmed', 1);
            $this->participants($table, 'player', 'pending', 1);
        }

        $this->assertSame($queriesForOne, $this->queriesFor('/mesas'));
        $this->assertSame($calendarForOne, $this->queriesFor('/eventos/calendario?'.$this->calendarRange()));
    }

    // The cards count what the table page counts

    #[DataProvider('waitingListSetting')]
    public function test_rows_that_hold_no_seat_leave_it_free_on_every_card(bool $waitingList): void
    {
        $this->setWaitingList($waitingList);
        $table = $this->table(['max_players' => 4, 'max_spectators' => 3]);
        $this->participants($table, 'player', 'confirmed', 1);
        $this->participants($table, 'player', 'cancelled', 2);
        $this->participants($table, 'player', 'rejected', 1);
        $this->participants($table, 'player', 'no_show', 1);
        $this->participants($table, 'player', 'waiting_list', 1, ['waiting_list_position' => 1]);
        $this->participants($table, 'spectator', 'confirmed', 2);

        $this->assertSeatsEverywhere($table, taken: 1, free: 3, full: false);
    }

    #[DataProvider('waitingListSetting')]
    public function test_pending_players_hold_no_seat_on_tables_that_confirm_automatically(bool $waitingList): void
    {
        $this->setWaitingList($waitingList);
        $table = $this->table(['max_players' => 2]);
        $this->participants($table, 'player', 'confirmed', 1);
        $this->participants($table, 'player', 'pending', 1);

        $this->assertSeatsEverywhere($table, taken: 1, free: 1, full: false);
    }

    public function test_with_the_waiting_list_off_pending_players_hold_no_seat_on_manual_tables(): void
    {
        $table = $this->table(['auto_confirm' => false, 'max_players' => 2]);
        $this->participants($table, 'player', 'pending', 2);

        $this->assertSeatsEverywhere($table, taken: 0, free: 2, full: false);
    }

    private function assertSeatsEverywhere(GameTableModel $table, int $taken, int $free, bool $full): void
    {
        $this->get(route('gametables.show', $table->slug))
            ->assertInertia(fn (Assert $page) => $page
                ->where('table.currentPlayers', $taken)
                ->where('table.spotsAvailable', $free));

        $this->get('/mesas')
            ->assertInertia(fn (Assert $page) => $page
                ->where('tables.data.0.currentPlayers', $taken)
                ->where('tables.data.0.spotsAvailable', $free)
                ->where('tables.data.0.isFull', $full));

        $this->get('/mesas/calendario?month='.$table->starts_at->format('Y-m'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('tables.0.currentPlayers', $taken)
                ->where('tables.0.spotsAvailable', $free)
                ->where('tables.0.isFull', $full));

        /** @var array<int, array<string, mixed>> $entries */
        $entries = $this->getJson('/eventos/calendario?'.$this->calendarRange())->assertOk()->json();
        $entry = array_first(array_filter($entries, fn (array $item): bool => $item['id'] === $table->id));
        $this->assertIsArray($entry);
        $this->assertSame($taken, $entry['currentPlayers']);
        $this->assertSame($full, $entry['isFull']);
        /** @var array<int, array{icon: string, label: string, value: string}> $meta */
        $meta = $entry['meta'];
        $players = array_first(array_filter($meta, fn (array $row): bool => $row['icon'] === 'users'));
        $this->assertIsArray($players);
        $this->assertStringStartsWith("{$taken}/{$table->max_players}", $players['value']);

        $campaign = $this->campaignWith($table);
        $this->get(route('campaigns.show', $campaign->slug))
            ->assertInertia(fn (Assert $page) => $page
                ->where('campaign.gameTables.0.currentPlayers', $taken)
                ->where('campaign.gameTables.0.spotsAvailable', $free)
                ->where('campaign.gameTables.0.isFull', $full));

        $profile = app(ProfileCreatedTablesDataProvider::class)->getDataForUser($table->created_by);
        $this->assertSame($taken, $profile['tables'][0]['currentPlayers'] ?? null);
    }

    private function queriesFor(string $uri): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->get($uri)->assertOk();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }

    private function calendarRange(): string
    {
        return 'start='.now()->toDateString().'&end='.now()->addMonth()->toDateString();
    }

    private function setWaitingList(bool $enabled): void
    {
        config()->set('modules.settings.game-tables.registration.waiting_list_enabled', $enabled);
    }

    private function campaignWith(GameTableModel $table): CampaignModel
    {
        $campaign = CampaignModel::create([
            'id' => (string) Str::uuid(),
            'game_system_id' => $this->gameSystem->id,
            'created_by' => $this->creator->id,
            'title' => 'Campaña de prueba',
            'slug' => 'campana-de-prueba-'.Str::lower(Str::random(6)),
            'status' => CampaignStatus::Active,
            'max_players' => 5,
            'is_published' => true,
            'published_at' => now(),
        ]);
        $table->update(['campaign_id' => $campaign->id]);

        return $campaign;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function participants(GameTableModel $table, string $role, string $status, int $count, array $attributes = []): void
    {
        for ($i = 0; $i < $count; $i++) {
            ParticipantModel::create([
                'id' => (string) Str::uuid(),
                'game_table_id' => $table->id,
                'user_id' => UserModel::factory()->create()->id,
                'role' => $role,
                'status' => $status,
                'confirmed_at' => $status === 'confirmed' ? now() : null,
                ...$attributes,
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function table(array $attributes = []): GameTableModel
    {
        return GameTableModel::create([
            'id' => (string) Str::uuid(),
            'game_system_id' => $this->gameSystem->id,
            'created_by' => $this->creator->id,
            'title' => 'Mesa de prueba',
            'slug' => 'mesa-de-prueba-'.Str::lower(Str::random(6)),
            'starts_at' => now()->addWeek(),
            'duration_minutes' => 240,
            'table_type' => 'one_shot',
            'table_format' => 'in_person',
            'status' => 'scheduled',
            'min_players' => 1,
            'max_players' => 5,
            'max_spectators' => 0,
            'language' => 'es',
            'registration_type' => 'everyone',
            'auto_confirm' => true,
            'is_published' => true,
            'published_at' => now(),
            ...$attributes,
        ]);
    }
}
