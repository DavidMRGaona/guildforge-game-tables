<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Feature\Filament;

use App\Application\Modules\Services\ModuleManagerServiceInterface;
use App\Application\Services\SettingsServiceInterface;
use App\Domain\Modules\ValueObjects\ModuleName;
use App\Filament\Clusters\SettingsCluster;
use App\Infrastructure\Persistence\Eloquent\Models\PermissionModel;
use App\Infrastructure\Persistence\Eloquent\Models\RoleModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Livewire\Livewire;
use Modules\GameTables\Filament\Pages\GameTablesSettings;
use Modules\GameTables\GameTablesServiceProvider;
use Tests\Support\Modules\InteractsWithModulePanel;
use Tests\Support\Modules\ModuleTestCase;

/**
 * The settings live only on the module's page of the settings cluster (core#666).
 */
final class GameTablesSettingsPageDeclarationTest extends ModuleTestCase
{
    use InteractsWithModulePanel;

    protected ?string $moduleName = 'game-tables';

    protected bool $autoEnableModule = true;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registerOnAdminPanel([], [GameTablesSettings::class]);
    }

    public function test_the_provider_declares_the_cluster_page_and_registers_it(): void
    {
        $provider = new GameTablesServiceProvider(app());

        $this->assertSame(GameTablesSettings::class, $provider->getSettingsPage());
        $this->assertContains(GameTablesSettings::class, $provider->registerFilamentPages());
        $this->assertSame(SettingsCluster::class, GameTablesSettings::getCluster());
    }

    public function test_the_generic_settings_schema_is_empty(): void
    {
        $this->assertSame([], (new GameTablesServiceProvider(app()))->getSettingsSchema());
    }

    public function test_modules_configure_alone_is_not_enough(): void
    {
        $this->actingAs($this->editorWith(['modules.configure']));

        $this->assertFalse(GameTablesSettings::canAccess());
        Livewire::test(GameTablesSettings::class)->assertForbidden();
    }

    public function test_saving_without_the_module_permission_is_refused(): void
    {
        $this->actingAsPanelAdmin();
        $page = Livewire::test(GameTablesSettings::class);

        $this->actingAs($this->editorWith(['modules.configure']));

        $page->call('save')->assertForbidden();
    }

    public function test_saved_values_round_trip_through_the_module_settings_row(): void
    {
        $this->actingAsPanelAdmin();

        // The shipped default is false, so only a real save can make this true
        Livewire::test(GameTablesSettings::class)
            ->fillForm(['registration.waiting_list_enabled' => true])
            ->call('save')
            ->assertHasNoFormErrors();

        $stored = json_decode((string) app(SettingsServiceInterface::class)->get('module_settings:game-tables'), true);
        $this->assertTrue($stored['registration']['waiting_list_enabled']);

        $moduleManager = app(ModuleManagerServiceInterface::class);
        $this->assertTrue(data_get($moduleManager->getSettings(new ModuleName('game-tables')), 'registration.waiting_list_enabled'));
    }

    public function test_the_page_shows_values_already_stored_in_the_module_settings_row(): void
    {
        $this->actingAsPanelAdmin();

        // A site configured before the move: the row as the generic page wrote it
        app(SettingsServiceInterface::class)->set('module_settings:game-tables', json_encode([
            'registration' => ['waiting_list_enabled' => true],
            'guest_confirmation' => ['mode' => 'wait', 'hold_hours' => 12],
        ], JSON_THROW_ON_ERROR));

        Livewire::test(GameTablesSettings::class)->assertSchemaStateSet([
            'registration.waiting_list_enabled' => true,
            'guest_confirmation.mode' => 'wait',
            'guest_confirmation.hold_hours' => 12,
        ]);
    }

    /**
     * @param  array<string>  $permissionKeys
     */
    private function editorWith(array $permissionKeys): UserModel
    {
        $role = RoleModel::create(['name' => 'tester_'.uniqid(), 'display_name' => 'Permissions tester']);

        foreach ($permissionKeys as $key) {
            $permission = PermissionModel::firstOrCreate(
                ['key' => $key],
                ['label' => $key, 'resource' => 'modules', 'action' => 'configure'],
            );
            $role->permissions()->attach($permission->id);
        }

        $user = UserModel::factory()->editor()->create();
        $user->roles()->attach($role->id);

        return $user;
    }
}
