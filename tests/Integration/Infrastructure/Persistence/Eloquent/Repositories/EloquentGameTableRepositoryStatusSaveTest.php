<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Integration\Infrastructure\Persistence\Eloquent\Repositories;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Support\Str;
use Modules\GameTables\Domain\Enums\TableStatus;
use Modules\GameTables\Domain\Exceptions\InvalidStatusTransitionException;
use Modules\GameTables\Domain\Repositories\GameTableRepositoryInterface;
use Modules\GameTables\Domain\ValueObjects\GameTableId;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameSystemModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameTableModel;
use Tests\Support\Modules\ModuleTestCase;

/**
 * A status change is saved only while the table still has the status it was read with, so a
 * change made meanwhile (the automatic update, another user) is never overwritten.
 */
final class EloquentGameTableRepositoryStatusSaveTest extends ModuleTestCase
{
    protected ?string $moduleName = 'game-tables';

    protected bool $autoEnableModule = true;

    private GameTableRepositoryInterface $repository;

    private GameTableModel $model;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = app(GameTableRepositoryInterface::class);

        $system = GameSystemModel::create([
            'id' => (string) Str::uuid(),
            'name' => 'Fixture System',
            'slug' => 'fixture-system',
            'is_active' => true,
        ]);

        $this->model = GameTableModel::create([
            'id' => (string) Str::uuid(),
            'game_system_id' => $system->id,
            'created_by' => UserModel::factory()->create()->id,
            'title' => 'La cripta olvidada',
            'slug' => 'la-cripta-olvidada',
            'starts_at' => '2030-03-10 18:00:00',
            'duration_minutes' => 240,
            'table_type' => 'one_shot',
            'table_format' => 'in_person',
            'status' => TableStatus::Scheduled->value,
            'min_players' => 2,
            'max_players' => 6,
            'language' => 'es',
            'is_published' => true,
        ]);
    }

    public function test_it_saves_the_change_while_the_status_is_the_one_read(): void
    {
        $table = $this->repository->findOrFail(new GameTableId($this->model->id));
        $table->cancel();

        $this->repository->saveIfStatusIs($table, TableStatus::Scheduled);

        $this->assertSame(TableStatus::Cancelled, $this->model->refresh()->status);
    }

    public function test_it_refuses_the_change_when_the_status_moved_meanwhile(): void
    {
        $table = $this->repository->findOrFail(new GameTableId($this->model->id));
        $table->title = 'Título cambiado';
        $table->cancel();

        // The automatic update starts the table after it was read
        GameTableModel::query()->whereKey($this->model->id)->update(['status' => TableStatus::InProgress->value]);

        try {
            $this->repository->saveIfStatusIs($table, TableStatus::Scheduled);
            $this->fail('A table that had moved was overwritten');
        } catch (InvalidStatusTransitionException $e) {
            $this->assertSame('in_progress', $e->from);
            $this->assertSame('cancelled', $e->to);
        }

        $this->model->refresh();
        $this->assertSame(TableStatus::InProgress, $this->model->status);
        $this->assertSame('La cripta olvidada', $this->model->title);
    }
}
