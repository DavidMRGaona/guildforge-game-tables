<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Feature\Filament;

use App\Filament\Pages\ModuleSettingsPage;
use App\Filament\Resources\EventResource\Pages\EditEvent;
use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Modules\GameTables\Filament\Pages\GameTablesSettings;
use Modules\GameTables\Filament\RelationManagers\EventGameTableConfigRelationManager;
use Modules\GameTables\Filament\Resources\CampaignResource;
use Modules\GameTables\Filament\Resources\CampaignResource\Pages\CreateCampaign;
use Modules\GameTables\Filament\Resources\CampaignResource\Pages\EditCampaign;
use Modules\GameTables\Filament\Resources\CampaignResource\Pages\ListCampaigns;
use Modules\GameTables\Filament\Resources\ContentWarningResource;
use Modules\GameTables\Filament\Resources\ContentWarningResource\Pages\CreateContentWarning;
use Modules\GameTables\Filament\Resources\ContentWarningResource\Pages\EditContentWarning;
use Modules\GameTables\Filament\Resources\ContentWarningResource\Pages\ListContentWarnings;
use Modules\GameTables\Filament\Resources\GameSystemResource;
use Modules\GameTables\Filament\Resources\GameSystemResource\Pages\CreateGameSystem;
use Modules\GameTables\Filament\Resources\GameSystemResource\Pages\EditGameSystem;
use Modules\GameTables\Filament\Resources\GameSystemResource\Pages\ListGameSystems;
use Modules\GameTables\Filament\Resources\GameTableResource;
use Modules\GameTables\Filament\Resources\GameTableResource\Pages\CreateGameTable;
use Modules\GameTables\Filament\Resources\GameTableResource\Pages\EditGameTable;
use Modules\GameTables\Filament\Resources\GameTableResource\Pages\ListGameTables;
use Modules\GameTables\Filament\Resources\GameTableResource\RelationManagers\ParticipantsRelationManager;
use Modules\GameTables\Filament\Resources\PublisherResource;
use Modules\GameTables\Filament\Resources\PublisherResource\Pages\CreatePublisher;
use Modules\GameTables\Filament\Resources\PublisherResource\Pages\EditPublisher;
use Modules\GameTables\Filament\Resources\PublisherResource\Pages\ListPublishers;
use Modules\GameTables\Filament\Widgets\PendingModerationWidget;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\CampaignModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\ContentWarningModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\EventGameTableConfigModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameMasterModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameSystemModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameTableModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\PublisherModel;
use Modules\GameTables\Notifications\ModerationSubmittedNotification;
use Tests\Support\Modules\InteractsWithModulePanel;
use Tests\Support\Modules\ModuleTestCase;

final class GameTablesPanelTest extends ModuleTestCase
{
    use InteractsWithModulePanel;

    protected ?string $moduleName = 'game-tables';

    protected bool $autoEnableModule = true;

    private UserModel $admin;

