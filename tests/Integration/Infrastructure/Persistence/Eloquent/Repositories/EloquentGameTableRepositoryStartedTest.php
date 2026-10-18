<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Integration\Infrastructure\Persistence\Eloquent\Repositories;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Support\Str;
use Modules\GameTables\Domain\Enums\TableStatus;
use Modules\GameTables\Domain\Repositories\GameTableRepositoryInterface;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameSystemModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameTableModel;
use Tests\Support\Modules\ModuleTestCase;

final class EloquentGameTableRepositoryStartedTest extends ModuleTestCase
{
    protected ?string $moduleName = 'game-tables';

    protected bool $autoEnableModule = true;

    private GameSystemModel $system;

    private UserModel $creator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->creator = UserModel::factory()->create();
        $this->system = GameSystemModel::create([
            'id' => (string) Str::uuid(),
            'name' => 'Fixture System',
            'slug' => 'fixture-system',
            'is_active' => true,
        ]);
    }

    public function test_it_lists_the_started_tables_of_the_given_statuses_earliest_first(): void
    {
        $later = $this->table(TableStatus::Full, '2030-03-10 18:00:00');
        $earlier = $this->table(TableStatus::Scheduled, '2030-03-09 18:00:00');
        $this->table(TableStatus::Scheduled, '2030-03-10 18:01:00');
        $this->table(TableStatus::Draft, '2030-03-01 18:00:00');
        $this->table(TableStatus::Cancelled, '2030-03-01 18:00:00');
        $this->table(TableStatus::InProgress, '2030-03-01 18:00:00');

        $ids = app(GameTableRepositoryInterface::class)->idsStartedInStatuses(
            [TableStatus::Scheduled, TableStatus::Full],
            new DateTimeImmutable('2030-03-10 18:00:00'),
        );

        $this->assertSame([$earlier->id, $later->id], $ids);
    }

    // starts_at is the wall-clock time of the application: a moment given in another zone is converted
    public function test_a_moment_in_another_timezone_is_compared_in_the_application_timezone(): void
    {
        $previous = date_default_timezone_get();
        date_default_timezone_set('Europe/Madrid');

        try {
            $table = $this->table(TableStatus::Scheduled, '2030-03-10 18:00:00');
            $repository = app(GameTableRepositoryInterface::class);

            // 17:00 UTC is 18:00 in Madrid (CET, UTC+1, in March 2030 before the change)
            $this->assertSame([$table->id], $repository->idsStartedInStatuses(
                [TableStatus::Scheduled],
                new DateTimeImmutable('2030-03-10 17:00:00', new DateTimeZone('UTC')),
            ));
            $this->assertSame([], $repository->idsStartedInStatuses(
                [TableStatus::Scheduled],
                new DateTimeImmutable('2030-03-10 16:59:00', new DateTimeZone('UTC')),
            ));
        } finally {
            date_default_timezone_set($previous);
        }
    }

    private function table(TableStatus $status, string $startsAt): GameTableModel
    {
        return GameTableModel::create([
            'id' => (string) Str::uuid(),
            'game_system_id' => $this->system->id,
            'created_by' => $this->creator->id,
            'title' => 'La cripta olvidada',
            'slug' => 'la-cripta-olvidada-'.Str::lower(Str::random(8)),
            'starts_at' => $startsAt,
            'duration_minutes' => 240,
            'table_type' => 'one_shot',
            'table_format' => 'in_person',
            'status' => $status->value,
            'min_players' => 2,
            'max_players' => 6,
            'language' => 'es',
            'is_published' => $status !== TableStatus::Draft,
        ]);
    }
}
