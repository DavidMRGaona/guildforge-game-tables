<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Feature;

use Modules\GameTables\Filament\Pages\GameTablesSettings;
use Modules\GameTables\Filament\Resources\CampaignResource;
use Modules\GameTables\Filament\Resources\ContentWarningResource;
use Modules\GameTables\Filament\Resources\GameSystemResource;
use Modules\GameTables\Filament\Resources\GameTableResource;
use Modules\GameTables\Filament\Resources\PublisherResource;
use Tests\Support\Modules\InteractsWithModulePanel;
use Tests\Support\Modules\ModuleTestCase;

/**
 * The module's help guides pass the knowledge base lint, so CI blocks a broken one.
 */
final class KnowledgeGuidesTest extends ModuleTestCase
{
    use InteractsWithModulePanel;

    protected ?string $moduleName = 'game-tables';

    protected bool $autoEnableModule = true;

    public function test_guides_are_valid(): void
    {
        // The panel guides link to the module's panel pages, routed only once they are on the panel
        $this->registerOnAdminPanel(
            [GameTableResource::class, CampaignResource::class, GameSystemResource::class, PublisherResource::class, ContentWarningResource::class],
            [GameTablesSettings::class],
        );

        $this->assertModuleKnowledgeGuidesAreValid('game-tables');
    }
}
