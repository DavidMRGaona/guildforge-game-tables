<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Feature\Filament;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Filament\Actions\ActionGroup;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Livewire\Livewire;
use Modules\GameTables\Filament\Resources\CampaignResource;
use Modules\GameTables\Filament\Resources\CampaignResource\Pages\EditCampaign;
use Modules\GameTables\Filament\Resources\CampaignResource\Pages\ListCampaigns;
use Modules\GameTables\Filament\Resources\CampaignResource\RelationManagers\InvitationsRelationManager;
use Modules\GameTables\Filament\Resources\CampaignResource\RelationManagers\JoinRequestsRelationManager;
use Modules\GameTables\Filament\Resources\CampaignResource\RelationManagers\RosterRelationManager;
use Modules\GameTables\Filament\Resources\ContentWarningResource;
use Modules\GameTables\Filament\Resources\ContentWarningResource\Pages\ListContentWarnings;
use Modules\GameTables\Filament\Resources\GameSystemResource;
use Modules\GameTables\Filament\Resources\GameSystemResource\Pages\ListGameSystems;
use Modules\GameTables\Filament\Resources\GameTableResource;
use Modules\GameTables\Filament\Resources\GameTableResource\Pages\EditGameTable;
use Modules\GameTables\Filament\Resources\GameTableResource\Pages\ListGameTables;
use Modules\GameTables\Filament\Resources\GameTableResource\RelationManagers\ParticipantsRelationManager;
use Modules\GameTables\Filament\Resources\GameTableResource\RelationManagers\StatusLogRelationManager;
use Modules\GameTables\Filament\Resources\PublisherResource;
use Modules\GameTables\Filament\Resources\PublisherResource\Pages\ListPublishers;
use Modules\GameTables\Filament\Widgets\PendingModerationWidget;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\CampaignModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameSystemModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameTableModel;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\Modules\InteractsWithModulePanel;
use Tests\Support\Modules\ModuleTestCase;

/**
 * The panel tables overflowed their container (more than 1000 px at 375 px, up to 758 px at 1280 px)
 * and pushed the row actions out of sight: the actions of a row sit in one menu and the columns
 * that are not needed to tell a row apart show only from the width where they fit.
 */
final class PanelTablesTest extends ModuleTestCase
{
    use InteractsWithModulePanel;

    protected ?string $moduleName = 'game-tables';

    protected bool $autoEnableModule = true;

    private UserModel $admin;

    private GameSystemModel $system;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registerOnAdminPanel([
            GameTableResource::class,
            CampaignResource::class,
            PublisherResource::class,
            GameSystemResource::class,
            ContentWarningResource::class,
        ]);
        $this->admin = $this->actingAsPanelAdmin();

