<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Feature\Filament;

use App\Filament\Pages\ModuleSettingsPage;
use App\Filament\Resources\EventResource\Pages\EditEvent;
use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use DateTimeImmutable;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Str;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Modules\GameTables\Domain\Enums\SchedulingMode;
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
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\ParticipantModel;
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

    public function test_the_creation_status_filter_does_not_offer_the_unused_rejected_status(): void
    {
        $this->gameTable();

        /** @var Testable<ListGameTables> $list */
        $list = Livewire::test(ListGameTables::class);
        $filter = $list->instance()->getTable()->getFilter('frontend_creation_status');
        $this->assertInstanceOf(SelectFilter::class, $filter);
        $options = $filter->getOptions();

        $this->assertArrayNotHasKey('rejected', $options);
        $this->assertSame(['draft', 'pending_review', 'approved'], array_keys($options));
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
            'first_name' => 'Jane',
            'last_name' => 'Directora',
            'email' => 'jane@example.test',
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

    // With the waiting list on, the panel refuses a confirmation that would exceed the player seats
    public function test_confirming_a_player_on_a_full_table_shows_a_notice_instead_of_failing(): void
    {
        config()->set('modules.settings.game-tables.registration.waiting_list_enabled', true);
        $table = $this->gameTable();
        $table->update(['max_players' => 1, 'auto_confirm' => false]);
        foreach (['confirmed', 'pending'] as $status) {
            ParticipantModel::create([
                'id' => (string) Str::uuid(),
                'game_table_id' => $table->id,
                'user_id' => UserModel::factory()->create()->id,
                'role' => 'player',
                'status' => $status,
            ]);
        }
        $pending = ParticipantModel::query()->where('game_table_id', $table->id)->where('status', 'pending')->firstOrFail();

        Livewire::test(ParticipantsRelationManager::class, ['ownerRecord' => $table, 'pageClass' => EditGameTable::class])
            ->callTableAction('confirm', $pending)
            ->assertNotified(__('game-tables::messages.errors.table_full'));

        $this->assertSame('pending', $pending->refresh()->status->value);
    }

    public function test_promoting_a_player_on_a_full_table_shows_a_notice_instead_of_failing(): void
    {
        config()->set('modules.settings.game-tables.registration.waiting_list_enabled', true);
        $table = $this->gameTable();
        $table->update(['max_players' => 1]);
        foreach (['confirmed' => null, 'waiting_list' => 1] as $status => $position) {
            ParticipantModel::create([
                'id' => (string) Str::uuid(),
                'game_table_id' => $table->id,
                'user_id' => UserModel::factory()->create()->id,
                'role' => 'player',
                'status' => $status,
                'waiting_list_position' => $position,
            ]);
        }
        $waiting = ParticipantModel::query()->where('game_table_id', $table->id)->where('status', 'waiting_list')->firstOrFail();

        Livewire::test(ParticipantsRelationManager::class, ['ownerRecord' => $table, 'pageClass' => EditGameTable::class])
            ->callTableAction('promote', $waiting)
            ->assertNotified(__('game-tables::messages.errors.table_full'));

        $this->assertSame('waiting_list', $waiting->refresh()->status->value);
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

    public function test_a_mistyped_time_slot_is_a_form_error_not_a_server_error(): void
    {
        $event = EventModel::factory()->create();

        $component = Livewire::test(EventGameTableConfigRelationManager::class, ['ownerRecord' => $event, 'pageClass' => EditEvent::class])
            ->set('configData.tables_enabled', true)
            ->set('configData.scheduling_mode', SchedulingMode::SlotBased->value)
            ->set('configData.time_slots', ['slot' => ['label' => 'Mañana', 'start_time' => '10::00', 'end_time' => '14:00', 'max_tables' => null]])
            ->set('configData.creation_opens_at', '2026-11-01 10:00:00')
            ->call('save')
            ->assertHasErrors(['configData.time_slots.slot.start_time']);

        $this->assertNull(EventGameTableConfigModel::query()->where('event_id', $event->id)->first());

        // Typing the seconds is not a mistake: the time picker hands over hours and minutes
        $component->set('configData.time_slots.slot.start_time', '10:00:00')
            ->call('save')
            ->assertHasNoErrors();

        $slots = EventGameTableConfigModel::query()->where('event_id', $event->id)->firstOrFail()->time_slots ?? [];
        $this->assertCount(1, $slots);
        $this->assertSame('10:00', (new DateTimeImmutable($slots[0]['start_time']))->format('H:i'));
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

        $url = (new ModerationSubmittedNotification($table->id, $table->title, 'John'))->toMail(new AnonymousNotifiable)->actionUrl;

        // The panel finds game tables by slug (GameTableModel::getRouteKeyName())
        $this->assertSame(GameTableResource::getUrl('edit', ['record' => $table]), $url);
    }

    public function test_the_moderation_email_of_a_missing_table_links_to_the_list(): void
    {
        $url = (new ModerationSubmittedNotification((string) Str::uuid(), 'Mesa borrada', 'John'))->toMail(new AnonymousNotifiable)->actionUrl;

        $this->assertSame(GameTableResource::getUrl('index'), $url);
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
