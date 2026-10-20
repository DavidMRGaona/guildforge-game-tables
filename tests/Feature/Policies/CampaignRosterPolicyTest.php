<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Feature\Policies;

use App\Infrastructure\Persistence\Eloquent\Models\PermissionModel;
use App\Infrastructure\Persistence\Eloquent\Models\RoleModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Support\Str;
use Modules\GameTables\Domain\Enums\FrontendCreationStatus;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\CampaignModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameSystemModel;
use Tests\Support\Modules\ModuleTestCase;

final class CampaignRosterPolicyTest extends ModuleTestCase
{
    protected ?string $moduleName = 'game-tables';

    protected bool $autoEnableModule = true;

    private function campaign(UserModel $creator, FrontendCreationStatus $status): CampaignModel
    {
        $system = GameSystemModel::create(['id' => (string) Str::uuid(), 'name' => 'Sistema', 'slug' => Str::random(10), 'is_active' => true]);

        return CampaignModel::create([
            'id' => (string) Str::uuid(),
            'game_system_id' => $system->id,
            'created_by' => $creator->id,
            'title' => 'Campaña',
            'slug' => Str::random(10),
            'status' => 'recruiting',
            'frontend_creation_status' => $status,
        ]);
    }

    public function test_a_director_manages_the_roster_of_an_approved_campaign(): void
    {
        $john = UserModel::factory()->create();

        $this->assertTrue($john->can('manageRoster', $this->campaign($john, FrontendCreationStatus::Approved)));
    }

    public function test_a_director_cannot_manage_the_roster_of_a_draft(): void
    {
        $john = UserModel::factory()->create();

        $this->assertFalse($john->can('manageRoster', $this->campaign($john, FrontendCreationStatus::Draft)));
    }

    public function test_staff_permissions_do_not_grant_roster_management(): void
    {
        $john = UserModel::factory()->create();
        $campaign = $this->campaign($john, FrontendCreationStatus::Approved);

        $role = RoleModel::create(['name' => 'tester_'.uniqid(), 'display_name' => 'Permissions tester']);
        $permission = PermissionModel::firstOrCreate(
            ['key' => 'gametables:campaigns.update'],
            ['label' => 'Update', 'resource' => 'campaigns', 'action' => 'update', 'module' => 'gametables'],
        );
        $role->permissions()->attach($permission->id);
        $staff = UserModel::factory()->editor()->create();
        $staff->roles()->attach($role->id);

        $this->assertTrue($staff->can('update', $campaign));
        $this->assertFalse($staff->can('manageRoster', $campaign));
    }
}