    private GameSystemModel $gameSystem;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registerOnAdminPanel(
            [
                GameSystemResource::class,
                PublisherResource::class,
                ContentWarningResource::class,
                CampaignResource::class,
                GameTableResource::class,
            ],
            [GameTablesSettings::class],
        );
        $this->admin = $this->actingAsPanelAdmin();
        $this->gameSystem = GameSystemModel::create([
            'id' => (string) Str::uuid(),
            'name' => 'Sistema de prueba',
            'slug' => 'sistema-de-prueba',
            'is_active' => true,
        ]);
    }

    // The catalogs are seeded with many rows, so the lists only need to render
    public function test_catalog_pages_render(): void
    {
        $publisher = PublisherModel::create(['id' => (string) Str::uuid(), 'name' => 'Editorial de prueba', 'slug' => 'editorial-de-prueba', 'is_active' => true]);
        $warning = ContentWarningModel::create(['id' => (string) Str::uuid(), 'name' => 'aviso-de-prueba', 'slug' => 'aviso-de-prueba', 'label' => 'Aviso de prueba', 'severity' => 'mild', 'is_active' => true]);

        Livewire::test(ListGameSystems::class)->assertOk();
        Livewire::test(CreateGameSystem::class)->assertOk();
        Livewire::test(EditGameSystem::class, ['record' => $this->gameSystem->getRouteKey()])->assertOk();

        Livewire::test(ListPublishers::class)->assertOk();
        Livewire::test(CreatePublisher::class)->assertOk();
        Livewire::test(EditPublisher::class, ['record' => $publisher->getRouteKey()])->assertOk();

        Livewire::test(ListContentWarnings::class)->assertOk();
        Livewire::test(CreateContentWarning::class)->assertOk();
        Livewire::test(EditContentWarning::class, ['record' => $warning->getRouteKey()])->assertOk();
    }

    public function test_campaign_pages_render(): void
    {
        $campaign = $this->campaign();

        Livewire::test(ListCampaigns::class)->assertOk()->assertCanSeeTableRecords([$campaign]);
        Livewire::test(CreateCampaign::class)->assertOk();
        Livewire::test(EditCampaign::class, ['record' => $campaign->getRouteKey()])
            ->assertOk()
            ->assertFormSet(['title' => 'La llamada']);
    }

    public function test_game_table_pages_render(): void
    {
        $table = $this->gameTable();

        Livewire::test(ListGameTables::class)->assertOk()->assertCanSeeTableRecords([$table]);
        Livewire::test(CreateGameTable::class)->assertOk();
        Livewire::test(EditGameTable::class, ['record' => $table->getRouteKey()])
            ->assertOk()
            ->assertFormSet(['title' => 'Mesa de prueba'])
            ->assertDontSee(__('game-tables::messages.fields.inherited_from_campaign'));
    }

    public function test_edit_page_marks_game_masters_inherited_from_the_campaign(): void
    {
        $campaign = $this->campaign();
        $gameMaster = GameMasterModel::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Marta',
            'last_name' => 'Directora',
            'email' => 'marta@example.test',
            'role' => 'main',
            'notify_by_email' => false,
            'is_name_public' => true,
        ]);
        $campaign->gameMasters()->attach($gameMaster->id, ['sort_order' => 1]);
        $table = $this->gameTable($campaign);

        Livewire::test(EditGameTable::class, ['record' => $table->getRouteKey()])
            ->assertOk()
            ->assertSee(__('game-tables::messages.fields.inherited_from_campaign'));
    }

    public function test_participants_relation_manager_renders(): void
    {
        $table = $this->gameTable();

        Livewire::test(ParticipantsRelationManager::class, ['ownerRecord' => $table, 'pageClass' => EditGameTable::class])
            ->assertOk();
    }

    public function test_event_config_relation_manager_saves_the_configuration(): void
    {
        $event = EventModel::factory()->create();

        Livewire::test(EventGameTableConfigRelationManager::class, ['ownerRecord' => $event, 'pageClass' => EditEvent::class])
            ->assertOk()
            ->set('configData.tables_enabled', false)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertFalse(EventGameTableConfigModel::query()->where('event_id', $event->id)->firstOrFail()->tables_enabled);
    }

    public function test_settings_pages_render(): void
    {
        Livewire::test(GameTablesSettings::class)->assertOk();
        Livewire::test(ModuleSettingsPage::class, ['module' => 'game-tables'])->assertOk();
    }

    public function test_pending_moderation_widget_lists_tables_awaiting_review(): void
    {
        $table = $this->gameTable();
        $table->update(['frontend_creation_status' => 'pending_review']);

        Livewire::test(PendingModerationWidget::class)->assertOk()->assertCanSeeTableRecords([$table]);
    }

    public function test_the_moderation_email_links_to_the_table_in_the_panel(): void
    {
        $table = $this->gameTable();

        $url = (new ModerationSubmittedNotification($table->id, $table->title, 'Ana'))->toMail(new AnonymousNotifiable())->actionUrl;

        // The panel finds game tables by slug (GameTableModel::getRouteKeyName())
        $this->assertSame(GameTableResource::getUrl('edit', ['record' => $table]), $url);
    }

    private function campaign(): CampaignModel
    {
        return CampaignModel::create([
            'id' => (string) Str::uuid(),
            'game_system_id' => $this->gameSystem->id,
            'created_by' => $this->admin->id,
            'title' => 'La llamada',
            'slug' => 'la-llamada',
            'status' => 'recruiting',
        ]);
    }

    private function gameTable(?CampaignModel $campaign = null): GameTableModel
    {
        return GameTableModel::create([
            'id' => (string) Str::uuid(),
            'game_system_id' => $this->gameSystem->id,
            'campaign_id' => $campaign?->id,
            'created_by' => $this->admin->id,
            'title' => 'Mesa de prueba',
            'slug' => 'mesa-de-prueba',
            'starts_at' => now()->addWeek(),
            'duration_minutes' => 240,
            'table_type' => 'one_shot',
            'table_format' => 'in_person',
            'status' => 'scheduled',
            'min_players' => 2,
            'max_players' => 5,
            'language' => 'es',
        ]);
    }
}
