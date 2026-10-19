<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Feature\Moderation;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Modules\GameTables\Filament\Resources\CampaignResource;
use Modules\GameTables\Filament\Resources\CampaignResource\Pages\ListCampaigns;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\CampaignModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameSystemModel;
use Modules\GameTables\Notifications\CampaignModerationApprovedNotification;
use Modules\GameTables\Notifications\CampaignModerationRejectedNotification;
use Tests\Support\Modules\InteractsWithModulePanel;
use Tests\Support\Modules\ModuleTestCase;

/**
 * Approving or rejecting a campaign sent for review tells its author, as tables do.
 */
final class CampaignModerationEmailsTest extends ModuleTestCase
{
    use InteractsWithModulePanel;

    protected ?string $moduleName = 'game-tables';

    protected bool $autoEnableModule = true;

    private UserModel $author;

    private CampaignModel $campaign;

    protected function setUp(): void
    {
        parent::setUp();

        app()->setLocale('es');
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

        $this->registerOnAdminPanel([CampaignResource::class]);
        $this->author = UserModel::factory()->create(['name' => 'Ana', 'email' => 'autora@example.test']);
        $gameSystem = GameSystemModel::create([
            'id' => (string) Str::uuid(),
            'name' => 'Sistema de prueba',
            'slug' => 'sistema-de-prueba',
            'is_active' => true,
        ]);
        $this->campaign = CampaignModel::create([
            'id' => (string) Str::uuid(),
            'game_system_id' => $gameSystem->id,
            'created_by' => $this->author->id,
            'title' => 'La maldición de Strahd',
            'slug' => 'la-maldicion-de-strahd',
            'status' => 'recruiting',
            'is_published' => false,
            'frontend_creation_status' => 'pending_review',
        ]);
        $this->actingAsPanelAdmin();
    }

    public function test_approving_a_campaign_emails_its_author_with_a_link_to_it(): void
    {
        Livewire::test(ListCampaigns::class)
            ->callTableAction('approve', $this->campaign)
            ->assertHasNoTableActionErrors();

        Notification::assertSentOnDemand(
            CampaignModerationApprovedNotification::class,
            function (CampaignModerationApprovedNotification $n, array $channels, AnonymousNotifiable $notifiable): bool {
                $message = $n->toMail($notifiable);

                return $notifiable->routes['mail'] === 'autora@example.test'
                    && $message->actionUrl === route('campaigns.show', 'la-maldicion-de-strahd')
                    && str_contains((string) $message->subject, 'La maldición de Strahd');
            },
        );
    }

    public function test_rejecting_a_campaign_emails_its_author_with_the_reason_and_the_edit_link(): void
    {
        Livewire::test(ListCampaigns::class)
            ->callTableAction('reject', $this->campaign, ['reason' => 'Falta la descripción'])
            ->assertHasNoTableActionErrors();

        Notification::assertSentOnDemand(
            CampaignModerationRejectedNotification::class,
            function (CampaignModerationRejectedNotification $n, array $channels, AnonymousNotifiable $notifiable): bool {
                $message = $n->toMail($notifiable);

                return $notifiable->routes['mail'] === 'autora@example.test'
                    && $message->actionUrl === route('campaigns.frontend-edit', $this->campaign->id)
                    && str_contains(implode(' ', $message->introLines), 'Falta la descripción');
            },
        );
    }
}
