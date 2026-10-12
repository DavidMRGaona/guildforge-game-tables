<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Integration\Providers;

use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use Tests\Support\Modules\ModuleTestCase;

/**
 * The module registers its own relations on the core EventModel, so the core
 * does not need to know about game tables.
 */
final class EventModelRelationsTest extends ModuleTestCase
{
    protected ?string $moduleName = 'game-tables';

    protected bool $autoEnableModule = true;

    public function test_module_registers_the_game_tables_relation_on_events(): void
    {
        $this->assertNotNull((new EventModel())->relationResolver(EventModel::class, 'gameTables'));
    }

    public function test_module_registers_the_game_table_config_relation_on_events(): void
    {
        $this->assertNotNull((new EventModel())->relationResolver(EventModel::class, 'gameTableConfig'));
    }
}
