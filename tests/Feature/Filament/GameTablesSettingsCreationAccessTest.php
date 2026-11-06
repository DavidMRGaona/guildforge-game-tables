<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Feature\Filament;

use App\Infrastructure\Persistence\Eloquent\Models\PermissionModel;
use App\Infrastructure\Persistence\Eloquent\Models\RoleModel;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Modules\GameTables\Domain\Enums\CreationAccessLevel;
use Modules\GameTables\Filament\Pages\GameTablesSettings;
use Modules\GameTables\Infrastructure\Services\GameTableSettingsReader;
use Tests\Support\Modules\InteractsWithModulePanel;
use Tests\Support\Modules\ModuleTestCase;

/**
 * Who may create from the web: roles and the permission are picked from lists, not typed as internal
 * names, and "anyone" is gone, since creating always needs a session.
 */
final class GameTablesSettingsCreationAccessTest extends ModuleTestCase
{
    use InteractsWithModulePanel;

    private const string LEVEL = 'frontend_creation.access_level';

    private const string ROLES = 'frontend_creation.allowed_roles';

    private const string PERMISSION = 'frontend_creation.required_permission';

    protected ?string $moduleName = 'game-tables';

    protected bool $autoEnableModule = true;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registerOnAdminPanel([], [GameTablesSettings::class]);
        $this->actingAsPanelAdmin();
        config()->set('modules.settings.game-tables', []);
        RoleModel::create(['name' => 'organizers', 'display_name' => 'Organización']);
        PermissionModel::firstOrCreate(
            ['key' => 'gametables:gametables.create'],
            ['label' => 'Crear mesas', 'resource' => 'gametables', 'action' => 'create'],
        );
    }

    public function test_anyone_is_no_longer_offered(): void
    {
        $this->save([self::LEVEL => 'everyone'])->assertHasFormErrors([self::LEVEL]);
    }

    public function test_a_stored_anyone_opens_as_registered_users(): void
    {
        config()->set('modules.settings.game-tables.frontend_creation', ['enabled' => true, 'access_level' => 'everyone']);

        Livewire::test(GameTablesSettings::class)->assertSchemaStateSet([self::LEVEL => 'registered']);
        $this->assertSame(CreationAccessLevel::Registered, (new GameTableSettingsReader)->getAccessLevel());
    }

    public function test_roles_are_chosen_by_name_and_saved_as_a_list(): void
    {
        $this->save([self::LEVEL => 'role', self::ROLES => ['organizers']])->assertHasNoFormErrors();

        $this->assertSame(['organizers'], (new GameTableSettingsReader)->getAllowedRoles());
    }

    public function test_roles_typed_before_are_kept_when_they_still_exist(): void
    {
        config()->set('modules.settings.game-tables.frontend_creation', [
            'enabled' => true,
            'access_level' => 'role',
            'allowed_roles' => 'organizers, nobody-has-this-role',
        ]);

        Livewire::test(GameTablesSettings::class)->assertSchemaStateSet([self::ROLES => ['organizers']]);
    }

    public function test_the_permission_is_chosen_from_the_registered_ones(): void
    {
        $this->save([self::LEVEL => 'permission', self::PERMISSION => 'gametables:gametables.create'])->assertHasNoFormErrors();
        $this->assertSame('gametables:gametables.create', (new GameTableSettingsReader)->getRequiredPermission());

        $this->save([self::LEVEL => 'permission', self::PERMISSION => 'made:up.permission'])->assertHasFormErrors([self::PERMISSION]);
    }

    /**
     * @param  array<string, mixed>  $values
     * @return Testable<GameTablesSettings>
     */
    private function save(array $values): Testable
    {
        return Livewire::test(GameTablesSettings::class)
            ->fillForm(['frontend_creation.enabled' => true, ...$values])
            ->call('save');
    }
}
