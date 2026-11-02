<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Feature\Infrastructure\Knowledge;

use App\Application\Knowledge\Services\KnowledgeQueryServiceInterface;
use App\Domain\Knowledge\Enums\GuideAudience;
use App\Domain\Knowledge\ValueObjects\GuideId;
use App\Filament\Pages\KnowledgeBasePage;
use App\Infrastructure\Persistence\Eloquent\Models\PermissionModel;
use App\Infrastructure\Persistence\Eloquent\Models\RoleModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Modules\GameTables\Domain\Enums\GameMasterRole;
use Modules\GameTables\Filament\Pages\GameTablesSettings;
use Modules\GameTables\Filament\Resources\CampaignResource;
use Modules\GameTables\Filament\Resources\CampaignResource\Pages\CreateCampaign;
use Modules\GameTables\Filament\Resources\CampaignResource\Pages\EditCampaign;
use Modules\GameTables\Filament\Resources\CampaignResource\Pages\ListCampaigns;
use Modules\GameTables\Filament\Resources\ContentWarningResource;
use Modules\GameTables\Filament\Resources\ContentWarningResource\Pages\ListContentWarnings;
use Modules\GameTables\Filament\Resources\GameSystemResource;
use Modules\GameTables\Filament\Resources\GameSystemResource\Pages\EditGameSystem;
use Modules\GameTables\Filament\Resources\GameSystemResource\Pages\ListGameSystems;
use Modules\GameTables\Filament\Resources\GameTableResource;
use Modules\GameTables\Filament\Resources\GameTableResource\Pages\CreateGameTable;
use Modules\GameTables\Filament\Resources\GameTableResource\Pages\EditGameTable;
use Modules\GameTables\Filament\Resources\GameTableResource\Pages\ListGameTables;
use Modules\GameTables\Filament\Resources\PublisherResource;
use Modules\GameTables\Filament\Resources\PublisherResource\Pages\ListPublishers;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\CampaignModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameMasterModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameSystemModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameTableModel;
use Tests\Support\Modules\InteractsWithModulePanel;
use Tests\Support\Modules\ModuleTestCase;

/**
 * Who sees each of the module's guides, and the help button on its panel pages.
 */
final class GameTablesGuidesTest extends ModuleTestCase
{
    use InteractsWithModulePanel;

    /** For every account: taking part in tables and campaigns. */
    private const array EVERYONE_GUIDES = [
        'game-tables.campaign-invitations',
        'game-tables.campaigns-overview',
        'game-tables.cancel-sign-up',
        'game-tables.join-campaign',
        'game-tables.sign-up',
        'game-tables.tables-overview',
    ];

    /** For whoever the creation settings let create tables and campaigns. */
    private const array CREATOR_GUIDES = [
        'game-tables.create-campaign',
        'game-tables.create-table',
        'game-tables.create-table-for-event',
        'game-tables.review-and-publish',
    ];

    private const array PANEL_GUIDES = [
        'game-tables.panel.campaign-roster',
        'game-tables.panel.campaign-sessions',
        'game-tables.panel.catalog',
        'game-tables.panel.create-campaign',
        'game-tables.panel.create-table',
        'game-tables.panel.event-tables',
        'game-tables.panel.moderation',
        'game-tables.panel.overview',
        'game-tables.panel.participants',
        'game-tables.panel.settings',
        'game-tables.panel.table-lifecycle',
        'game-tables.panel.web-creation-settings',
    ];

    protected ?string $moduleName = 'game-tables';

    protected bool $autoEnableModule = true;

    private GameSystemModel $gameSystem;

    protected function setUp(): void
    {
        parent::setUp();

        $this->configureCreation(enabled: false);
        $this->registerOnAdminPanel(
            [GameTableResource::class, CampaignResource::class, GameSystemResource::class, PublisherResource::class, ContentWarningResource::class],
            [GameTablesSettings::class],
        );
        $this->gameSystem = GameSystemModel::create([
            'id' => (string) Str::uuid(),
            'name' => 'Sistema de prueba',
            'slug' => 'sistema-de-prueba',
            'is_active' => true,
        ]);
        $this->artisanCommand('knowledge:sync')->assertSuccessful()->run();
    }

