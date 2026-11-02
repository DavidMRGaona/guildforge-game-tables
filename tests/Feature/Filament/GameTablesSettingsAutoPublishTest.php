<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Feature\Filament;

use App\Infrastructure\Persistence\Eloquent\Models\PermissionModel;
use App\Infrastructure\Persistence\Eloquent\Models\RoleModel;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Modules\GameTables\Filament\Pages\GameTablesSettings;
use Modules\GameTables\Infrastructure\Services\GameTableSettingsReader;
use Tests\Support\Modules\InteractsWithModulePanel;
use Tests\Support\Modules\ModuleTestCase;

/**
 * The "by role" publication mode asks which roles and permissions publish without review, so the
 * mode can actually be used from the settings page.
 */
final class GameTablesSettingsAutoPublishTest extends ModuleTestCase
{
    use InteractsWithModulePanel;

    private const string ROLES = 'frontend_creation.publication.auto_publish_roles';

    private const string PERMISSIONS = 'frontend_creation.publication.auto_publish_permissions';

    protected ?string $moduleName = 'game-tables';

    protected bool $autoEnableModule = true;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registerOnAdminPanel([], [GameTablesSettings::class]);
        $this->actingAsPanelAdmin();
        config()->set('modules.settings.game-tables', []);
        RoleModel::create(['name' => 'publishers', 'display_name' => 'Publicadores']);
        PermissionModel::firstOrCreate(
            ['key' => 'gametables:gametables.update'],
            ['label' => 'Editar mesas', 'resource' => 'gametables', 'action' => 'update'],
        );
    }

    public function test_the_role_and_permission_fields_show_only_in_the_by_role_mode(): void
    {
        Livewire::test(GameTablesSettings::class)
            ->fillForm(['frontend_creation.enabled' => true, 'frontend_creation.publication.mode' => 'approval'])
            ->assertFormFieldIsHidden(self::ROLES)
            ->assertFormFieldIsHidden(self::PERMISSIONS)
            ->fillForm(['frontend_creation.publication.mode' => 'role_based'])
            ->assertFormFieldIsVisible(self::ROLES)
            ->assertFormFieldIsVisible(self::PERMISSIONS);
    }

    public function test_the_chosen_roles_and_permissions_are_saved(): void
    {
        $this->save([
            'frontend_creation.publication.mode' => 'role_based',
            self::ROLES => ['publishers'],
            self::PERMISSIONS => ['gametables:gametables.update'],
        ])->assertHasNoFormErrors();

        $settings = new GameTableSettingsReader;
        $this->assertSame('role_based', $settings->getPublicationMode());
        $this->assertSame(['publishers'], $settings->getAutoPublishRoles());
        $this->assertSame(['gametables:gametables.update'], $settings->getAutoPublishPermissions());
    }

    public function test_a_permission_alone_is_enough(): void
    {
        $this->save([
            'frontend_creation.publication.mode' => 'role_based',
            self::ROLES => [],
            self::PERMISSIONS => ['gametables:gametables.update'],
        ])->assertHasNoFormErrors();
    }

    public function test_the_by_role_mode_needs_a_role_or_a_permission(): void
    {
        $this->save([
            'frontend_creation.publication.mode' => 'role_based',
            self::ROLES => [],
            self::PERMISSIONS => [],
        ])->assertHasFormErrors([self::ROLES]);

        $this->assertSame('approval', (new GameTableSettingsReader)->getPublicationMode());
    }

    public function test_unknown_roles_and_permissions_are_refused(): void
    {
        $this->save([
            'frontend_creation.publication.mode' => 'role_based',
            self::ROLES => ['nobody'],
            self::PERMISSIONS => ['gametables:nothing'],
        ])->assertHasFormErrors([self::ROLES.'.0', self::PERMISSIONS.'.0']);
    }

    public function test_other_modes_need_neither(): void
    {
        $this->save([
            'frontend_creation.publication.mode' => 'approval',
        ])->assertHasNoFormErrors();
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
