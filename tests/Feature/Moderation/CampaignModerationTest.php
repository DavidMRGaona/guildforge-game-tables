<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Feature\Moderation;

use App\Infrastructure\Persistence\Eloquent\Models\PermissionModel;
use App\Infrastructure\Persistence\Eloquent\Models\RoleModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Modules\GameTables\Application\Services\FrontendCreationServiceInterface;
use Modules\GameTables\Filament\Resources\CampaignResource;
use Modules\GameTables\Filament\Resources\CampaignResource\Pages\ListCampaigns;
use Modules\GameTables\Filament\Resources\GameTableResource;
use Modules\GameTables\Filament\Widgets\PendingModerationWidget;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\CampaignModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameSystemModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameTableModel;
use Modules\GameTables\Notifications\CampaignModerationSubmittedNotification;
use Tests\Support\Modules\InteractsWithModulePanel;
use Tests\Support\Modules\ModuleTestCase;

/**
 * Campaigns sent for review reach the moderators as tables do: email and moderation widget.
 */
final class CampaignModerationTest extends ModuleTestCase
{
    use InteractsWithModulePanel;

    protected ?string $moduleName = 'game-tables';

    protected bool $autoEnableModule = true;

    private UserModel $admin;

    private UserModel $creator;

    private GameSystemModel $gameSystem;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registerOnAdminPanel([CampaignResource::class, GameTableResource::class]);
        $this->admin = $this->actingAsPanelAdmin();
        $this->creator = UserModel::factory()->create(['name' => 'John Doe']);
        $this->gameSystem = GameSystemModel::create([
            'id' => (string) Str::uuid(),
            'name' => 'Sistema de prueba',
            'slug' => 'sistema-de-prueba',
            'is_active' => true,
        ]);
        config()->set('modules.settings.game-tables.frontend_creation.publication.mode', 'approval');
    }

    public function test_a_campaign_sent_for_review_emails_whoever_can_moderate_campaigns(): void
    {
        Notification::fake();
        $editor = UserModel::factory()->editor()->create();
        RoleModel::query()->where('name', 'editor')->firstOrFail()->permissions()->attach($this->permission('gametables:campaigns.update')->id);
        $tablesOnly = RoleModel::create(['name' => 'table_moderator', 'display_name' => 'Table moderator']);
        $tablesOnly->permissions()->attach([$this->permission('admin.access')->id, $this->permission('gametables:gametables.update')->id]);
        UserModel::factory()->create()->roles()->attach($tablesOnly->id);
        $campaign = $this->campaign('draft');

        app(FrontendCreationServiceInterface::class)->submitCampaignForReview($campaign->id, $this->creator->id);

        $this->assertSame('pending_review', $campaign->refresh()->frontend_creation_status?->value);
        $recipients = [];
        Notification::assertSentOnDemand(
            CampaignModerationSubmittedNotification::class,
            function (CampaignModerationSubmittedNotification $notification, array $channels, AnonymousNotifiable $notifiable) use (&$recipients): bool {
                $recipients[] = $notifiable->routes['mail'];

                return true;
            },
        );
        $this->assertEqualsCanonicalizing([$this->admin->email, $editor->email], $recipients);
    }

    public function test_an_auto_published_campaign_sends_no_moderation_email(): void
    {
        Notification::fake();
        config()->set('modules.settings.game-tables.frontend_creation.publication.mode', 'auto');
        $campaign = $this->campaign('draft');

        app(FrontendCreationServiceInterface::class)->submitCampaignForReview($campaign->id, $this->creator->id);

        $this->assertTrue($campaign->refresh()->is_published);
        Notification::assertNothingSent();
    }

    public function test_the_moderation_email_names_the_campaign_and_links_to_it_in_the_panel(): void
    {
        $campaign = $this->campaign('pending_review');

        $mail = (new CampaignModerationSubmittedNotification($campaign->id, $campaign->title, 'John Doe'))
            ->toMail(new AnonymousNotifiable);

        $this->assertSame(CampaignResource::getUrl('edit', ['record' => $campaign]), $mail->actionUrl);
        $this->assertStringContainsString('La llamada', (string) $mail->subject);
        $this->assertContains('**'.__('game-tables::emails.campaign_moderation_submitted.created_by').':** John Doe', $mail->introLines);
    }

    public function test_the_moderation_widget_lists_campaigns_and_tables_awaiting_review(): void
    {
        $this->campaign('pending_review');
        $this->campaign('draft', 'Campaña en borrador');
        $this->table('pending_review');

        $this->assertTrue(PendingModerationWidget::canView());
        Livewire::test(PendingModerationWidget::class)
            ->assertOk()
            ->assertSee('La llamada')
            ->assertSee('Mesa pendiente')
            ->assertDontSee('Campaña en borrador')
            ->assertSee(CampaignResource::getUrl('edit', ['record' => CampaignModel::query()->where('title', 'La llamada')->firstOrFail()]))
            ->assertSee(GameTableResource::getUrl('edit', ['record' => GameTableModel::query()->firstOrFail()]));
    }

    public function test_the_moderation_widget_shows_while_only_a_campaign_awaits_review(): void
    {
        $this->assertFalse(PendingModerationWidget::canView());

        $this->campaign('pending_review');

        $this->assertTrue(PendingModerationWidget::canView());
    }

    public function test_approving_a_campaign_publishes_it_and_takes_it_out_of_the_queue(): void
    {
        $campaign = $this->campaign('pending_review');

        Livewire::test(ListCampaigns::class)
            ->assertTableActionVisible('approve', $campaign)
            ->callTableAction('approve', $campaign)
            ->assertHasNoTableActionErrors();

        $campaign->refresh();
        $this->assertSame('approved', $campaign->frontend_creation_status?->value);
        $this->assertTrue($campaign->is_published);
        $this->assertFalse(PendingModerationWidget::canView());
    }

    public function test_rejecting_a_campaign_returns_it_to_an_unpublished_draft_with_the_reason(): void
    {
        $campaign = $this->campaign('pending_review');

        Livewire::test(ListCampaigns::class)
            ->callTableAction('reject', $campaign, ['reason' => 'Falta la sinopsis'])
            ->assertHasNoTableActionErrors();

        $campaign->refresh();
        $this->assertSame('draft', $campaign->frontend_creation_status?->value);
        $this->assertSame('Falta la sinopsis', $campaign->moderation_notes);
        $this->assertFalse($campaign->is_published);
    }

    public function test_campaigns_not_awaiting_review_have_no_moderation_actions(): void
    {
        $campaign = $this->campaign(null);

        Livewire::test(ListCampaigns::class)
            ->assertTableActionHidden('approve', $campaign)
            ->assertTableActionHidden('reject', $campaign);
    }

    private function permission(string $key): PermissionModel
    {
        return PermissionModel::firstOrCreate(
            ['key' => $key],
            ['label' => $key, 'resource' => Str::before(Str::afterLast($key, ':'), '.'), 'action' => Str::afterLast($key, '.')],
        );
    }

    private function campaign(?string $creationStatus, string $title = 'La llamada'): CampaignModel
    {
        return CampaignModel::create([
            'id' => (string) Str::uuid(),
            'game_system_id' => $this->gameSystem->id,
            'created_by' => $this->creator->id,
            'title' => $title,
            'slug' => Str::slug($title),
            'status' => 'recruiting',
            'is_published' => false,
            'frontend_creation_status' => $creationStatus,
        ]);
    }

    private function table(string $creationStatus): GameTableModel
    {
        return GameTableModel::create([
            'id' => (string) Str::uuid(),
            'game_system_id' => $this->gameSystem->id,
            'created_by' => $this->creator->id,
            'title' => 'Mesa pendiente',
            'slug' => 'mesa-pendiente',
            'starts_at' => now()->addWeek(),
            'duration_minutes' => 240,
            'table_type' => 'one_shot',
            'table_format' => 'in_person',
            'status' => 'draft',
            'min_players' => 2,
            'max_players' => 5,
            'language' => 'es',
            'frontend_creation_status' => $creationStatus,
        ]);
    }
}