        $this->system = GameSystemModel::create([
            'id' => (string) Str::uuid(),
            'name' => 'Sistema de prueba',
            'slug' => 'sistema-de-prueba',
            'is_active' => true,
        ]);
    }

    /**
     * The tables with more than one row action, and the actions that go in the menu.
     *
     * @return array<string, array{string, list<string>}>
     */
    public static function tablesWithSeveralActions(): array
    {
        return [
            'tables' => ['tables', ['approve', 'reject', 'edit']],
            'campaigns' => ['campaigns', ['approve', 'reject', 'edit']],
            'participants' => ['participants', ['confirm', 'confirmEmail', 'reject', 'promote', 'cancelRegistration', 'markNoShow', 'edit', 'delete']],
            'join requests' => ['join_requests', ['accept', 'reject']],
            'invitations' => ['invitations', ['resend', 'cancel']],
        ];
    }

    /**
     * @param  list<string>  $expected
     */
    #[DataProvider('tablesWithSeveralActions')]
    public function test_the_row_actions_sit_in_one_menu(string $key, array $expected): void
    {
        $actions = $this->table($key)->getRecordActions();

        $this->assertCount(1, $actions);
        $this->assertInstanceOf(ActionGroup::class, $actions[0]);
        $this->assertEqualsCanonicalizing($expected, array_keys($actions[0]->getFlatActions()));
    }

    /**
     * What each table shows only from a width, and what it always shows. Anything else (toggleable
     * columns hidden by default, for one) is not part of the fit.
     *
     * @return array<string, array{string, array<string, string>, list<string>}>
     */
    public static function responsiveColumns(): array
    {
        return [
            'tables' => ['tables', [
                'starts_at' => 'md', 'is_published' => 'xl', 'participants_count' => 'xl',
                'gameSystem.name' => '2xl', 'table_format' => '2xl', 'frontend_creation_status' => '2xl',
            ], ['title', 'status']],
            'campaigns' => ['campaigns', [
                'is_published' => 'lg', 'accepts_new_players' => 'lg', 'gameSystem.name' => 'xl',
                'frequency' => '2xl', 'current_session' => '2xl',
            ], ['title', 'status']],
            'publishers' => ['publishers', ['country' => 'sm', 'game_systems_count' => 'md'], ['name', 'is_active']],
            'game systems' => ['game_systems', ['publisher.name' => 'sm', 'edition' => 'xl', 'year' => 'xl'], ['name', 'is_active']],
            'content warnings' => ['content_warnings', ['label' => 'sm', 'severity' => 'md'], ['name', 'is_active']],
            'participants' => ['participants', [
                'role' => 'sm', 'guest_flags' => 'md', 'waiting_list_position' => 'md',
                'created_at' => 'xl', 'confirmed_at' => '2xl',
            ], ['display_name', 'status']],
            'status log' => ['status_log', ['created_at' => 'sm', 'actor' => 'md', 'reason' => 'md'], ['participant', 'change']],
            'roster' => ['roster', ['email' => 'sm', 'pivot.created_at' => 'md'], ['name']],
            'join requests' => ['join_requests', ['message' => 'md', 'created_at' => 'sm'], ['user.name', 'status']],
            'invitations' => ['invitations', ['expires_at' => 'sm', 'inviter_name' => 'lg', 'send_count' => 'lg'], ['email']],
            'pending moderation widget' => ['pending_moderation', ['created_at' => 'md', 'game_system' => 'xl', 'creator' => 'xl'], ['type', 'title']],
        ];
    }

    /**
     * @param  array<string, string>  $secondary
     * @param  list<string>  $primary
     */
    #[DataProvider('responsiveColumns')]
    public function test_secondary_columns_show_only_from_the_width_where_they_fit(string $key, array $secondary, array $primary): void
    {
        $table = $this->table($key);

        foreach ($secondary as $name => $breakpoint) {
            $this->assertSame($breakpoint, $table->getColumn($name)?->getVisibleFrom(), "Column {$name} of {$key}");
        }

        foreach ($primary as $name) {
            $this->assertNull($table->getColumn($name)?->getVisibleFrom(), "Column {$name} of {$key} is always visible");
        }
    }

    private function table(string $key): Table
    {
        /** @var HasTable|null $component */
        $component = match ($key) {
            'tables' => Livewire::test(ListGameTables::class)->instance(),
            'campaigns' => Livewire::test(ListCampaigns::class)->instance(),
            'publishers' => Livewire::test(ListPublishers::class)->instance(),
            'game_systems' => Livewire::test(ListGameSystems::class)->instance(),
            'content_warnings' => Livewire::test(ListContentWarnings::class)->instance(),
            'participants' => Livewire::test(ParticipantsRelationManager::class, ['ownerRecord' => $this->gameTable(), 'pageClass' => EditGameTable::class])->instance(),
            'status_log' => Livewire::test(StatusLogRelationManager::class, ['ownerRecord' => $this->gameTable(), 'pageClass' => EditGameTable::class])->instance(),
            'roster' => Livewire::test(RosterRelationManager::class, ['ownerRecord' => $this->campaign(), 'pageClass' => EditCampaign::class])->instance(),
            'join_requests' => Livewire::test(JoinRequestsRelationManager::class, ['ownerRecord' => $this->campaign(), 'pageClass' => EditCampaign::class])->instance(),
            'invitations' => Livewire::test(InvitationsRelationManager::class, ['ownerRecord' => $this->campaign(), 'pageClass' => EditCampaign::class])->instance(),
            // Its table is built from the class itself: the widget renders nothing in a test mount
            'pending_moderation' => null,
            default => throw new InvalidArgumentException("Unknown table {$key}"),
        };

        if ($component === null) {
            $widget = new PendingModerationWidget;

            return $widget->table(Table::make($widget));
        }

        return $component->getTable();
    }

    private function campaign(): CampaignModel
    {
        return CampaignModel::create([
            'id' => (string) Str::uuid(),
            'game_system_id' => $this->system->id,
            'created_by' => $this->admin->id,
            'title' => 'La llamada',
            'slug' => 'la-llamada',
            'status' => 'recruiting',
        ]);
    }

    private function gameTable(): GameTableModel
    {
        return GameTableModel::create([
            'id' => (string) Str::uuid(),
            'game_system_id' => $this->system->id,
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
