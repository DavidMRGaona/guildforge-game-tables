<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Integration\Persistence;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Support\Str;
use Modules\GameTables\Domain\Repositories\GameTableRepositoryInterface;
use Modules\GameTables\Domain\ValueObjects\GameTableId;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameSystemModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameTableModel;
use Tests\Support\Modules\ModuleTestCase;

final class GameTableDelegationFlagTest extends ModuleTestCase
{
    protected ?string $moduleName = 'game-tables';

    protected bool $autoEnableModule = true;

    public function test_new_tables_do_not_delegate_by_default(): void
    {
        $model = $this->makeTable();

        $this->assertFalse($model->refresh()->delegates_registrations);

        $entity = $this->repository()->find(new GameTableId($model->id));
        $this->assertNotNull($entity);
        $this->assertFalse($entity->delegatesRegistrations);
    }

    public function test_the_flag_round_trips_through_the_repository(): void
    {
        $model = $this->makeTable(['delegates_registrations' => true]);

        $entity = $this->repository()->find(new GameTableId($model->id));
        $this->assertNotNull($entity);
        $this->assertTrue($entity->delegatesRegistrations);

        $this->repository()->save($entity);

        $this->assertTrue($model->refresh()->delegates_registrations);
    }

    private function repository(): GameTableRepositoryInterface
    {
        return app(GameTableRepositoryInterface::class);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeTable(array $overrides = []): GameTableModel
    {
        $system = GameSystemModel::create(['id' => (string) Str::uuid(), 'name' => 'Sistema', 'slug' => 'sistema', 'is_active' => true]);
        $author = UserModel::factory()->create();

        return GameTableModel::create([
            'id' => (string) Str::uuid(),
            'game_system_id' => $system->id,
            'created_by' => $author->id,
            'title' => 'Mesa de prueba',
            'slug' => 'mesa-de-prueba',
            'starts_at' => now()->addWeek(),
            'duration_minutes' => 240,
            'table_type' => 'one_shot',
            'table_format' => 'in_person',
            'status' => 'scheduled',
            'min_players' => 1,
            'max_players' => 5,
            'language' => 'es',
            'registration_type' => 'everyone',
            'auto_confirm' => true,
            'is_published' => false,
            ...$overrides,
        ]);
    }
}
