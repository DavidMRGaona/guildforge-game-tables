<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Feature\Http\Controllers;

use App\Infrastructure\Persistence\Eloquent\Models\PermissionModel;
use App\Infrastructure\Persistence\Eloquent\Models\RoleModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Support\Str;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\CampaignModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameSystemModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameTableModel;
use Tests\Support\Modules\ModuleTestCase;

/**
 * From the web, the author edits, submits for review and deletes a table or campaign only while
 * "Mis mesas" / "Mis campañas" offer it: not published, and a draft or rejected. An item published
 * from the panel while still a draft is out of the author's hands; the panel keeps full access.
 */
final class AuthorChangesTest extends ModuleTestCase
{
    protected ?string $moduleName = 'game-tables';

    protected bool $autoEnableModule = true;

    private UserModel $author;

    private GameSystemModel $gameSystem;

    protected function setUp(): void
    {
        parent::setUp();

        app('router')->getRoutes()->refreshNameLookups();
        config()->set('modules.settings.game-tables', [
            'frontend_creation' => [
                'enabled' => true,
                'access_level' => 'registered',
                'allowed_content' => 'both',
                'publication' => ['mode' => 'approval'],
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

    public function test_the_author_still_edits_submits_and_deletes_an_unpublished_draft_table(): void
    {
        $table = $this->table(published: false, status: 'draft');

        $this->actingAs($this->author)
            ->put("/mesas/mis-mesas/{$table->slug}", $this->tableData('Mesa corregida'))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('gametables.my-tables'));
        $this->assertSame('Mesa corregida', $table->refresh()->title);

        $this->actingAs($this->author)
            ->post("/mesas/mis-mesas/{$table->slug}/enviar-revision")
            ->assertRedirect(route('gametables.my-tables'));
        $this->assertSame('pending_review', $table->refresh()->frontend_creation_status?->value);

        $other = $this->table(published: false, status: 'rejected', slug: 'mesa-rechazada');
        $this->actingAs($this->author)
            ->delete("/mesas/mis-mesas/{$other->slug}")
            ->assertRedirect(route('gametables.my-tables'));
        $this->assertNull(GameTableModel::query()->find($other->id));
    }

    public function test_the_author_of_a_published_table_goes_back_to_my_tables_with_the_reason(): void
    {
        $table = $this->table(published: true, status: 'draft');

        $this->assertAuthorSentBackFromEveryRoute($table, 'Esta mesa ya está publicada y ya no se puede modificar.');

        $table->refresh();
        $this->assertSame('Mesa del autor', $table->title);
        $this->assertTrue($table->is_published);
        $this->assertSame('draft', $table->frontend_creation_status?->value);
    }

    public function test_the_author_of_a_table_in_review_goes_back_to_my_tables_with_the_reason(): void
    {
        $table = $this->table(published: false, status: 'pending_review');

        $this->assertAuthorSentBackFromEveryRoute($table, 'Esta mesa ya se ha enviado a revisión y ya no se puede modificar.');

        $table->refresh();
        $this->assertSame('Mesa del autor', $table->title);
        $this->assertSame('pending_review', $table->frontend_creation_status?->value);
    }

    public function test_the_author_of_an_approved_unpublished_table_gets_the_general_reason(): void
    {
        $table = $this->table(published: false, status: 'approved');

        $this->actingAs($this->author)
            ->get("/mesas/mis-mesas/{$table->slug}/editar")
            ->assertRedirect(route('gametables.my-tables'))
            ->assertSessionHas('error', 'Esta mesa ya se ha enviado a revisión o está publicada y ya no se puede modificar.');
    }

    public function test_the_reason_follows_the_locale(): void
    {
        app()->setLocale('en');
        $table = $this->table(published: false, status: 'pending_review');

        $this->actingAs($this->author)
            ->get("/mesas/mis-mesas/{$table->slug}/editar")
            ->assertRedirect(route('gametables.my-tables'))
            ->assertSessionHas('error', 'This table has already been sent for review and can no longer be changed.');
    }

    public function test_anyone_but_the_author_still_gets_forbidden_on_a_table_in_review_or_published(): void
    {
        $other = UserModel::factory()->create();

        foreach ([[false, 'pending_review'], [true, 'draft']] as $i => [$published, $status]) {
            $table = $this->table(published: $published, status: $status, slug: "mesa-ajena-{$i}");

            $this->actingAs($other)->get("/mesas/mis-mesas/{$table->slug}/editar")->assertForbidden();
            $this->actingAs($other)->put("/mesas/mis-mesas/{$table->slug}", $this->tableData('Mesa ajena'))->assertForbidden();
            $this->actingAs($other)->post("/mesas/mis-mesas/{$table->slug}/enviar-revision")->assertForbidden();
            $this->actingAs($other)->delete("/mesas/mis-mesas/{$table->slug}")->assertForbidden();

            $this->assertSame('Mesa del autor', $table->refresh()->title);
        }
    }

    public function test_the_author_still_opens_the_edit_form_of_a_draft(): void
    {
        $table = $this->table(published: false, status: 'draft');

        $this->actingAs($this->author)
            ->get("/mesas/mis-mesas/{$table->slug}/editar")
            ->assertOk()
            ->assertSessionMissing('error');
    }

    public function test_the_author_still_edits_submits_and_deletes_an_unpublished_draft_campaign(): void
    {
        $campaign = $this->campaign(published: false, status: 'draft');

        $this->actingAs($this->author)
            ->put("/campanas/mis-campanas/{$campaign->id}", $this->campaignData('Campaña corregida'))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('campaigns.my-campaigns'));
        $this->assertSame('Campaña corregida', $campaign->refresh()->title);

        $this->actingAs($this->author)
            ->post("/campanas/mis-campanas/{$campaign->id}/enviar-revision")
            ->assertRedirect(route('campaigns.my-campaigns'));
        $this->assertSame('pending_review', $campaign->refresh()->frontend_creation_status?->value);

        $other = $this->campaign(published: false, status: 'rejected', slug: 'campana-rechazada');
        $this->actingAs($this->author)
            ->delete("/campanas/mis-campanas/{$other->id}")
            ->assertRedirect(route('campaigns.my-campaigns'));
        $this->assertNull(CampaignModel::query()->find($other->id));
    }

    public function test_the_author_of_a_published_campaign_goes_back_to_my_campaigns_with_the_reason(): void
    {
        $campaign = $this->campaign(published: true, status: 'draft');

        $this->assertCampaignAuthorSentBackFromEveryRoute($campaign, 'Esta campaña ya está publicada y ya no se puede modificar.');

        $campaign->refresh();
        $this->assertSame('Campaña del autor', $campaign->title);
        $this->assertTrue($campaign->is_published);
        $this->assertSame('draft', $campaign->frontend_creation_status?->value);
    }

    public function test_the_author_of_a_campaign_in_review_goes_back_to_my_campaigns_with_the_reason(): void
    {
        $campaign = $this->campaign(published: false, status: 'pending_review');

        $this->assertCampaignAuthorSentBackFromEveryRoute($campaign, 'Esta campaña ya se ha enviado a revisión y ya no se puede modificar.');

        $campaign->refresh();
        $this->assertSame('Campaña del autor', $campaign->title);
        $this->assertSame('pending_review', $campaign->frontend_creation_status?->value);
    }

    public function test_the_author_of_an_approved_unpublished_campaign_gets_the_general_reason(): void
    {
        $campaign = $this->campaign(published: false, status: 'approved');

        $this->actingAs($this->author)
            ->get("/campanas/mis-campanas/{$campaign->id}/editar")
            ->assertRedirect(route('campaigns.my-campaigns'))
            ->assertSessionHas('error', 'Esta campaña ya se ha enviado a revisión o está publicada y ya no se puede modificar.');
    }

    public function test_the_campaign_reason_follows_the_locale(): void
    {
        app()->setLocale('en');
        $campaign = $this->campaign(published: true, status: 'approved');

        $this->actingAs($this->author)
            ->delete("/campanas/mis-campanas/{$campaign->id}")
            ->assertRedirect(route('campaigns.my-campaigns'))
            ->assertSessionHas('error', 'This campaign is already published and can no longer be changed.');
    }

    public function test_anyone_but_the_author_still_gets_forbidden_on_a_campaign_in_review_or_published(): void
    {
        $other = UserModel::factory()->create();

        foreach ([[false, 'pending_review'], [true, 'draft']] as $i => [$published, $status]) {
            $campaign = $this->campaign(published: $published, status: $status, slug: "campana-ajena-{$i}");

            $this->actingAs($other)->get("/campanas/mis-campanas/{$campaign->id}/editar")->assertForbidden();
            $this->actingAs($other)->put("/campanas/mis-campanas/{$campaign->id}", $this->campaignData('Campaña ajena'))->assertForbidden();
            $this->actingAs($other)->post("/campanas/mis-campanas/{$campaign->id}/enviar-revision")->assertForbidden();
            $this->actingAs($other)->delete("/campanas/mis-campanas/{$campaign->id}")->assertForbidden();

            $this->assertSame('Campaña del autor', $campaign->refresh()->title);
        }
    }

    public function test_the_author_still_opens_the_edit_form_of_a_draft_campaign(): void
    {
        $campaign = $this->campaign(published: false, status: 'draft');

        $this->actingAs($this->author)
            ->get("/campanas/mis-campanas/{$campaign->id}/editar")
            ->assertOk()
            ->assertSessionMissing('error');
    }

    public function test_the_author_policy_follows_the_same_rule(): void
    {
        foreach ([[false, 'draft', true], [false, 'rejected', true], [false, 'pending_review', false], [false, null, false], [true, 'draft', false]] as $i => [$published, $status, $allowed]) {
            $table = $this->table(published: $published, status: $status, slug: "mesa-{$i}");
            $campaign = $this->campaign(published: $published, status: $status, slug: "campana-{$i}");

            $this->assertSame($allowed, $this->author->can('update', $table), "table update {$i}");
            $this->assertSame($allowed, $this->author->can('submitForReview', $table), "table submit {$i}");
            $this->assertSame($allowed, $this->author->can('delete', $table), "table delete {$i}");
            $this->assertSame($allowed, $this->author->can('update', $campaign), "campaign update {$i}");
            $this->assertSame($allowed, $this->author->can('delete', $campaign), "campaign delete {$i}");
        }
    }

    public function test_an_admin_with_the_permission_still_updates_and_deletes_a_published_item(): void
    {
        $admin = $this->userWith([
            'gametables:gametables.update',
            'gametables:gametables.delete',
            'gametables:campaigns.update',
            'gametables:campaigns.delete',
        ]);
        $table = $this->table(published: true, status: 'draft');
        $campaign = $this->campaign(published: true, status: 'draft');

        $this->assertTrue($admin->can('update', $table));
        $this->assertTrue($admin->can('delete', $table));
        $this->assertTrue($admin->can('update', $campaign));
        $this->assertTrue($admin->can('delete', $campaign));
    }

    public function test_a_user_with_the_permission_who_cannot_change_the_table_gets_forbidden_and_nothing_changes(): void
    {
        $editor = $this->userWith(['gametables:gametables.update', 'gametables:gametables.delete']);
        $table = $this->table(published: false, status: 'draft');
        $title = $table->title;

        $this->actingAs($editor)
            ->put("/mesas/mis-mesas/{$table->slug}", $this->tableData('Mesa ajena'))
            ->assertForbidden();
        $this->actingAs($editor)
            ->delete("/mesas/mis-mesas/{$table->slug}")
            ->assertForbidden();

        $this->assertSame($title, $table->refresh()->title);
        $this->assertNotNull(GameTableModel::query()->find($table->id));
    }

    /**
     * Every author route (edit form, save, send to review, delete) sends the author back to
     * «Mis mesas» with the reason, and none of them is forbidden.
     */
    private function assertAuthorSentBackFromEveryRoute(GameTableModel $table, string $reason): void
    {
        $responses = [
            'edit' => $this->actingAs($this->author)->get("/mesas/mis-mesas/{$table->slug}/editar"),
            'update' => $this->actingAs($this->author)->put("/mesas/mis-mesas/{$table->slug}", $this->tableData('Mesa forzada')),
            'submit' => $this->actingAs($this->author)->post("/mesas/mis-mesas/{$table->slug}/enviar-revision"),
            'destroy' => $this->actingAs($this->author)->delete("/mesas/mis-mesas/{$table->slug}"),
        ];

        foreach ($responses as $route => $response) {
            $this->assertSame(302, $response->getStatusCode(), "{$route} status");
            $response->assertRedirect(route('gametables.my-tables'));
            $response->assertSessionHas('error', $reason);
        }

        $this->assertNotNull(GameTableModel::query()->find($table->id));
    }

    /**
     * Every author route of a campaign (edit form, save, send to review, delete) sends the author
     * back to «Mis campañas» with the reason, and none of them is forbidden.
     */
    private function assertCampaignAuthorSentBackFromEveryRoute(CampaignModel $campaign, string $reason): void
    {
        $responses = [
            'edit' => $this->actingAs($this->author)->get("/campanas/mis-campanas/{$campaign->id}/editar"),
            'update' => $this->actingAs($this->author)->put("/campanas/mis-campanas/{$campaign->id}", $this->campaignData('Campaña forzada')),
            'submit' => $this->actingAs($this->author)->post("/campanas/mis-campanas/{$campaign->id}/enviar-revision"),
            'destroy' => $this->actingAs($this->author)->delete("/campanas/mis-campanas/{$campaign->id}"),
        ];

        foreach ($responses as $route => $response) {
            $this->assertSame(302, $response->getStatusCode(), "{$route} status");
            $response->assertRedirect(route('campaigns.my-campaigns'));
            $response->assertSessionHas('error', $reason);
        }

        $this->assertNotNull(CampaignModel::query()->find($campaign->id));
    }

    /**
     * @return array<string, mixed>
     */
    private function tableData(string $title): array
    {
        return [
            'game_system_id' => $this->gameSystem->id,
            'title' => $title,
            'starts_at' => now()->addWeeks(2)->toIso8601String(),
            'duration_minutes' => 240,
            'table_type' => 'one_shot',
            'table_format' => 'in_person',
            'min_players' => 2,
            'max_players' => 5,
            'language' => 'es',
            'experience_level' => 'none',
            'character_creation' => 'pre_generated',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function campaignData(string $title): array
    {
        return [
            'game_system_id' => $this->gameSystem->id,
            'title' => $title,
            'frequency' => 'weekly',
            'min_players' => 2,
            'max_players' => 5,
            'table_format' => 'in_person',
        ];
    }

    /**
     * @param  array<string>  $permissionKeys
     */
    private function userWith(array $permissionKeys): UserModel
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

    private function table(bool $published, ?string $status, string $slug = 'mesa-del-autor'): GameTableModel
    {
        return GameTableModel::create([
            'id' => (string) Str::uuid(),
            'game_system_id' => $this->gameSystem->id,
            'created_by' => $this->author->id,
            'title' => 'Mesa del autor',
            'slug' => $slug,
            'starts_at' => now()->addWeek(),
            'duration_minutes' => 240,
            'table_type' => 'one_shot',
            'table_format' => 'in_person',
            'status' => $published ? 'scheduled' : 'draft',
            'min_players' => 1,
            'max_players' => 5,
            'language' => 'es',
            'registration_type' => 'everyone',
            'auto_confirm' => true,
            'is_published' => $published,
            'frontend_creation_status' => $status,
        ]);
    }

    private function campaign(bool $published, ?string $status, string $slug = 'campana-del-autor'): CampaignModel
    {
        return CampaignModel::create([
            'id' => (string) Str::uuid(),
            'game_system_id' => $this->gameSystem->id,
            'created_by' => $this->author->id,
            'title' => 'Campaña del autor',
            'slug' => $slug,
            'frequency' => 'weekly',
            'status' => 'recruiting',
            'max_players' => 5,
            'is_published' => $published,
            'frontend_creation_status' => $status,
        ]);
    }
}
