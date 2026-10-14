<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Feature\Policies;

use App\Infrastructure\Persistence\Eloquent\Models\PermissionModel;
use App\Infrastructure\Persistence\Eloquent\Models\RoleModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Modules\GameTables\Filament\Resources\GameSystemResource;
use Modules\GameTables\Filament\Resources\PublisherResource;
use Modules\GameTables\Filament\Resources\PublisherResource\Pages\ListPublishers;
use Modules\GameTables\GameTablesServiceProvider;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\PublisherModel;
use Modules\GameTables\Policies\PublisherPolicy;
use Tests\Support\Modules\InteractsWithModulePanel;
use Tests\Support\Modules\ModuleTestCase;

/**
 * Publishers are a panel catalog next to game systems, under the same permissions.
 */
final class PublisherAccessTest extends ModuleTestCase
{
    use InteractsWithModulePanel;

    protected ?string $moduleName = 'game-tables';

    protected bool $autoEnableModule = true;

    public function test_the_publisher_resource_is_registered_on_the_panel(): void
    {
        $provider = new GameTablesServiceProvider(app());

        $this->assertContains(PublisherResource::class, $provider->registerFilamentResources());
        $this->assertSame(PublisherPolicy::class, $provider->registerPolicies()[PublisherModel::class] ?? null);
    }

    public function test_publishers_share_the_navigation_group_of_game_systems(): void
    {
        $this->assertSame(GameSystemResource::getNavigationGroup(), PublisherResource::getNavigationGroup());
    }

    public function test_viewing_publishers_needs_the_game_systems_view_permission(): void
    {
        $this->assertTrue($this->userWith(['gametables:gamesystems.view_any'])->can('viewAny', PublisherModel::class));
        $this->assertTrue($this->userWith(['gametables:gamesystems.view_any'])->can('view', $this->publisher()));
        $this->assertFalse($this->userWith([])->can('viewAny', PublisherModel::class));
        $this->assertFalse($this->userWith(['gametables:gamesystems.view_any'])->can('create', PublisherModel::class));
    }

    public function test_managing_publishers_needs_the_game_systems_manage_permission(): void
    {
        $manager = $this->userWith(['gametables:gamesystems.manage']);
        $publisher = $this->publisher();

        $this->assertTrue($manager->can('create', PublisherModel::class));
        $this->assertTrue($manager->can('update', $publisher));
        $this->assertTrue($manager->can('delete', $publisher));
        $this->assertTrue($manager->can('deleteAny', PublisherModel::class));
        $this->assertFalse($this->userWith([])->can('update', $publisher));
        $this->assertFalse($this->userWith([])->can('delete', $publisher));
    }

    public function test_an_editor_without_the_permission_cannot_open_the_publisher_list(): void
    {
        $this->registerOnAdminPanel([PublisherResource::class]);

        $this->actingAs($this->userWith([]));
        Livewire::test(ListPublishers::class)->assertForbidden();
    }

    public function test_an_editor_granted_the_permission_opens_the_publisher_list(): void
    {
        $this->registerOnAdminPanel([PublisherResource::class]);

        $this->actingAs($this->userWith(['gametables:gamesystems.view_any']));
        Livewire::test(ListPublishers::class)->assertOk();
    }

    /**
     * An editor (panel access) with an extra role holding exactly the given permission keys.
     *
     * @param  array<string>  $permissionKeys
     */
    private function userWith(array $permissionKeys): UserModel
    {
        $role = RoleModel::create([
            'name' => 'tester_'.uniqid(),
            'display_name' => 'Permissions tester',
        ]);

        foreach ($permissionKeys as $key) {
            $permission = PermissionModel::firstOrCreate(
                ['key' => $key],
                ['label' => $key, 'resource' => 'gamesystems', 'action' => Str::afterLast($key, '.'), 'module' => 'gametables'],
            );
            $role->permissions()->attach($permission->id);
        }

        $user = UserModel::factory()->editor()->create();
        $user->roles()->attach($role->id);

        return $user;
    }

    private function publisher(): PublisherModel
    {
        return PublisherModel::create([
            'id' => (string) Str::uuid(),
            'name' => 'Editorial de prueba',
            'slug' => 'editorial-de-prueba',
            'is_active' => true,
        ]);
    }
}
