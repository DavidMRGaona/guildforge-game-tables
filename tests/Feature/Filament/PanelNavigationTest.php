<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Feature\Filament;

use App\Filament\Clusters\SettingsCluster;
use App\Filament\Support\PanelNavigationGroup;
use Filament\Resources\Resource;
use Modules\GameTables\Filament\Pages\GameTablesSettings;
use Modules\GameTables\Filament\Resources\CampaignResource;
use Modules\GameTables\Filament\Resources\ContentWarningResource;
use Modules\GameTables\Filament\Resources\GameSystemResource;
use Modules\GameTables\Filament\Resources\GameTableResource;
use Modules\GameTables\Filament\Resources\PublisherResource;
use Modules\GameTables\GameTablesServiceProvider;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\Modules\InteractsWithModulePanel;
use Tests\Support\Modules\ModuleTestCase;

/**
 * The module's entries sit in the core's task groups, in the sort slots docs/modules/README.md
 * gives them, and its settings page sits in the core's «Configuración» cluster.
 */
final class PanelNavigationTest extends ModuleTestCase
{
    use InteractsWithModulePanel;

    protected ?string $moduleName = 'game-tables';

    protected bool $autoEnableModule = true;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registerOnAdminPanel(
            [
                GameTableResource::class,
                CampaignResource::class,
                GameSystemResource::class,
                PublisherResource::class,
                ContentWarningResource::class,
            ],
            [GameTablesSettings::class],
        );
    }

    /**
     * @return array<string, array{class-string<resource>, PanelNavigationGroup, int}>
     */
    public static function resourceSlots(): array
    {
        return [
            'tables' => [GameTableResource::class, PanelNavigationGroup::Activities, 10],
            'campaigns' => [CampaignResource::class, PanelNavigationGroup::Activities, 10],
            'game systems' => [GameSystemResource::class, PanelNavigationGroup::Catalogues, 10],
            'publishers' => [PublisherResource::class, PanelNavigationGroup::Catalogues, 20],
            'content warnings' => [ContentWarningResource::class, PanelNavigationGroup::Catalogues, 30],
        ];
    }

    /**
     * @param  class-string<resource>  $resource
     */
    #[DataProvider('resourceSlots')]
    public function test_each_resource_sits_in_its_core_group_and_slot(
        string $resource,
        PanelNavigationGroup $group,
        int $sort,
    ): void {
        // The label as a string: an enum case would form a second group under the same heading
        $this->assertSame($group->getLabel(), $resource::getNavigationGroup());
        $this->assertSame($sort, $resource::getNavigationSort());
    }

    public function test_campaigns_are_a_child_of_tables(): void
    {
        $this->assertSame(GameTableResource::getNavigationLabel(), CampaignResource::getNavigationParentItem());
        $this->assertNull(GameTableResource::getNavigationParentItem());
    }

    public function test_catalogues_have_no_parent_entry(): void
    {
        $this->assertNull(GameSystemResource::getNavigationParentItem());
        $this->assertNull(PublisherResource::getNavigationParentItem());
        $this->assertNull(ContentWarningResource::getNavigationParentItem());
    }

    public function test_the_settings_page_lives_in_the_settings_cluster_in_its_slot(): void
    {
        $this->assertSame(SettingsCluster::class, GameTablesSettings::getCluster());
        $this->assertSame(130, GameTablesSettings::getNavigationSort());
        $this->assertSame('Mesas', GameTablesSettings::getNavigationLabel());
        $this->assertStringStartsWith(
            '/admin/configuracion/',
            (string) parse_url(GameTablesSettings::getUrl(), PHP_URL_PATH),
        );
    }

    public function test_the_settings_page_declares_no_navigation_group_of_its_own(): void
    {
        // The cluster entry sits in the core's «Sistema» group, so the page must not name one
        $this->assertNull(GameTablesSettings::getNavigationGroup());
    }

    public function test_the_module_registers_no_navigation_groups(): void
    {
        $provider = new GameTablesServiceProvider($this->app);

        $this->assertSame([], $provider->registerNavigationGroups());
    }
}
