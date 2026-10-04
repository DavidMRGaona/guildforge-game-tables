<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Integration\Database;

use Modules\GameTables\Database\Seeders\ContentWarningsSeeder;
use Modules\GameTables\Database\Seeders\GameSystemsSeeder;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\ContentWarningModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameSystemModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\PublisherModel;
use Tests\Support\Modules\ModuleTestCase;

/**
 * The catalog seeders only add what is missing: running them again must not
 * overwrite what admins edited in the panel.
 */
final class CatalogSeedersTest extends ModuleTestCase
{
    protected ?string $moduleName = 'game-tables';

    protected bool $autoEnableModule = true;

    protected function setUp(): void
    {
        parent::setUp();

        // database/ is outside the module's autoloaded src/
        foreach (glob(dirname(__DIR__, 3).'/database/seeders/*Seeder.php') ?: [] as $seeder) {
            require_once $seeder;
        }
    }

    public function test_running_the_catalog_seeders_again_keeps_admin_edits(): void
    {
        $this->seed(GameSystemsSeeder::class);
        $this->seed(ContentWarningsSeeder::class);

        $system = GameSystemModel::query()->firstOrFail();
        $publisher = PublisherModel::query()->firstOrFail();
        $warning = ContentWarningModel::query()->firstOrFail();
        $system->update(['name' => 'Renamed by an admin']);
        $publisher->update(['name' => 'Publisher renamed by an admin']);
        $warning->update(['label' => 'Label edited by an admin']);

        $this->seed(GameSystemsSeeder::class);
        $this->seed(ContentWarningsSeeder::class);

        $this->assertSame('Renamed by an admin', $system->fresh()?->name);
        $this->assertSame('Publisher renamed by an admin', $publisher->fresh()?->name);
        $this->assertSame('Label edited by an admin', $warning->fresh()?->label);
    }
}
