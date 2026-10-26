<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Feature\Moderation;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Livewire\Livewire;
use Modules\GameTables\Application\Services\FrontendCreationServiceInterface;
use Modules\GameTables\Domain\Exceptions\NotEditableException;
use Modules\GameTables\Filament\Resources\CampaignResource;
use Modules\GameTables\Filament\Resources\CampaignResource\Pages\EditCampaign;
use Modules\GameTables\Filament\Resources\CampaignResource\Pages\ListCampaigns;
use Modules\GameTables\Filament\Resources\GameTableResource;
use Modules\GameTables\Filament\Resources\GameTableResource\Pages\EditGameTable;
use Modules\GameTables\Filament\Resources\GameTableResource\Pages\ListGameTables;
use Modules\GameTables\Filament\Support\PublicationLock;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\CampaignModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameSystemModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameTableModel;
use Tests\Support\Modules\InteractsWithModulePanel;
use Tests\Support\Modules\ModuleTestCase;

/**
 * One review workflow for tables and campaigns created from the web: the author prepares a draft
 * and sends it to review; an admin approves it (approved and published) or rejects it with a
 * reason (back to a draft that shows the reason until the author sends it again). The panel never
 * publishes a web-created item the author has not sent; items created in the panel publish freely.
 */
final class ReviewWorkflowTest extends ModuleTestCase
{
    use InteractsWithModulePanel;

    protected ?string $moduleName = 'game-tables';

    protected bool $autoEnableModule = true;

    private UserModel $author;

    private GameSystemModel $gameSystem;

    protected function setUp(): void
    {
        parent::setUp();

        app('router')->getRoutes()->refreshNameLookups();
        Notification::fake();
        config()->set('modules.settings.game-tables', [
            'frontend_creation' => [
                'enabled' => true,
                'access_level' => 'registered',
                'allowed_content' => 'both',
                'publication' => ['mode' => 'approval'],
            ],
        ]);

        $this->registerOnAdminPanel([CampaignResource::class, GameTableResource::class]);
        $this->author = UserModel::factory()->create();
        $this->gameSystem = GameSystemModel::create([
            'id' => (string) Str::uuid(),
            'name' => 'Sistema de prueba',
            'slug' => 'sistema-de-prueba',
            'is_active' => true,
        ]);
    }

    public function test_a_rejected_table_goes_back_to_draft_with_the_reason_until_the_author_sends_it_again(): void
    {
        $this->actingAsPanelAdmin();
        $table = $this->table('pending_review');

        Livewire::test(ListGameTables::class)
            ->callTableAction('reject', $table, ['reason' => 'Falta la sinopsis'])
            ->assertHasNoTableActionErrors();

        $table->refresh();
        $this->assertSame('draft', $table->frontend_creation_status?->value);
        $this->assertSame('Falta la sinopsis', $table->moderation_notes);
        $this->assertFalse($table->is_published);

        $this->actingAs($this->author)
            ->get(route('gametables.my-tables'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('GameTables/MyTables', false)
                ->where('drafts.0.rejection_reason', 'Falta la sinopsis')
                ->where('drafts.0.can_edit', true));

        $this->actingAs($this->author)
            ->put("/mesas/mis-mesas/{$table->slug}", $this->tableData('Mesa corregida'))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('gametables.my-tables'));
        $this->assertSame('Mesa corregida', $table->refresh()->title);

        $this->actingAs($this->author)
            ->post("/mesas/mis-mesas/{$table->slug}/enviar-revision")
            ->assertRedirect(route('gametables.my-tables'));

        $table->refresh();
        $this->assertSame('pending_review', $table->frontend_creation_status?->value);
        $this->assertNull($table->moderation_notes);

        $this->actingAs($this->author)
            ->get(route('gametables.my-tables'))
            ->assertInertia(fn (Assert $page) => $page->where('drafts.0.rejection_reason', null));
    }

