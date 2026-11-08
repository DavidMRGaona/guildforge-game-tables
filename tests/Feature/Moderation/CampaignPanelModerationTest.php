<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Feature\Moderation;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Modules\GameTables\Filament\Resources\CampaignResource;
use Modules\GameTables\Filament\Resources\CampaignResource\Pages\EditCampaign;
use Modules\GameTables\Filament\Resources\CampaignResource\Pages\ListCampaigns;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\CampaignModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameSystemModel;
use Tests\Support\Modules\InteractsWithModulePanel;
use Tests\Support\Modules\ModuleTestCase;

/**
 * Campaigns are moderated in the panel as tables are: a «Pendientes de moderación» tab, approving
 * and rejecting from the edit page and several at once from the list.
 */
final class CampaignPanelModerationTest extends ModuleTestCase
{
    use InteractsWithModulePanel;

    protected ?string $moduleName = 'game-tables';

    protected bool $autoEnableModule = true;

    private UserModel $author;

    private GameSystemModel $gameSystem;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        $this->registerOnAdminPanel([CampaignResource::class]);
        $this->actingAsPanelAdmin();
        $this->author = UserModel::factory()->create();
        $this->gameSystem = GameSystemModel::create([
            'id' => (string) Str::uuid(),
            'name' => 'Sistema de prueba',
            'slug' => 'sistema-de-prueba',
            'is_active' => true,
        ]);
    }

    public function test_the_pending_tab_lists_only_campaigns_awaiting_review_and_counts_them(): void
    {
        $pending = $this->campaign('pending_review', 'En revisión');
        $otherPending = $this->campaign('pending_review', 'Otra en revisión');
        $draft = $this->campaign('draft', 'Borrador del autor');
        $approved = $this->campaign('approved', 'Ya aprobada');
        $panel = $this->campaign(null, 'Creada en el panel');

        $list = Livewire::test(ListCampaigns::class);
        $page = $list->instance();
        $this->assertInstanceOf(ListCampaigns::class, $page);

        $this->assertSame('2', (string) $page->getTabs()['pending_moderation']->getBadge());

        $list->assertCanSeeTableRecords([$pending, $otherPending, $draft, $approved, $panel])
            ->set('activeTab', 'pending_moderation')
            ->assertCanSeeTableRecords([$pending, $otherPending])
            ->assertCanNotSeeTableRecords([$draft, $approved, $panel]);
    }

    public function test_the_moderation_filter_separates_drafts_reviews_and_approved_campaigns(): void
    {
        $pending = $this->campaign('pending_review', 'En revisión');
        $draft = $this->campaign('draft', 'Borrador del autor');
        $approved = $this->campaign('approved', 'Ya aprobada');

        Livewire::test(ListCampaigns::class)
            ->filterTable('frontend_creation_status', 'draft')
            ->assertCanSeeTableRecords([$draft])
            ->assertCanNotSeeTableRecords([$pending, $approved]);
    }

    public function test_bulk_approval_only_publishes_campaigns_in_review(): void
    {
        $pending = $this->campaign('pending_review', 'En revisión');
        $draft = $this->campaign('draft', 'Borrador del autor');
        $panel = $this->campaign(null, 'Creada en el panel');

        Livewire::test(ListCampaigns::class)
            ->callTableBulkAction('approve', [$pending, $draft, $panel])
            ->assertNotified('1 campaña(s) aprobada(s)');

        $this->assertSame('approved', $pending->refresh()->frontend_creation_status?->value);
        $this->assertTrue($pending->is_published);
        $this->assertSame('draft', $draft->refresh()->frontend_creation_status?->value);
        $this->assertFalse($draft->is_published);
        $this->assertNull($panel->refresh()->frontend_creation_status);
        $this->assertFalse($panel->is_published);
    }

    public function test_bulk_rejection_returns_the_campaigns_in_review_to_drafts_with_the_same_reason(): void
    {
        $pending = $this->campaign('pending_review', 'En revisión');
        $otherPending = $this->campaign('pending_review', 'Otra en revisión');
        $approved = $this->campaign('approved', 'Ya aprobada', published: true);

        Livewire::test(ListCampaigns::class)
            ->callTableBulkAction('reject', [$pending, $otherPending, $approved], ['reason' => 'Falta la sinopsis'])
            ->assertNotified('2 campaña(s) rechazada(s)');

        foreach ([$pending, $otherPending] as $campaign) {
            $campaign->refresh();
            $this->assertSame('draft', $campaign->frontend_creation_status?->value);
            $this->assertSame('Falta la sinopsis', $campaign->moderation_notes);
            $this->assertFalse($campaign->is_published);
        }

        $this->assertSame('approved', $approved->refresh()->frontend_creation_status?->value);
        $this->assertTrue($approved->is_published);
    }

    public function test_bulk_rejection_needs_a_reason(): void
    {
        $pending = $this->campaign('pending_review', 'En revisión');

        Livewire::test(ListCampaigns::class)
            ->callTableBulkAction('reject', [$pending], ['reason' => ''])
            ->assertHasTableBulkActionErrors(['reason' => 'required']);

        $this->assertSame('pending_review', $pending->refresh()->frontend_creation_status?->value);
    }

    public function test_approving_from_the_edit_page_publishes_the_campaign_and_shows_it_on_the_form(): void
    {
        $campaign = $this->campaign('pending_review', 'En revisión');

        Livewire::test(EditCampaign::class, ['record' => $campaign->getRouteKey()])
            ->assertActionVisible('approveModeration')
            ->assertActionVisible('rejectModeration')
            ->callAction('approveModeration')
            ->assertNotified(__('game-tables::messages.notifications.campaign_approved'))
            ->assertFormSet(['is_published' => true])
            ->assertActionHidden('approveModeration')
            ->assertActionHidden('rejectModeration');

        $campaign->refresh();
        $this->assertSame('approved', $campaign->frontend_creation_status?->value);
        $this->assertTrue($campaign->is_published);
    }

    public function test_rejecting_from_the_edit_page_returns_the_campaign_to_a_draft_with_the_reason(): void
    {
        $campaign = $this->campaign('pending_review', 'En revisión');

        Livewire::test(EditCampaign::class, ['record' => $campaign->getRouteKey()])
            ->callAction('rejectModeration', ['reason' => 'Falta la sinopsis'])
            ->assertNotified(__('game-tables::messages.notifications.campaign_rejected'))
            ->assertActionHidden('approveModeration');

        $campaign->refresh();
        $this->assertSame('draft', $campaign->frontend_creation_status?->value);
        $this->assertSame('Falta la sinopsis', $campaign->moderation_notes);
        $this->assertFalse($campaign->is_published);
    }

    public function test_the_edit_page_offers_no_moderation_for_campaigns_not_in_review(): void
    {
        foreach ([null, 'draft', 'approved'] as $index => $status) {
            $campaign = $this->campaign($status, 'Campaña '.$index);

            Livewire::test(EditCampaign::class, ['record' => $campaign->getRouteKey()])
                ->assertActionHidden('approveModeration')
                ->assertActionHidden('rejectModeration');
        }
    }

    public function test_the_locked_toggle_points_to_the_approve_and_reject_actions(): void
    {
        $campaign = $this->campaign('pending_review', 'En revisión');

        Livewire::test(EditCampaign::class, ['record' => $campaign->getRouteKey()])
            ->assertSee('apruébala o recházala con «Aprobar» o «Rechazar»', escape: false);
    }

    private function campaign(?string $creationStatus, string $title, bool $published = false): CampaignModel
    {
        return CampaignModel::create([
            'id' => (string) Str::uuid(),
            'game_system_id' => $this->gameSystem->id,
            'created_by' => $this->author->id,
            'title' => $title,
            'slug' => Str::slug($title),
            'status' => 'recruiting',
            'is_published' => $published,
            'frontend_creation_status' => $creationStatus,
        ]);
    }
}