    public function test_without_creation_or_direction_a_person_sees_only_the_guides_to_take_part(): void
    {
        $this->assertSame(self::EVERYONE_GUIDES, $this->visibleSiteGuides(UserModel::factory()->create()));
    }

    public function test_the_creation_guides_follow_the_creation_settings(): void
    {
        $this->configureCreation(enabled: true);

        $this->assertSame($this->sorted([...self::EVERYONE_GUIDES, ...self::CREATOR_GUIDES]), $this->visibleSiteGuides(UserModel::factory()->create()));
    }

    public function test_directors_see_the_guides_of_what_they_direct(): void
    {
        $this->configureCreation(enabled: true);
        $director = UserModel::factory()->create();
        $this->campaignDirectedBy($director);
        $this->tableDirectedBy($director);

        $this->assertSame($this->sorted([
            ...self::EVERYONE_GUIDES,
            ...self::CREATOR_GUIDES,
            'game-tables.add-session',
            'game-tables.manage-registrations',
            'game-tables.manage-roster',
        ]), $this->visibleSiteGuides($director));
    }

    public function test_every_guide_renders_without_raw_markup(): void
    {
        $this->configureCreation(enabled: true);
        $director = UserModel::factory()->create();
        $this->campaignDirectedBy($director);
        $this->tableDirectedBy($director);
        $admin = UserModel::factory()->admin()->create();

        $this->assertSame(self::PANEL_GUIDES, $this->visiblePanelGuides($admin));

        $guides = [
            ...array_map(static fn (string $id): array => [$director, GuideAudience::Site, $id], $this->visibleSiteGuides($director)),
            ...array_map(static fn (string $id): array => [$admin, GuideAudience::Panel, $id], self::PANEL_GUIDES),
        ];

        foreach ($guides as [$user, $audience, $id]) {
            $guide = $this->queries()->guide($user, $audience, GuideId::fromString($id));
            $this->assertNotNull($guide, $id);
            $this->assertStringNotContainsString('[[', $guide->html, $id);
            $this->assertStringNotContainsString('{{', $guide->html, $id);
            $this->assertStringNotContainsString('[!', $guide->html, $id);
        }
    }

    public function test_each_permission_shows_only_its_panel_guides(): void
    {
        $this->assertSame([
            'game-tables.panel.campaign-sessions',
            'game-tables.panel.create-table',
        ], $this->visiblePanelGuides($this->userWith('gametables:gametables.create')));

        $this->assertSame([
            'game-tables.panel.campaign-roster',
            'game-tables.panel.moderation',
        ], $this->visiblePanelGuides($this->userWith('gametables:campaigns.update')));

        $this->assertSame([
            'game-tables.panel.settings',
            'game-tables.panel.web-creation-settings',
        ], $this->visiblePanelGuides($this->userWith('gametables:gametables.settings')));

        $this->assertSame(['game-tables.panel.catalog'], $this->visiblePanelGuides($this->userWith('gametables:gamesystems.view_any')));

        $this->assertSame([], $this->visiblePanelGuides(UserModel::factory()->editor()->create()));
    }

    public function test_the_panel_pages_open_their_guide(): void
    {
        $this->actingAsPanelAdmin();
        $table = $this->tableDirectedBy(UserModel::factory()->create());
        $campaign = $this->campaignDirectedBy(UserModel::factory()->create());

        $pages = [
            [ListGameTables::class, [], 'game-tables.panel.overview'],
            [CreateGameTable::class, [], 'game-tables.panel.create-table'],
            [EditGameTable::class, ['record' => $table->getRouteKey()], 'game-tables.panel.table-lifecycle'],
            [ListCampaigns::class, [], 'game-tables.panel.overview'],
            [CreateCampaign::class, [], 'game-tables.panel.create-campaign'],
            [EditCampaign::class, ['record' => $campaign->getRouteKey()], 'game-tables.panel.campaign-roster'],
            [ListGameSystems::class, [], 'game-tables.panel.catalog'],
            [EditGameSystem::class, ['record' => $this->gameSystem->getRouteKey()], 'game-tables.panel.catalog'],
            [ListPublishers::class, [], 'game-tables.panel.catalog'],
            [ListContentWarnings::class, [], 'game-tables.panel.catalog'],
            [GameTablesSettings::class, [], 'game-tables.panel.settings'],
        ];

        foreach ($pages as [$page, $parameters, $guide]) {
            Livewire::test($page, $parameters)
                ->assertActionVisible('contextualHelp')
                ->assertActionHasUrl('contextualHelp', KnowledgeBasePage::getUrl(['guia' => $guide]));
        }
    }