    public function test_a_rejected_campaign_goes_back_to_draft_with_the_reason_until_the_author_sends_it_again(): void
    {
        $this->actingAsPanelAdmin();
        $campaign = $this->campaign('pending_review');

        Livewire::test(ListCampaigns::class)
            ->callTableAction('reject', $campaign, ['reason' => 'Falta la descripción'])
            ->assertHasNoTableActionErrors();

        $campaign->refresh();
        $this->assertSame('draft', $campaign->frontend_creation_status?->value);
        $this->assertSame('Falta la descripción', $campaign->moderation_notes);
        $this->assertFalse($campaign->is_published);

        $this->actingAs($this->author)
            ->get(route('campaigns.my-campaigns'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Campaigns/MyCampaigns', false)
                ->where('campaigns.0.rejection_reason', 'Falta la descripción')
                ->where('campaigns.0.can_edit', true));

        $this->actingAs($this->author)
            ->post(route('campaigns.submit-review', $campaign->id))
            ->assertRedirect();

        $campaign->refresh();
        $this->assertSame('pending_review', $campaign->frontend_creation_status?->value);
        $this->assertNull($campaign->moderation_notes);

        $this->actingAs($this->author)
            ->get(route('campaigns.my-campaigns'))
            ->assertInertia(fn (Assert $page) => $page->where('campaigns.0.rejection_reason', null));
    }

    // Rows rejected before 2.10.0 keep working until the deploy converts them to drafts
    public function test_a_legacy_rejected_item_still_shows_its_reason_and_can_be_sent_again(): void
    {
        $table = $this->table('rejected', notes: 'Fecha incorrecta');
        $this->campaign('rejected', notes: 'Sin sistema');

        $this->actingAs($this->author)
            ->get(route('gametables.my-tables'))
            ->assertInertia(fn (Assert $page) => $page->where('drafts.0.rejection_reason', 'Fecha incorrecta'));
        $this->actingAs($this->author)
            ->get(route('campaigns.my-campaigns'))
            ->assertInertia(fn (Assert $page) => $page->where('campaigns.0.rejection_reason', 'Sin sistema'));

        $this->actingAs($this->author)->post("/mesas/mis-mesas/{$table->slug}/enviar-revision")->assertRedirect();
        $this->assertSame('pending_review', $table->refresh()->frontend_creation_status?->value);
    }

    // Without review ('auto' mode) a table sent by its author is approved and published, as a campaign is
    public function test_a_table_sent_without_review_is_approved_and_published(): void
    {
        config()->set('modules.settings.game-tables.frontend_creation.publication.mode', 'auto');
        $table = $this->table('draft', notes: 'Falta la sinopsis');

        $this->actingAs($this->author)->post("/mesas/mis-mesas/{$table->slug}/enviar-revision")->assertRedirect();

        $table->refresh();
        $this->assertSame('approved', $table->frontend_creation_status?->value);
        $this->assertTrue($table->is_published);
        $this->assertNull($table->moderation_notes);
    }

    public function test_approving_a_table_in_review_approves_and_publishes_it(): void
    {
        $this->actingAsPanelAdmin();
        $table = $this->table('pending_review');

        Livewire::test(EditGameTable::class, ['record' => $table->getRouteKey()])
            ->callAction('approveModeration')
            ->assertFormSet(['is_published' => true]);

        $table->refresh();
        $this->assertSame('approved', $table->frontend_creation_status?->value);
        $this->assertTrue($table->is_published);
    }

    public function test_only_items_in_review_can_be_approved_or_rejected(): void
    {
        $service = app(FrontendCreationServiceInterface::class);
        $draft = $this->table('draft');

        try {
            $service->approveFrontendCreation($draft->id);
            $this->fail('A draft the author has not sent was approved');
        } catch (NotEditableException) {
        }

        try {
            $service->rejectFrontendCreation($draft->id, 'Motivo');
            $this->fail('A draft the author has not sent was rejected');
        } catch (NotEditableException) {
        }

        $draft->refresh();
        $this->assertSame('draft', $draft->frontend_creation_status?->value);
        $this->assertFalse($draft->is_published);
        $this->assertNull($draft->moderation_notes);
    }

    public function test_bulk_approval_and_rejection_only_touch_tables_in_review(): void
    {
        $this->actingAsPanelAdmin();
        $draft = $this->table('draft', slug: 'mesa-en-borrador');
        $pending = $this->table('pending_review', slug: 'mesa-en-revision');
        $approved = $this->table('approved', published: true, slug: 'mesa-aprobada');

        Livewire::test(ListGameTables::class)
            ->callTableBulkAction('approve', [$draft, $pending, $approved])
            ->assertNotified('1 mesa(s) aprobada(s)');

        $this->assertSame('draft', $draft->refresh()->frontend_creation_status?->value);
        $this->assertFalse($draft->is_published);
        $this->assertSame('approved', $pending->refresh()->frontend_creation_status?->value);
        $this->assertTrue($pending->is_published);

        $otherPending = $this->table('pending_review', slug: 'otra-mesa-en-revision');

        Livewire::test(ListGameTables::class)
            ->callTableBulkAction('reject', [$draft, $approved, $otherPending], ['reason' => 'Revisa el aforo'])
            ->assertNotified('1 mesa(s) rechazada(s)');

        $this->assertNull($draft->refresh()->moderation_notes);
        $this->assertSame('approved', $approved->refresh()->frontend_creation_status?->value);
        $this->assertTrue($approved->is_published);
        $otherPending->refresh();
        $this->assertSame('draft', $otherPending->frontend_creation_status?->value);
        $this->assertSame('Revisa el aforo', $otherPending->moderation_notes);
    }

    public function test_the_panel_cannot_publish_a_web_table_the_author_has_not_sent(): void
    {
        $this->actingAsPanelAdmin();
        $table = $this->table('draft');

        Livewire::test(EditGameTable::class, ['record' => $table->getRouteKey()])
            ->assertFormFieldDisabled('is_published')
            ->assertSee('El autor aún no la ha enviado a revisión.')
            ->assertActionHidden('publish')
            ->set('data.status', 'scheduled')
            ->set('data.is_published', true)
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertFalse($table->refresh()->is_published);
        $this->assertSame('draft', $table->frontend_creation_status?->value);
    }

    public function test_the_panel_publishes_a_web_table_in_review_only_by_approving_it(): void
    {
        $this->actingAsPanelAdmin();
        $table = $this->table('pending_review');

        Livewire::test(EditGameTable::class, ['record' => $table->getRouteKey()])
            ->assertFormFieldDisabled('is_published')
            ->assertSee('Está pendiente de revisión: apruébala o recházala con «Aprobar» o «Rechazar».')
            ->assertActionHidden('publish')
            ->set('data.status', 'scheduled')
            ->set('data.is_published', true)
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertFalse($table->refresh()->is_published);
        $this->assertSame('pending_review', $table->frontend_creation_status?->value);
    }

    public function test_a_table_created_in_the_panel_is_published_with_the_toggle(): void
    {
        $this->actingAsPanelAdmin();
        $table = $this->table(null);

        Livewire::test(EditGameTable::class, ['record' => $table->getRouteKey()])
            ->assertFormFieldEnabled('is_published')
            ->assertDontSee('El autor aún no la ha enviado a revisión.')
            ->assertActionVisible('publish')
            ->set('data.is_published', true)
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertTrue($table->refresh()->is_published);
    }

    public function test_an_approved_table_can_be_unpublished_and_republished(): void
    {
        $this->actingAsPanelAdmin();
        $table = $this->table('approved', published: true);

        Livewire::test(EditGameTable::class, ['record' => $table->getRouteKey()])
            ->assertFormFieldEnabled('is_published')
            ->set('data.is_published', false)
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertFalse($table->refresh()->is_published);

        Livewire::test(EditGameTable::class, ['record' => $table->getRouteKey()])
            ->assertFormFieldEnabled('is_published')
            ->assertActionVisible('publish')
            ->set('data.is_published', true)
            ->call('save')
            ->assertHasNoFormErrors();

        $table->refresh();
        $this->assertTrue($table->is_published);
        $this->assertSame('approved', $table->frontend_creation_status?->value);
    }

    public function test_the_panel_cannot_publish_a_web_campaign_the_author_has_not_sent(): void
    {
        $this->actingAsPanelAdmin();
        $draft = $this->campaign('draft');

        Livewire::test(EditCampaign::class, ['record' => $draft->getRouteKey()])
            ->assertFormFieldDisabled('is_published')
            ->assertSee('El autor aún no la ha enviado a revisión.')
            ->set('data.is_published', true)
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertFalse($draft->refresh()->is_published);

        $pending = $this->campaign('pending_review', slug: 'campana-en-revision');

        Livewire::test(EditCampaign::class, ['record' => $pending->getRouteKey()])
            ->assertFormFieldDisabled('is_published')
            ->assertSee('Está pendiente de revisión: apruébala o recházala desde la lista de campañas.')
            ->set('data.is_published', true)
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertFalse($pending->refresh()->is_published);
        $this->assertSame('pending_review', $pending->frontend_creation_status?->value);
    }

    public function test_panel_campaigns_and_approved_campaigns_publish_with_the_toggle(): void
    {
        $this->actingAsPanelAdmin();
        $panelCampaign = $this->campaign(null);

        Livewire::test(EditCampaign::class, ['record' => $panelCampaign->getRouteKey()])
            ->assertFormFieldEnabled('is_published')
            ->set('data.is_published', true)
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertTrue($panelCampaign->refresh()->is_published);

        $approved = $this->campaign('approved', published: true, slug: 'campana-aprobada');

        Livewire::test(EditCampaign::class, ['record' => $approved->getRouteKey()])
            ->set('data.is_published', false)
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertFalse($approved->refresh()->is_published);

        Livewire::test(EditCampaign::class, ['record' => $approved->getRouteKey()])
            ->assertFormFieldEnabled('is_published')
            ->set('data.is_published', true)
            ->call('save')
            ->assertHasNoFormErrors();
        $approved->refresh();
        $this->assertTrue($approved->is_published);
        $this->assertSame('approved', $approved->frontend_creation_status?->value);
    }

    // The save guard on its own, for a payload that reaches the save with the toggle on
    public function test_the_save_guard_keeps_a_locked_item_unpublished_and_leaves_the_rest_alone(): void
    {
        $data = ['title' => 'Mesa del autor', 'is_published' => true];

        $this->assertFalse(PublicationLock::guardFormData($this->table('draft'), $data)['is_published']);
        $this->assertFalse(PublicationLock::guardFormData($this->table('pending_review', slug: 'en-revision'), $data)['is_published']);
        $this->assertFalse(PublicationLock::guardFormData($this->campaign('rejected'), $data)['is_published']);
        $this->assertTrue(PublicationLock::guardFormData($this->table(null, slug: 'del-panel'), $data)['is_published']);
        $this->assertTrue(PublicationLock::guardFormData($this->campaign('approved', slug: 'aprobada'), $data)['is_published']);
        // Already published (e.g. before 2.10.0): it may stay published or be unpublished
        $this->assertTrue(PublicationLock::guardFormData($this->table('draft', published: true, slug: 'publicada'), $data)['is_published']);
    }

    public function test_approving_or_rejecting_a_table_that_is_no_longer_in_review_warns_instead_of_failing(): void
    {
        $this->actingAsPanelAdmin();
        $table = $this->table('pending_review');
        $this->serviceThatRefuses();

        Livewire::test(EditGameTable::class, ['record' => $table->getRouteKey()])
            ->callAction('approveModeration')
            ->assertNotified('La mesa ya no está en un estado que permita este cambio')
            ->callAction('rejectModeration', ['reason' => 'Motivo'])
            ->assertNotified('La mesa ya no está en un estado que permita este cambio');

        Livewire::test(ListGameTables::class)
            ->callTableAction('approve', $table)
            ->assertNotified('La mesa ya no está en un estado que permita este cambio')
            ->callTableAction('reject', $table, ['reason' => 'Motivo'])
            ->assertNotified('La mesa ya no está en un estado que permita este cambio');

        Livewire::test(ListGameTables::class)
            ->callTableBulkAction('approve', [$table])
            ->assertNotified('La mesa ya no está en un estado que permita este cambio')
            ->callTableBulkAction('reject', [$table], ['reason' => 'Motivo'])
            ->assertNotified('La mesa ya no está en un estado que permita este cambio');

        // Every selected table skipped: the warning alone, never a "0 approved" success. Each
        // assertion pulls the notifications from the session, hence one action per assertion
        Livewire::test(ListGameTables::class)
            ->callTableBulkAction('approve', [$table])
            ->assertNotNotified('0 mesa(s) aprobada(s)')
            ->callTableBulkAction('reject', [$table], ['reason' => 'Motivo'])
            ->assertNotNotified('0 mesa(s) rechazada(s)');
    }

    public function test_approving_or_rejecting_a_campaign_that_is_no_longer_in_review_warns_instead_of_failing(): void
    {
        $this->actingAsPanelAdmin();
        $campaign = $this->campaign('pending_review');
        $this->serviceThatRefuses();

        Livewire::test(ListCampaigns::class)
            ->callTableAction('approve', $campaign)
            ->assertNotified('La campaña ya no está en un estado que permita este cambio')
            ->callTableAction('reject', $campaign, ['reason' => 'Motivo'])
            ->assertNotified('La campaña ya no está en un estado que permita este cambio');
    }

    public function test_an_admin_saves_the_content_of_a_web_draft_table_without_changing_its_status(): void
    {
        $this->actingAsPanelAdmin();
        $table = $this->table('draft');

        Livewire::test(EditGameTable::class, ['record' => $table->getRouteKey()])
            ->assertFormSet(['status' => 'draft'])
            ->set('data.title', 'Mesa retocada en el panel')
            ->call('save')
            ->assertHasNoFormErrors();

        $table->refresh();
        $this->assertSame('Mesa retocada en el panel', $table->title);
        $this->assertSame('draft', $table->status->value);
        $this->assertFalse($table->is_published);
        $this->assertSame('draft', $table->frontend_creation_status?->value);
    }

    public function test_the_draft_table_status_is_only_offered_to_a_table_that_is_in_draft(): void
    {
        $this->actingAsPanelAdmin();
        $scheduled = $this->table(null);

        Livewire::test(EditGameTable::class, ['record' => $scheduled->getRouteKey()])
            ->set('data.status', 'draft')
            ->call('save')
            ->assertHasFormErrors(['status']);
    }

    private function serviceThatRefuses(): void
    {
        $service = \Mockery::mock(FrontendCreationServiceInterface::class);
        $service->shouldReceive('approveFrontendCreation', 'rejectFrontendCreation', 'approveCampaign', 'rejectCampaign')
            ->andThrow(NotEditableException::forStatus('approved'));
        $this->app->instance(FrontendCreationServiceInterface::class, $service);
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

    private function table(?string $creationStatus, bool $published = false, string $slug = 'mesa-del-autor', ?string $notes = null): GameTableModel
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
            // The panel offers no "draft" table status: what it creates is scheduled until published
            'status' => $published || $creationStatus === null ? 'scheduled' : 'draft',
            'min_players' => 1,
            'max_players' => 5,
            'language' => 'es',
            'experience_level' => 'none',
            'character_creation' => 'pre_generated',
            'registration_type' => 'everyone',
            'auto_confirm' => true,
            'is_published' => $published,
            'published_at' => $published ? now() : null,
            'frontend_creation_status' => $creationStatus,
            'moderation_notes' => $notes,
        ]);
    }

    private function campaign(?string $creationStatus, bool $published = false, string $slug = 'campana-del-autor', ?string $notes = null): CampaignModel
    {
        return CampaignModel::create([
            'id' => (string) Str::uuid(),
            'game_system_id' => $this->gameSystem->id,
            'created_by' => $this->author->id,
            'title' => 'Campaña del autor',
            'slug' => $slug,
            'status' => 'recruiting',
            'is_published' => $published,
            'frontend_creation_status' => $creationStatus,
            'moderation_notes' => $notes,
        ]);
    }
}
