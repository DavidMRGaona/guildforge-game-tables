<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Feature\Filament;

use App\Application\Authorization\Services\PermissionRegistryInterface;
use App\Infrastructure\Persistence\Eloquent\Models\PermissionModel;
use App\Infrastructure\Persistence\Eloquent\Models\RoleModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Livewire\Livewire;
use Modules\GameTables\Filament\Pages\GameTablesSettings;
use Modules\GameTables\GameTablesServiceProvider;
use Tests\Support\Modules\InteractsWithModulePanel;
use Tests\Support\Modules\ModuleTestCase;

/**
 * The settings page opens with the gametables.settings permission; admins have it by default.
 */
final class GameTablesSettingsAccessTest extends ModuleTestCase
{
    use InteractsWithModulePanel;

    private const string PERMISSION = 'gametables:gametables.settings';

    protected ?string $moduleName = 'game-tables';

    protected bool $autoEnableModule = true;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registerOnAdminPanel([], [GameTablesSettings::class]);
    }

    public function test_the_permission_is_registered_and_proposed_to_no_role(): void
    {
        $this->assertTrue(app(PermissionRegistryInterface::class)->has(self::PERMISSION));

        $settings = collect((new GameTablesServiceProvider(app()))->registerPermissions())
            ->firstWhere('name', 'gametables.settings');
        $this->assertSame([], $settings?->roles);
    }

    public function test_an_admin_opens_the_settings_page(): void
    {
        $this->actingAsPanelAdmin();

        $this->assertTrue(GameTablesSettings::canAccess());
        Livewire::test(GameTablesSettings::class)->assertOk();
    }

    public function test_an_editor_granted_the_permission_opens_the_settings_page(): void
    {
        $this->actingAs($this->editorWith([self::PERMISSION]));

        $this->assertTrue(GameTablesSettings::canAccess());
        Livewire::test(GameTablesSettings::class)->assertOk();
    }

    public function test_an_editor_without_the_permission_cannot_open_the_settings_page(): void
    {
        $this->actingAs($this->editorWith([]));

        $this->assertFalse(GameTablesSettings::canAccess());
        Livewire::test(GameTablesSettings::class)->assertForbidden();
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
                ['label' => $key, 'resource' => 'gametables', 'action' => 'settings', 'module' => 'gametables'],
            );
            $role->permissions()->attach($permission->id);
        }

        $user = UserModel::factory()->editor()->create();
        $user->roles()->attach($role->id);

        return $user;
    }
}
