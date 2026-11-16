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
use Tests\Support\Modules\InteractsWithModulePanel;
use Tests\Support\Modules\ModuleTestCase;

/**
 * The panel's participants list names each registration as staff know it: the account name of a linked
 * account (never the public name the site shows), or the guest's name as written; it searches and sorts by
 * that same name.
 */
final class ParticipantsNameColumnTest extends ModuleTestCase
{
    use InteractsWithModulePanel;

    protected ?string $moduleName = 'game-tables';

    protected bool $autoEnableModule = true;

    private GameTableModel $table;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registerOnAdminPanel([GameTableResource::class]);
        $this->actingAsPanelAdmin();
        config()->set('modules.settings.game-tables', []);
        $system = GameSystemModel::create(['id' => (string) Str::uuid(), 'name' => 'Sistema', 'slug' => 'sistema', 'is_active' => true]);
        $this->table = GameTableModel::create([
            'id' => (string) Str::uuid(),
            'game_system_id' => $system->id,
            'created_by' => UserModel::factory()->create()->id,
            'title' => 'Mesa de prueba',
            'slug' => 'mesa-de-prueba',
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
        ]);
    }

    public function test_a_linked_account_is_named_by_its_account_name_and_a_guest_by_the_written_name(): void
    {
        $account = $this->accountRow('Ana Account', 'Ana Reader');
        $guest = $this->guestRow('Janie', 'Roe');

        $this->participants()
            ->assertTableColumnStateSet('display_name', 'Ana Account', $account)
            ->assertTableColumnStateNotSet('display_name', 'Ana Reader', $account)
            ->assertTableColumnStateSet('display_name', 'Janie Roe', $guest);
    }

    public function test_searching_finds_a_registration_by_the_account_name(): void
    {
        $account = $this->accountRow('Ana Account', 'Ana Reader');
        $other = $this->accountRow('Bob Account', 'Bob Reader');

        $this->participants()
            ->searchTable('Ana Account')
            ->assertCanSeeTableRecords([$account])
            ->assertCanNotSeeTableRecords([$other]);
    }

    public function test_sorting_by_name_orders_accounts_and_guests_by_the_name_shown(): void
    {
        $carla = $this->accountRow('Carla Account', 'Aaron Reader');
        $ana = $this->accountRow('Ana Account', 'Zoe Reader');
        $bruno = $this->guestRow('Bruno', 'Roe');

        $this->participants()
            ->sortTable('display_name')
            ->assertCanSeeTableRecords([$ana, $bruno, $carla], inOrder: true)
            ->sortTable('display_name', 'desc')
            ->assertCanSeeTableRecords([$carla, $bruno, $ana], inOrder: true);
    }

    /**
     * @return Testable<ParticipantsRelationManager>
     */
    private function participants(): Testable
    {
        return Livewire::test(ParticipantsRelationManager::class, ['ownerRecord' => $this->table, 'pageClass' => EditGameTable::class]);
    }

    private function accountRow(string $name, string $publicName): ParticipantModel
    {
        return ParticipantModel::create([
            'id' => (string) Str::uuid(),
            'game_table_id' => $this->table->id,
            'user_id' => UserModel::factory()->create(['name' => $name, 'display_name' => $publicName])->id,
            'role' => 'player',
            'status' => 'confirmed',
            'confirmed_at' => now(),
        ]);
    }

    private function guestRow(string $firstName, string $lastName): ParticipantModel
    {
        return ParticipantModel::create([
            'id' => (string) Str::uuid(),
            'game_table_id' => $this->table->id,
            'user_id' => null,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => Str::lower($firstName).'@example.com',
            'role' => 'player',
            'status' => 'confirmed',
            'confirmed_at' => now(),
            'email_confirmed_at' => now(),
        ]);
    }
}
