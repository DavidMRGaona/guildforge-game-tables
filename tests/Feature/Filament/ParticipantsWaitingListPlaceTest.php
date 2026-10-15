<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Feature\Filament;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Modules\GameTables\Filament\Resources\GameTableResource;
use Modules\GameTables\Filament\Resources\GameTableResource\Pages\EditGameTable;
use Modules\GameTables\Filament\Resources\GameTableResource\RelationManagers\ParticipantsRelationManager;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameSystemModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameTableModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\ParticipantModel;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\Modules\InteractsWithModulePanel;
use Tests\Support\Modules\ModuleTestCase;

/**
 * The waiting list column of the participants table shows each waiting row's place among those
 * still waiting for the same role, and nothing for rows that left the list.
 */
final class ParticipantsWaitingListPlaceTest extends ModuleTestCase
{
    use InteractsWithModulePanel;

    protected ?string $moduleName = 'game-tables';

    protected bool $autoEnableModule = true;

    private UserModel $admin;

    private GameSystemModel $gameSystem;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registerOnAdminPanel([GameTableResource::class]);
        $this->admin = $this->actingAsPanelAdmin();
        config()->set('modules.settings.game-tables', []);
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

    #[DataProvider('waitingListSetting')]
    public function test_the_column_shows_the_place_among_those_still_waiting(bool $waitingList): void
    {
        config()->set('modules.settings.game-tables.registration.waiting_list_enabled', $waitingList);
        $table = $this->table();
        $this->participant($table, 'player', 'confirmed');
        $left = $this->participant($table, 'player', 'cancelled', 1);
        $second = $this->participant($table, 'player', 'waiting_list', 2);
        $spectator = $this->participant($table, 'spectator', 'waiting_list', 3);
        $fourth = $this->participant($table, 'player', 'waiting_list', 4);

        $this->participants($table)
            ->assertTableColumnStateSet('waiting_list_position', 1, $second)
            ->assertTableColumnStateSet('waiting_list_position', 2, $fourth)
            ->assertTableColumnStateSet('waiting_list_position', 1, $spectator)
            ->assertTableColumnStateSet('waiting_list_position', null, $left);
    }

    #[DataProvider('waitingListSetting')]
    public function test_the_column_shows_only_while_someone_waits(bool $waitingList): void
    {
        config()->set('modules.settings.game-tables.registration.waiting_list_enabled', $waitingList);
        $table = $this->table();
        $this->participant($table, 'player', 'confirmed');
        $this->participant($table, 'player', 'cancelled', 1);

        $this->participants($table)->assertTableColumnHidden('waiting_list_position');

        $this->participant($table, 'spectator', 'waiting_list', 2);

        $this->participants($table)->assertTableColumnVisible('waiting_list_position');
    }

    #[DataProvider('waitingListSetting')]
    public function test_after_a_promotion_the_places_are_counted_again(bool $waitingList): void
    {
        config()->set('modules.settings.game-tables.registration.waiting_list_enabled', $waitingList);
        $table = $this->table();
        $first = $this->participant($table, 'player', 'waiting_list', 1);
        $second = $this->participant($table, 'player', 'waiting_list', 2);

        $this->participants($table)
            ->assertTableColumnStateSet('waiting_list_position', 2, $second)
            ->callTableAction('promote', $first)
            ->assertHasNoTableActionErrors()
            ->assertTableColumnStateSet('waiting_list_position', 1, $second)
            ->assertTableColumnStateSet('waiting_list_position', null, $first);
    }

    #[DataProvider('waitingListSetting')]
    public function test_the_places_take_one_query_for_the_whole_table(bool $waitingList): void
    {
        config()->set('modules.settings.game-tables.registration.waiting_list_enabled', $waitingList);
        $small = $this->table();
        $large = $this->table();
        foreach ([[$small, 2], [$large, 6]] as [$table, $waiting]) {
            $this->participant($table, 'player', 'confirmed');
            for ($position = 1; $position <= $waiting; $position++) {
                $this->participant($table, 'player', 'waiting_list', $position * 2);
            }
        }

        $this->assertSame($this->queriesToList($small), $this->queriesToList($large));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function placeLabels(): array
    {
        return [
            'es' => ['es', 'Puesto en lista de espera'],
            'en' => ['en', 'Place on the waiting list'],
        ];
    }

    #[DataProvider('placeLabels')]
    public function test_the_column_is_labelled_as_the_place(string $locale, string $label): void
    {
        $this->assertSame($label, __('game-tables::messages.fields.waiting_list_position', [], $locale));
    }

    private function queriesToList(GameTableModel $table): int
    {
        $component = $this->participants($table);
        DB::flushQueryLog();
        DB::enableQueryLog();
        $component->call('$refresh')->assertOk();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }

    /**
     * @return Testable<ParticipantsRelationManager>
     */
    private function participants(GameTableModel $table): Testable
    {
        return Livewire::test(ParticipantsRelationManager::class, ['ownerRecord' => $table, 'pageClass' => EditGameTable::class]);
    }

    private function participant(GameTableModel $table, string $role, string $status, ?int $position = null): ParticipantModel
    {
        return ParticipantModel::create([
            'id' => (string) Str::uuid(),
            'game_table_id' => $table->id,
            'user_id' => UserModel::factory()->create()->id,
            'role' => $role,
            'status' => $status,
            'waiting_list_position' => $position,
            'confirmed_at' => $status === 'confirmed' ? now() : null,
        ]);
    }

    private function table(): GameTableModel
    {
        return GameTableModel::create([
            'id' => (string) Str::uuid(),
            'game_system_id' => $this->gameSystem->id,
            'created_by' => $this->admin->id,
            'title' => 'Mesa de prueba',
            'slug' => 'mesa-de-prueba-'.Str::lower(Str::random(6)),
            'starts_at' => now()->addWeek(),
            'duration_minutes' => 240,
            'table_type' => 'one_shot',
            'table_format' => 'in_person',
            'status' => 'scheduled',
            'min_players' => 1,
            'max_players' => 1,
            'max_spectators' => 0,
            'language' => 'es',
            'registration_type' => 'everyone',
            'auto_confirm' => true,
            'is_published' => true,
        ]);
    }
}
