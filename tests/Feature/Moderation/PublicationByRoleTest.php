<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Feature\Moderation;

use App\Infrastructure\Persistence\Eloquent\Models\PermissionModel;
use App\Infrastructure\Persistence\Eloquent\Models\RoleModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Modules\GameTables\Application\DTOs\FrontendCreateCampaignDTO;
use Modules\GameTables\Application\Services\FrontendCreationServiceInterface;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\CampaignModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameSystemModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameTableModel;
use Tests\Support\Modules\ModuleTestCase;

/**
 * In the "by role" publication mode, what the holders of the chosen roles or permissions create on
 * the web is published without review; everybody else's goes to review. The chosen roles count in
 * that mode only.
 */
final class PublicationByRoleTest extends ModuleTestCase
{
    protected ?string $moduleName = 'game-tables';

    protected bool $autoEnableModule = true;

    private UserModel $author;

    private GameSystemModel $gameSystem;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        config()->set('modules.settings.game-tables', [
            'frontend_creation' => [
                'enabled' => true,
                'access_level' => 'registered',
                'allowed_content' => 'both',
                'publication' => [
                    'mode' => 'role_based',
                    'auto_publish_roles' => ['publishers'],
                    'auto_publish_permissions' => ['gametables:publish_freely'],
                ],
            ],
        ]);

        $this->author = UserModel::factory()->create();
        $this->gameSystem = GameSystemModel::create([
            'id' => (string) Str::uuid(),
            'name' => 'Sistema de prueba',
            'slug' => 'sistema-de-prueba',
            'is_active' => true,
        ]);
    }

    public function test_a_table_sent_by_a_holder_of_a_chosen_role_is_published(): void
    {
        $this->giveRole('publishers');
        $table = $this->table();

        $this->actingAs($this->author)->post("/mesas/mis-mesas/{$table->slug}/enviar-revision")->assertRedirect();

        $table->refresh();
        $this->assertSame('approved', $table->frontend_creation_status?->value);
        $this->assertTrue($table->is_published);
    }

    public function test_a_campaign_sent_by_a_holder_of_a_chosen_permission_is_published(): void
    {
        $this->giveRole('others', 'gametables:publish_freely');
        $campaign = $this->campaign();

        app(FrontendCreationServiceInterface::class)->submitCampaignForReview($campaign->id, $this->author->id);

        $this->assertTrue($campaign->refresh()->is_published);
        $this->assertSame('approved', $campaign->frontend_creation_status?->value);
    }

    public function test_a_campaign_created_by_a_holder_of_a_chosen_role_is_published_at_once(): void
    {
        $this->giveRole('publishers');

        $created = app(FrontendCreationServiceInterface::class)->createCampaign($this->campaignDto());

        $campaign = CampaignModel::query()->findOrFail($created->id);
        $this->assertTrue($campaign->is_published);
        $this->assertSame('approved', $campaign->frontend_creation_status?->value);
    }

    public function test_what_others_send_goes_to_review(): void
    {
        $this->giveRole('others');
        $table = $this->table();
        $campaign = $this->campaign();

        $this->actingAs($this->author)->post("/mesas/mis-mesas/{$table->slug}/enviar-revision")->assertRedirect();
        app(FrontendCreationServiceInterface::class)->submitCampaignForReview($campaign->id, $this->author->id);

        $this->assertSame('pending_review', $table->refresh()->frontend_creation_status?->value);
        $this->assertFalse($table->is_published);
        $this->assertSame('pending_review', $campaign->refresh()->frontend_creation_status?->value);
        $this->assertFalse($campaign->is_published);
    }

    public function test_the_chosen_roles_count_only_in_the_by_role_mode(): void
    {
        // Roles chosen earlier stay stored when the mode changes back to approval
        config()->set('modules.settings.game-tables.frontend_creation.publication.mode', 'approval');
        $this->giveRole('publishers', 'gametables:publish_freely');
        $table = $this->table();
        $campaign = $this->campaign();

        $this->actingAs($this->author)->post("/mesas/mis-mesas/{$table->slug}/enviar-revision")->assertRedirect();
        app(FrontendCreationServiceInterface::class)->submitCampaignForReview($campaign->id, $this->author->id);
        $created = app(FrontendCreationServiceInterface::class)->createCampaign($this->campaignDto());

        $this->assertSame('pending_review', $table->refresh()->frontend_creation_status?->value);
        $this->assertSame('pending_review', $campaign->refresh()->frontend_creation_status?->value);
        $this->assertSame('draft', CampaignModel::query()->findOrFail($created->id)->frontend_creation_status?->value);
    }

    private function giveRole(string $name, ?string $permission = null): void
    {
        $role = RoleModel::create(['name' => $name, 'display_name' => Str::headline($name)]);

        if ($permission !== null) {
            $role->permissions()->attach(PermissionModel::firstOrCreate(
                ['key' => $permission],
                ['label' => $permission, 'resource' => 'gametables', 'action' => Str::afterLast($permission, ':')],
            )->id);
        }

        $this->author->roles()->attach($role->id);
    }

    private function campaignDto(): FrontendCreateCampaignDTO
    {
        return FrontendCreateCampaignDTO::fromArray([
            'created_by' => $this->author->id,
            'game_system_id' => $this->gameSystem->id,
            'title' => 'Campaña nueva',
            'frequency' => 'weekly',
            'min_players' => 2,
            'max_players' => 5,
            'table_format' => 'in_person',
        ]);
    }

    private function table(): GameTableModel
    {
        return GameTableModel::create([
            'id' => (string) Str::uuid(),
            'game_system_id' => $this->gameSystem->id,
            'created_by' => $this->author->id,
            'title' => 'Mesa del autor',
            'slug' => 'mesa-del-autor',
            'starts_at' => now()->addWeek(),
            'duration_minutes' => 240,
            'table_type' => 'one_shot',
            'table_format' => 'in_person',
            'status' => 'draft',
            'min_players' => 1,
            'max_players' => 5,
            'language' => 'es',
            'experience_level' => 'none',
            'character_creation' => 'pre_generated',
            'registration_type' => 'everyone',
            'auto_confirm' => true,
            'is_published' => false,
            'frontend_creation_status' => 'draft',
        ]);
    }

    private function campaign(): CampaignModel
    {
        return CampaignModel::create([
            'id' => (string) Str::uuid(),
            'game_system_id' => $this->gameSystem->id,
            'created_by' => $this->author->id,
            'title' => 'Campaña del autor',
            'slug' => 'campana-del-autor',
            'status' => 'recruiting',
            'is_published' => false,
            'frontend_creation_status' => 'draft',
        ]);
    }
}
