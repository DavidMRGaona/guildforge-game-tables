<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Feature\Moderation;

use App\Infrastructure\Persistence\Eloquent\Models\PermissionModel;
use App\Infrastructure\Persistence\Eloquent\Models\RoleModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Modules\GameTables\Application\Services\FrontendCreationServiceInterface;
use Modules\GameTables\Domain\Exceptions\NotEditableException;
use Modules\GameTables\Filament\Resources\CampaignResource;
use Modules\GameTables\Filament\Resources\CampaignResource\Pages\ListCampaigns;
use Modules\GameTables\Filament\Resources\GameTableResource;
use Modules\GameTables\Filament\Resources\GameTableResource\Pages\EditGameTable;
use Modules\GameTables\Filament\Resources\GameTableResource\Pages\ListGameTables;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\CampaignModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameSystemModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameTableModel;
use Tests\Support\Modules\InteractsWithModulePanel;
use Tests\Support\Modules\ModuleTestCase;

/**
 * Approving or rejecting needs the update permission of the item, not just seeing the list or being its author.
 */
final class ModerationPermissionTest extends ModuleTestCase
{
    use InteractsWithModulePanel;

    protected ?string $moduleName = 'game-tables';

    protected bool $autoEnableModule = true;

    private GameSystemModel $gameSystem;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registerOnAdminPanel([CampaignResource::class, GameTableResource::class]);
        $this->gameSystem = GameSystemModel::create(['id' => (string) Str::uuid(), 'name' => 'Sistema', 'slug' => 'sistema', 'is_active' => true]);
    }

    public function test_a_campaign_viewer_cannot_approve_or_reject_even_their_own_campaign(): void
    {
        $viewer = $this->editorWith(['gametables:campaigns.view_any']);
        $campaign = $this->campaign($viewer);
        $this->actingAs($viewer);

        Livewire::test(ListCampaigns::class)
            ->assertTableActionHidden('approve', $campaign)
            ->assertTableActionHidden('reject', $campaign);

        $this->assertSame('pending_review', $campaign->refresh()->frontend_creation_status?->value);
    }

    public function test_a_campaign_editor_approves_campaigns(): void
    {
        $this->actingAs($this->editorWith(['gametables:campaigns.view_any', 'gametables:campaigns.update']));
        $campaign = $this->campaign(UserModel::factory()->create());

        Livewire::test(ListCampaigns::class)
            ->assertTableActionVisible('approve', $campaign)
            ->callTableAction('approve', $campaign);

        $this->assertSame('approved', $campaign->refresh()->frontend_creation_status?->value);
    }

    public function test_a_campaign_not_awaiting_review_cannot_be_approved_or_rejected(): void
    {
        $campaign = $this->campaign(UserModel::factory()->create(), 'draft');
        $service = app(FrontendCreationServiceInterface::class);

        try {
            $service->approveCampaign($campaign->id);
            $this->fail('A draft campaign was approved');
        } catch (NotEditableException) {
        }

        $this->expectException(NotEditableException::class);
        $service->rejectCampaign($campaign->id, 'Motivo');
    }

    public function test_a_table_viewer_cannot_approve_or_reject_tables(): void
    {
        $viewer = $this->editorWith(['gametables:gametables.view_any', 'gametables:gametables.view']);
        $table = $this->table($viewer);
        $this->actingAs($viewer);

        Livewire::test(ListGameTables::class)
            ->assertTableActionHidden('approve', $table)
            ->assertTableActionHidden('reject', $table)
            ->assertTableBulkActionHidden('approve')
            ->assertTableBulkActionHidden('reject');
    }

    // The edit page, where the header actions live, stays closed to the author while the table is in review
    public function test_the_author_without_the_permission_cannot_reach_the_moderation_of_the_edit_page(): void
    {
        $author = $this->editorWith(['gametables:gametables.view_any', 'gametables:gametables.view']);
        $table = $this->table($author);
        $this->actingAs($author);

        Livewire::test(EditGameTable::class, ['record' => $table->getRouteKey()])->assertForbidden();
    }

    public function test_a_table_editor_moderates_tables(): void
    {
        $this->actingAs($this->editorWith(['gametables:gametables.view_any', 'gametables:gametables.view', 'gametables:gametables.update']));
        $table = $this->table(UserModel::factory()->create());

        Livewire::test(ListGameTables::class)
            ->assertTableActionVisible('approve', $table)
            ->assertTableActionVisible('reject', $table)
            ->assertTableBulkActionVisible('approve');

        Livewire::test(EditGameTable::class, ['record' => $table->getRouteKey()])
            ->assertActionVisible('approveModeration');
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
                ['label' => $key, 'resource' => Str::between($key, ':', '.'), 'action' => Str::afterLast($key, '.'), 'module' => 'gametables'],
            );
            $role->permissions()->attach($permission->id);
        }

        $user = UserModel::factory()->editor()->create();
        $user->roles()->attach($role->id);

        return $user;
    }

    private function campaign(UserModel $creator, string $creationStatus = 'pending_review'): CampaignModel
    {
        return CampaignModel::create([
            'id' => (string) Str::uuid(),
            'game_system_id' => $this->gameSystem->id,
            'created_by' => $creator->id,
            'title' => 'La llamada',
            'slug' => 'la-llamada',
            'status' => 'recruiting',
            'is_published' => false,
            'frontend_creation_status' => $creationStatus,
        ]);
    }

    private function table(UserModel $creator): GameTableModel
    {
        return GameTableModel::create([
            'id' => (string) Str::uuid(),
            'game_system_id' => $this->gameSystem->id,
            'created_by' => $creator->id,
            'title' => 'Mesa pendiente',
            'slug' => 'mesa-pendiente',
            'starts_at' => now()->addWeek(),
            'duration_minutes' => 240,
            'table_type' => 'one_shot',
            'table_format' => 'in_person',
            'status' => 'draft',
            'min_players' => 1,
            'max_players' => 5,
            'language' => 'es',
            'frontend_creation_status' => 'pending_review',
        ]);
    }
}