    private function configureCreation(bool $enabled): void
    {
        config()->set('modules.settings.game-tables', [
            'frontend_creation' => [
                'enabled' => $enabled,
                'access_level' => 'registered',
                'allowed_content' => 'both',
                'publication' => ['mode' => 'approval'],
            ],
        ]);
    }

    private function gameMaster(UserModel $user): GameMasterModel
    {
        return GameMasterModel::create([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'role' => GameMasterRole::Main,
            'notify_by_email' => true,
            'is_name_public' => true,
        ]);
    }

    private function campaignDirectedBy(UserModel $director): CampaignModel
    {
        $campaign = CampaignModel::create([
            'id' => (string) Str::uuid(),
            'game_system_id' => $this->gameSystem->id,
            'created_by' => UserModel::factory()->create()->id,
            'title' => 'Campaña dirigida',
            'slug' => 'campana-dirigida-'.Str::random(6),
            'frequency' => 'weekly',
            'status' => 'recruiting',
            'max_players' => 5,
            'is_published' => true,
        ]);
        $campaign->gameMasters()->attach($this->gameMaster($director)->id, ['sort_order' => 1]);

        return $campaign;
    }

    private function tableDirectedBy(UserModel $director): GameTableModel
    {
        $table = GameTableModel::create([
            'id' => (string) Str::uuid(),
            'game_system_id' => $this->gameSystem->id,
            'created_by' => UserModel::factory()->create()->id,
            'title' => 'Mesa dirigida',
            'slug' => 'mesa-dirigida-'.Str::random(6),
            'starts_at' => now()->addWeek(),
            'duration_minutes' => 240,
            'table_type' => 'one_shot',
            'table_format' => 'in_person',
            'status' => 'scheduled',
            'min_players' => 1,
            'max_players' => 5,
            'language' => 'es',
            'experience_level' => 'none',
            'character_creation' => 'pre_generated',
            'registration_type' => 'everyone',
            'auto_confirm' => false,
            'delegates_registrations' => true,
            'is_published' => true,
            'published_at' => now(),
        ]);
        $table->gameMasters()->attach($this->gameMaster($director)->id, ['source' => 'local', 'excluded' => false, 'sort_order' => 1]);

        return $table;
    }

    /**
     * @return list<string>
     */
    private function visibleSiteGuides(UserModel $user): array
    {
        return $this->moduleGuides($this->queries()->visibleIds($user, GuideAudience::Site));
    }

    /**
     * @return list<string>
     */
    private function visiblePanelGuides(UserModel $user): array
    {
        return $this->moduleGuides($this->queries()->visibleIds($user, GuideAudience::Panel));
    }

    /**
     * @param  array<string>  $ids
     * @return list<string>
     */
    private function moduleGuides(array $ids): array
    {
        return $this->sorted(array_filter($ids, static fn (string $id): bool => str_starts_with($id, 'game-tables.')));
    }

    /**
     * @param  array<string>  $ids
     * @return list<string>
     */
    private function sorted(array $ids): array
    {
        $ids = array_values($ids);
        sort($ids);

        return $ids;
    }

    private function userWith(string ...$permissionKeys): UserModel
    {
        $role = RoleModel::query()->create(['name' => 'tester_'.uniqid(), 'display_name' => 'Permissions tester']);

        foreach ($permissionKeys as $key) {
            $permission = PermissionModel::query()->firstOrCreate(
                ['key' => $key],
                ['label' => $key, 'resource' => 'gametables', 'action' => 'test', 'module' => 'gametables'],
            );
            $role->permissions()->attach($permission->id);
        }

        $user = UserModel::factory()->editor()->create();
        $user->roles()->attach($role->id);

        return $user;
    }

    private function queries(): KnowledgeQueryServiceInterface
    {
        return $this->app->make(KnowledgeQueryServiceInterface::class);
    }
}
