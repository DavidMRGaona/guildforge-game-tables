<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Feature\Filament;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
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
 * Confirm and Promote in the panel: with the waiting list on they never take a table over its
 * player or spectator seats, counted as the registrations count them (pending ones hold seats on
 * manual tables); with the list off they work as before.
 */
final class ParticipantsConfirmSeatsTest extends ModuleTestCase
{
    use InteractsWithModulePanel;

    protected ?string $moduleName = 'game-tables';

    protected bool $autoEnableModule = true;

    private GameSystemModel $gameSystem;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registerOnAdminPanel([GameTableResource::class]);
        $this->actingAsPanelAdmin();
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
    public function test_confirming_a_spectator_beyond_the_spectator_seats(bool $waitingList): void
    {
        $this->setWaitingList($waitingList);
        $table = $this->table(['max_spectators' => 1]);
        $this->participant($table, 'confirmed', 'spectator');
        $pending = $this->participant($table, 'pending', 'spectator');

        $component = $this->participants($table)->callTableAction('confirm', $pending);

        if ($waitingList) {
            $component->assertNotified(__('game-tables::messages.errors.spectators_full'));
            $this->assertSame('pending', $pending->refresh()->status->value);
        } else {
            $this->assertSame('confirmed', $pending->refresh()->status->value);
        }
    }

    // On a manual table with the list on, a pending spectator already holds its seat
    public function test_confirming_a_pending_spectator_who_holds_a_seat_on_a_manual_table(): void
    {
        $this->setWaitingList(true);
        $table = $this->table(['max_spectators' => 1, 'auto_confirm' => false]);
        $pending = $this->participant($table, 'pending', 'spectator');

        $this->participants($table)->callTableAction('confirm', $pending);

        $this->assertSame('confirmed', $pending->refresh()->status->value);
    }

    #[DataProvider('waitingListSetting')]
    public function test_promoting_a_spectator_to_a_table_without_spectator_seats(bool $waitingList): void
    {
        $this->setWaitingList($waitingList);
        $table = $this->table(['max_spectators' => 0]);
        $waiting = $this->participant($table, 'waiting_list', 'spectator', ['waiting_list_position' => 1]);

        $component = $this->participants($table)->callTableAction('promote', $waiting);

        if ($waitingList) {
            $component->assertNotified(__('game-tables::messages.errors.spectators_full'));
            $this->assertSame('waiting_list', $waiting->refresh()->status->value);
        } else {
            $this->assertSame('confirmed', $waiting->refresh()->status->value);
        }
    }

    #[DataProvider('waitingListSetting')]
    public function test_promoting_a_spectator_with_a_free_spectator_seat(bool $waitingList): void
    {
        $this->setWaitingList($waitingList);
        $table = $this->table(['max_spectators' => 1]);
        $waiting = $this->participant($table, 'waiting_list', 'spectator', ['waiting_list_position' => 1]);

        $this->participants($table)->callTableAction('promote', $waiting);

        $this->assertSame('confirmed', $waiting->refresh()->status->value);
    }

    // The pending players of a manual table hold their seats: promoting past them overfills it
    #[DataProvider('waitingListSetting')]
    public function test_promoting_a_player_into_seats_held_by_pending_players_on_a_manual_table(bool $waitingList): void
    {
        $this->setWaitingList($waitingList);
        $table = $this->table(['max_players' => 2, 'auto_confirm' => false]);
        $this->participant($table, 'confirmed', 'player');
        $this->participant($table, 'pending', 'player');
        $waiting = $this->participant($table, 'waiting_list', 'player', ['waiting_list_position' => 1]);

        $component = $this->participants($table)->callTableAction('promote', $waiting);

        if ($waitingList) {
            $component->assertNotified(__('game-tables::messages.errors.table_full'));
            $this->assertSame('waiting_list', $waiting->refresh()->status->value);
        } else {
            $this->assertSame('confirmed', $waiting->refresh()->status->value);
        }
    }

    // Characterization: a pending player confirmed on a manual table only needs a seat among the confirmed
    #[DataProvider('waitingListSetting')]
    public function test_confirming_a_pending_player_who_holds_a_seat(bool $waitingList): void
    {
        $this->setWaitingList($waitingList);
        $table = $this->table(['max_players' => 2, 'auto_confirm' => false]);
        $this->participant($table, 'confirmed', 'player');
        $this->participant($table, 'pending', 'player');
        $pending = $this->participant($table, 'pending', 'player');

        $this->participants($table)->callTableAction('confirm', $pending);

        $this->assertSame('confirmed', $pending->refresh()->status->value);
    }

    /**
     * @return Testable<ParticipantsRelationManager>
     */
    private function participants(GameTableModel $table): Testable
    {
        return Livewire::test(ParticipantsRelationManager::class, ['ownerRecord' => $table, 'pageClass' => EditGameTable::class]);
    }

    private function setWaitingList(bool $enabled): void
    {
        config()->set('modules.settings.game-tables.registration.waiting_list_enabled', $enabled);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function participant(GameTableModel $table, string $status, string $role, array $attributes = []): ParticipantModel
    {
        return ParticipantModel::create([
            'id' => (string) Str::uuid(),
            'game_table_id' => $table->id,
            'user_id' => UserModel::factory()->create()->id,
            'role' => $role,
            'status' => $status,
            'confirmed_at' => $status === 'confirmed' ? now() : null,
            ...$attributes,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function table(array $attributes = []): GameTableModel
    {
        return GameTableModel::create([
            'id' => (string) Str::uuid(),
            'game_system_id' => $this->gameSystem->id,
            'created_by' => UserModel::factory()->create()->id,
            'title' => 'Mesa de prueba',
            'slug' => 'mesa-de-prueba-'.Str::lower(Str::random(6)),
            'starts_at' => now()->addWeek(),
            'duration_minutes' => 240,
            'table_type' => 'one_shot',
            'table_format' => 'in_person',
            'status' => 'scheduled',
            'min_players' => 1,
            'max_players' => 5,
            'max_spectators' => 2,
            'language' => 'es',
            'registration_type' => 'everyone',
            'auto_confirm' => true,
            'is_published' => true,
            ...$attributes,
        ]);
    }
}
