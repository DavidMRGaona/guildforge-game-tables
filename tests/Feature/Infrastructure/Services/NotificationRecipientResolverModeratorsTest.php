<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Feature\Infrastructure\Services;

use App\Infrastructure\Persistence\Eloquent\Models\PermissionModel;
use App\Infrastructure\Persistence\Eloquent\Models\RoleModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Modules\GameTables\Infrastructure\Services\NotificationRecipientResolver;
use Tests\TestCase;

/**
 * Moderation emails reach whoever the panel lets approve or reject, whatever their role is called.
 */
final class NotificationRecipientResolverModeratorsTest extends TestCase
{
    use RefreshDatabase;

    private const string TABLES_UPDATE = 'gametables:gametables.update';

    private const string CAMPAIGNS_UPDATE = 'gametables:campaigns.update';

    private NotificationRecipientResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->resolver = new NotificationRecipientResolver;
    }

    public function test_a_custom_role_with_panel_access_and_the_update_permission_receives_them(): void
    {
        $tableModerator = $this->userWithRole(['admin.access', self::TABLES_UPDATE]);
        $campaignModerator = $this->userWithRole(['admin.access', self::CAMPAIGNS_UPDATE]);

        $this->assertSame([$tableModerator->email], $this->resolver->getTableModeratorEmails());
        $this->assertSame([$campaignModerator->email], $this->resolver->getCampaignModeratorEmails());
    }

    public function test_panel_access_and_the_update_permission_may_come_from_different_roles(): void
    {
        $moderator = $this->userWithRole(['admin.access']);
        $moderator->roles()->attach($this->role([self::TABLES_UPDATE])->id);

        $this->assertSame([$moderator->email], $this->resolver->getTableModeratorEmails());
    }

    public function test_an_editor_without_the_update_permission_receives_nothing(): void
    {
        UserModel::factory()->editor()->create();

        $this->assertSame([], $this->resolver->getTableModeratorEmails());
        $this->assertSame([], $this->resolver->getCampaignModeratorEmails());
    }

    public function test_the_update_permission_without_panel_access_receives_nothing(): void
    {
        $this->userWithRole([self::TABLES_UPDATE, self::CAMPAIGNS_UPDATE]);

        $this->assertSame([], $this->resolver->getTableModeratorEmails());
        $this->assertSame([], $this->resolver->getCampaignModeratorEmails());
    }

    public function test_the_tables_permission_does_not_reach_campaigns_nor_the_other_way_round(): void
    {
        $tableModerator = $this->userWithRole(['admin.access', self::TABLES_UPDATE]);
        $campaignModerator = $this->userWithRole(['admin.access', self::CAMPAIGNS_UPDATE]);

        $this->assertNotContains($tableModerator->email, $this->resolver->getCampaignModeratorEmails());
        $this->assertNotContains($campaignModerator->email, $this->resolver->getTableModeratorEmails());
    }

    public function test_an_admin_receives_both(): void
    {
        $admin = UserModel::factory()->admin()->create();

        $this->assertSame([$admin->email], $this->resolver->getTableModeratorEmails());
        $this->assertSame([$admin->email], $this->resolver->getCampaignModeratorEmails());
    }

    public function test_an_account_without_roles_receives_nothing(): void
    {
        UserModel::factory()->create();

        $this->assertSame([], $this->resolver->getTableModeratorEmails());
    }

    /**
     * @param  list<string>  $permissionKeys
     */
    private function userWithRole(array $permissionKeys): UserModel
    {
        $user = UserModel::factory()->create();
        $user->roles()->attach($this->role($permissionKeys)->id);

        return $user;
    }

    /**
     * @param  list<string>  $permissionKeys
     */
    private function role(array $permissionKeys): RoleModel
    {
        $role = RoleModel::create(['name' => 'moderator_'.Str::lower(Str::random(8)), 'display_name' => 'Moderator']);

        foreach ($permissionKeys as $key) {
            $permission = PermissionModel::firstOrCreate(
                ['key' => $key],
                ['label' => $key, 'resource' => Str::before(Str::afterLast($key, ':'), '.'), 'action' => Str::afterLast($key, '.')],
            );
            $role->permissions()->attach($permission->id);
        }

        return $role;
    }
}
