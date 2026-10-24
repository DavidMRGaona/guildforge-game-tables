<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Feature\Console;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Event as ScheduledEvent;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Str;
use Illuminate\Testing\PendingCommand;
use Modules\GameTables\Console\Commands\PruneParticipantStatusLogCommand;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameSystemModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameTableModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\ParticipantStatusLogModel;
use Tests\Support\Modules\ModuleTestCase;

/**
 * The registration history is kept for 12 months after its table started.
 */
final class PruneParticipantStatusLogCommandTest extends ModuleTestCase
{
    protected ?string $moduleName = 'game-tables';

    protected bool $autoEnableModule = true;

    private GameSystemModel $system;

    protected function setUp(): void
    {
        parent::setUp();

        // The module is booted after the console application exists, so its commands have to be
        // added to it by hand
        $this->app->make(Kernel::class)->registerCommand($this->app->make(PruneParticipantStatusLogCommand::class));

        $this->travelTo(CarbonImmutable::parse('2026-11-02 10:00:00'));
        $this->system = GameSystemModel::create(['id' => (string) Str::uuid(), 'name' => 'Sistema', 'slug' => 'sistema', 'is_active' => true]);
    }

    public function test_deletes_the_history_of_tables_that_started_more_than_12_months_ago(): void
    {
        $old = $this->tableStartingAt(now()->subMonths(12)->subDay());
        $recent = $this->tableStartingAt(now()->subMonths(11));
        $upcoming = $this->tableStartingAt(now()->addWeek());
        $this->logRow($old);
        $this->logRow($old);
        $kept = [$this->logRow($recent)->id, $this->logRow($upcoming)->id];

        $this->runCommand('Pruned 2 registration history row(s).');

        $this->assertEqualsCanonicalizing($kept, ParticipantStatusLogModel::query()->pluck('id')->all());
    }

    public function test_is_scheduled_daily(): void
    {
        $events = array_values(array_filter(
            app(Schedule::class)->events(),
            fn (ScheduledEvent $event): bool => str_contains((string) $event->command, 'game-tables:prune-participant-status-log'),
        ));

        $this->assertCount(1, $events);
        $this->assertSame('0 0 * * *', $events[0]->expression);
        $this->assertTrue($events[0]->withoutOverlapping);
        $this->assertSame(10, $events[0]->expiresAt);
    }

    private function tableStartingAt(mixed $startsAt): GameTableModel
    {
        return GameTableModel::create([
            'id' => (string) Str::uuid(),
            'game_system_id' => $this->system->id,
            'created_by' => UserModel::factory()->create()->id,
            'title' => 'Mesa',
            'slug' => 'mesa-'.Str::lower(Str::random(8)),
            'starts_at' => $startsAt,
            'duration_minutes' => 240,
            'table_type' => 'one_shot',
            'table_format' => 'in_person',
            'status' => 'scheduled',
            'min_players' => 1,
            'max_players' => 5,
            'max_spectators' => 0,
            'language' => 'es',
            'is_published' => true,
        ]);
    }

    private function logRow(GameTableModel $table): ParticipantStatusLogModel
    {
        return ParticipantStatusLogModel::query()->create([
            'game_table_id' => $table->id,
            'actor_is_system' => true,
            'from_status' => null,
            'to_status' => 'confirmed',
        ]);
    }

    private function runCommand(string $expectedOutput): void
    {
        $command = $this->artisan('game-tables:prune-participant-status-log');
        $this->assertInstanceOf(PendingCommand::class, $command);
        $command->expectsOutput($expectedOutput)->assertSuccessful()->run();
    }
}
