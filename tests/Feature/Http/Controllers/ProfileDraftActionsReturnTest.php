<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Feature\Http\Controllers;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Support\Str;
use Modules\GameTables\Application\Services\FrontendCreationServiceInterface;
use Modules\GameTables\Domain\Exceptions\SessionBelowRosterException;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\CampaignModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameSystemModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameTableModel;
use Tests\Support\Modules\ModuleTestCase;

/**
 * The profile's drafts offer the same actions as «Mis mesas» / «Mis campañas». Sending a draft to
 * review or deleting it from there lands back on the profile (the page keeps its tab) with the
 * flash message; only the whitelisted `return_to=profile` does so, anything else keeps the usual
 * redirect.
 */
final class ProfileDraftActionsReturnTest extends ModuleTestCase
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

    public function test_a_table_sent_to_review_from_the_profile_returns_to_the_profile(): void
    {
        $table = $this->table(status: 'draft');

        $this->actingAs($this->author)
            ->post("/mesas/mis-mesas/{$table->slug}/enviar-revision?return_to=profile")
            ->assertRedirect(route('profile.show'))
            ->assertSessionHas('success', 'Mesa enviada para revisión');

        $this->assertSame('pending_review', $table->refresh()->frontend_creation_status?->value);
    }

    public function test_the_return_target_may_also_come_in_the_body(): void
    {
        $table = $this->table(status: 'draft');

        $this->actingAs($this->author)
            ->post("/mesas/mis-mesas/{$table->slug}/enviar-revision", ['return_to' => 'profile'])
            ->assertRedirect(route('profile.show'));
    }

    public function test_a_table_deleted_from_the_profile_returns_to_the_profile(): void
    {
        $table = $this->table(status: 'draft');

        $this->actingAs($this->author)
            ->delete("/mesas/mis-mesas/{$table->slug}?return_to=profile")
            ->assertRedirect(route('profile.show'))
            ->assertSessionHas('success', 'Mesa eliminada correctamente');

        $this->assertNull(GameTableModel::query()->find($table->id));
    }

    public function test_a_campaign_sent_to_review_from_the_profile_returns_to_the_profile(): void
    {
        $campaign = $this->campaign(status: 'draft');

        $this->actingAs($this->author)
            ->post("/campanas/mis-campanas/{$campaign->id}/enviar-revision?return_to=profile")
            ->assertRedirect(route('profile.show'))
            ->assertSessionHas('success', 'Campaña enviada para revisión');

        $this->assertSame('pending_review', $campaign->refresh()->frontend_creation_status?->value);
    }

    public function test_a_campaign_deleted_from_the_profile_returns_to_the_profile(): void
    {
        $campaign = $this->campaign(status: 'draft');

        $this->actingAs($this->author)
            ->delete("/campanas/mis-campanas/{$campaign->id}?return_to=profile")
            ->assertRedirect(route('profile.show'))
            ->assertSessionHas('success', 'Campaña eliminada correctamente');

        $this->assertNull(CampaignModel::query()->find($campaign->id));
    }

    public function test_without_a_return_target_the_actions_keep_landing_on_my_tables_and_my_campaigns(): void
    {
        $table = $this->table(status: 'draft');
        $campaign = $this->campaign(status: 'draft');

        $this->actingAs($this->author)
            ->post("/mesas/mis-mesas/{$table->slug}/enviar-revision")
            ->assertRedirect(route('gametables.my-tables'));
        $this->actingAs($this->author)
            ->post("/campanas/mis-campanas/{$campaign->id}/enviar-revision")
            ->assertRedirect(route('campaigns.my-campaigns'));
    }

    public function test_an_unknown_return_target_is_ignored(): void
    {
        foreach (['https://example.com/perfil', '/perfil', 'admin', 'Profile'] as $index => $target) {
            $table = $this->table(status: 'draft', slug: "mesa-{$index}");
            $campaign = $this->campaign(status: 'draft', slug: "campana-{$index}");

            $this->actingAs($this->author)
                ->delete("/mesas/mis-mesas/{$table->slug}", ['return_to' => $target])
                ->assertRedirect(route('gametables.my-tables'));
            $this->actingAs($this->author)
                ->delete("/campanas/mis-campanas/{$campaign->id}", ['return_to' => $target])
                ->assertRedirect(route('campaigns.my-campaigns'));
        }

        // Not a string at all
        $table = $this->table(status: 'draft', slug: 'mesa-array');
        $this->actingAs($this->author)
            ->delete("/mesas/mis-mesas/{$table->slug}", ['return_to' => ['profile']])
            ->assertRedirect(route('gametables.my-tables'));
    }

    public function test_the_locked_author_returns_to_the_profile_with_the_reason(): void
    {
        $table = $this->table(status: 'pending_review');
        $campaign = $this->campaign(status: 'pending_review');

        $responses = [
            'table submit' => $this->actingAs($this->author)->post("/mesas/mis-mesas/{$table->slug}/enviar-revision?return_to=profile"),
            'table delete' => $this->actingAs($this->author)->delete("/mesas/mis-mesas/{$table->slug}?return_to=profile"),
        ];
        foreach ($responses as $action => $response) {
            $this->assertSame(302, $response->getStatusCode(), $action);
            $response->assertRedirect(route('profile.show'));
            $response->assertSessionHas('error', 'Esta mesa ya se ha enviado a revisión y ya no se puede modificar.');
        }

        $responses = [
            'campaign submit' => $this->actingAs($this->author)->post("/campanas/mis-campanas/{$campaign->id}/enviar-revision?return_to=profile"),
            'campaign delete' => $this->actingAs($this->author)->delete("/campanas/mis-campanas/{$campaign->id}?return_to=profile"),
        ];
        foreach ($responses as $action => $response) {
            $this->assertSame(302, $response->getStatusCode(), $action);
            $response->assertRedirect(route('profile.show'));
            $response->assertSessionHas('error', 'Esta campaña ya se ha enviado a revisión y ya no se puede modificar.');
        }

        $this->assertNotNull(GameTableModel::query()->find($table->id));
        $this->assertNotNull(CampaignModel::query()->find($campaign->id));
    }

    public function test_the_locked_author_without_a_return_target_still_goes_to_my_tables(): void
    {
        $table = $this->table(status: 'pending_review');

        $this->actingAs($this->author)
            ->delete("/mesas/mis-mesas/{$table->slug}?return_to=elsewhere")
            ->assertRedirect(route('gametables.my-tables'))
            ->assertSessionHas('error', 'Esta mesa ya se ha enviado a revisión y ya no se puede modificar.');
    }

    public function test_a_refused_publication_from_the_profile_returns_to_the_profile_with_the_reason(): void
    {
        $table = $this->table(status: 'draft');
        $exception = SessionBelowRosterException::forNewSession(5, 6);
        $this->mock(FrontendCreationServiceInterface::class)
            ->shouldReceive('submitForReview')
            ->andThrow($exception);

        $this->actingAs($this->author)
            ->from(route('gametables.my-tables'))
            ->post("/mesas/mis-mesas/{$table->slug}/enviar-revision?return_to=profile")
            ->assertRedirect(route('profile.show'))
            ->assertSessionHas('error', $exception->getMessage());
    }

    public function test_a_refused_publication_without_a_return_target_still_goes_back(): void
    {
        $table = $this->table(status: 'draft');
        $exception = SessionBelowRosterException::forNewSession(5, 6);
        $this->mock(FrontendCreationServiceInterface::class)
            ->shouldReceive('submitForReview')
            ->andThrow($exception);

        $this->actingAs($this->author)
            ->from(route('gametables.my-tables'))
            ->post("/mesas/mis-mesas/{$table->slug}/enviar-revision")
            ->assertRedirect(route('gametables.my-tables'))
            ->assertSessionHas('error', $exception->getMessage());
    }

    private function table(?string $status, string $slug = 'mesa-del-autor'): GameTableModel
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
            'status' => 'draft',
            'min_players' => 1,
            'max_players' => 5,
            'language' => 'es',
            'registration_type' => 'everyone',
            'auto_confirm' => true,
            'is_published' => false,
            'frontend_creation_status' => $status,
        ]);
    }

    private function campaign(?string $status, string $slug = 'campana-del-autor'): CampaignModel
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
            'is_published' => false,
            'frontend_creation_status' => $status,
        ]);
    }
}
