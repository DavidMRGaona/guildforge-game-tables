<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Feature\Filament;

use App\Infrastructure\Persistence\Eloquent\Models\PermissionModel;
use App\Infrastructure\Persistence\Eloquent\Models\RoleModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Modules\GameTables\Filament\Resources\CampaignResource;
use Modules\GameTables\Filament\Resources\GameTableResource;
use Modules\GameTables\Filament\Widgets\PendingModerationWidget;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\CampaignModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameSystemModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameTableModel;
use Tests\Support\Modules\InteractsWithModulePanel;
use Tests\Support\Modules\ModuleTestCase;

/**
 * «Pendientes de moderación» shows titles and authors of what waits for review: only to those who
 * may moderate it, and each one only what they may moderate.
 */
final class PendingModerationWidgetTest extends ModuleTestCase
{
    use InteractsWithModulePanel;

    protected ?string $moduleName = 'game-tables';

    protected bool $autoEnableModule = true;

    private GameSystemModel $gameSystem;

    private GameTableModel $table;

    private CampaignModel $campaign;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registerOnAdminPanel([CampaignResource::class, GameTableResource::class], []);
        $author = UserModel::factory()->create();
        $this->gameSystem = GameSystemModel::create([
            'id' => (string) Str::uuid(),
            'name' => 'Sistema de prueba',
            'slug' => 'sistema-de-prueba',
            'is_active' => true,
        ]);
        $this->table = GameTableModel::create([
            'id' => (string) Str::uuid(),
            'game_system_id' => $this->gameSystem->id,
            'created_by' => $author->id,
            'title' => 'Mesa en revisión',
            'slug' => 'mesa-en-revision',
            'starts_at' => now()->addWeek(),
            'duration_minutes' => 240,
            'table_type' => 'one_shot',
            'table_format' => 'in_person',
            'status' => 'draft',
            'min_players' => 2,
            'max_players' => 5,
            'language' => 'es',
            'frontend_creation_status' => 'pending_review',
        ]);
        $this->campaign = CampaignModel::create([
            'id' => (string) Str::uuid(),
            'game_system_id' => $this->gameSystem->id,
            'created_by' => $author->id,
            'title' => 'Campaña en revisión',
            'slug' => 'campana-en-revision',
            'status' => 'recruiting',
            'frontend_creation_status' => 'pending_review',
        ]);
    }

    public function test_someone_who_cannot_moderate_does_not_see_it(): void
    {
        $this->actingAs($this->editorWith(['gametables:gametables.view_any', 'gametables:campaigns.view_any']));

        $this->assertFalse(PendingModerationWidget::canView());
    }

    public function test_a_table_moderator_sees_only_the_tables(): void
    {
        $this->actingAs($this->editorWith(['gametables:gametables.update']));

        $this->assertTrue(PendingModerationWidget::canView());
        Livewire::test(PendingModerationWidget::class)
            ->assertCanSeeTableRecords([$this->table])
            ->assertCanNotSeeTableRecords([$this->campaign]);
    }

    public function test_a_campaign_moderator_sees_only_the_campaigns(): void
    {
        $this->actingAs($this->editorWith(['gametables:campaigns.update']));

        $this->assertTrue(PendingModerationWidget::canView());
        Livewire::test(PendingModerationWidget::class)
            ->assertCanSeeTableRecords([$this->campaign])
            ->assertCanNotSeeTableRecords([$this->table]);
    }

    // Something waits, but nothing this person may moderate
    public function test_it_stays_hidden_when_nothing_pending_is_for_this_moderator(): void
    {
        $this->campaign->update(['frontend_creation_status' => 'approved']);
        $this->actingAs($this->editorWith(['gametables:campaigns.update']));

        $this->assertFalse(PendingModerationWidget::canView());
    }

    public function test_an_admin_sees_both(): void
    {
        $this->actingAsPanelAdmin();

        $this->assertTrue(PendingModerationWidget::canView());
        Livewire::test(PendingModerationWidget::class)
            ->assertCanSeeTableRecords([$this->table, $this->campaign]);
    }

    /**
     * @param  list<string>  $permissionKeys
     */
    private function editorWith(array $permissionKeys): UserModel
    {
        $role = RoleModel::create(['name' => 'tester_'.uniqid(), 'display_name' => 'Permissions tester']);

        foreach ($permissionKeys as $key) {
            $permission = PermissionModel::firstOrCreate(
                ['key' => $key],
                ['label' => $key, 'resource' => 'gametables', 'action' => Str::afterLast($key, '.'), 'module' => 'gametables'],
            );
            $role->permissions()->attach($permission->id);
        }

        $user = UserModel::factory()->editor()->create();
        $user->roles()->attach($role->id);

        return $user;
    }
}
