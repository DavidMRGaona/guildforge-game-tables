<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Feature\Console;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Event as ScheduledEvent;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Illuminate\Testing\PendingCommand;
use Modules\GameTables\Console\Commands\UpdateTableStatusesCommand;
use Modules\GameTables\Domain\Enums\TableStatus;
use Modules\GameTables\Domain\Events\GameTableCompleted;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameSystemModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameTableModel;
use Tests\Support\Modules\ModuleTestCase;

final class UpdateTableStatusesCommandTest extends ModuleTestCase
{
    protected ?string $moduleName = 'game-tables';

    protected bool $autoEnableModule = true;

    private GameTableModel $table;

    protected function setUp(): void
    {
        parent::setUp();

        // The module is booted after the console application exists, so its commands have to be
        // added to it by hand
        $this->app->make(Kernel::class)->registerCommand($this->app->make(UpdateTableStatusesCommand::class));

        Event::fake([GameTableCompleted::class]);
        config()->set('modules.settings.game-tables', []);

        $system = GameSystemModel::create([
            'id' => (string) Str::uuid(),
            'name' => 'Fixture System',
            'slug' => 'fixture-system',
            'is_active' => true,
        ]);
        $this->table = GameTableModel::create([
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

    public function test_nothing_changes_before_the_start(): void
    {
        $this->travelTo(CarbonImmutable::parse('2030-03-10 17:59:00'));

        $this->runCommand('Started 0 table(s), completed 0, skipped 0, failed 0.');

        $this->assertSame(TableStatus::Scheduled, $this->table->refresh()->status);
    }

    public function test_the_table_starts_at_its_start_time(): void
    {
        $this->travelTo(CarbonImmutable::parse('2030-03-10 18:00:00'));

        $this->runCommand('Started 1 table(s), completed 0, skipped 0, failed 0.');

        $this->assertSame(TableStatus::InProgress, $this->table->refresh()->status);
    }

    public function test_a_table_that_already_ended_is_started_and_completed_in_one_run(): void
    {
        $this->travelTo(CarbonImmutable::parse('2030-03-10 22:00:00'));

        $this->runCommand('Started 1 table(s), completed 1, skipped 0, failed 0.');

        $this->assertSame(TableStatus::Completed, $this->table->refresh()->status);
        Event::assertDispatchedTimes(GameTableCompleted::class, 1);
    }

    public function test_nothing_changes_when_the_setting_is_off(): void
    {
        config()->set('modules.settings.game-tables.automation.auto_status_enabled', false);
        $this->travelTo(CarbonImmutable::parse('2030-03-11 09:00:00'));

        $this->runCommand('Automatic status updates are disabled.');

        $this->assertSame(TableStatus::Scheduled, $this->table->refresh()->status);
        Event::assertNotDispatched(GameTableCompleted::class);
    }

    public function test_the_command_is_scheduled_every_five_minutes_without_overlapping(): void
    {
        $events = array_values(array_filter(
            app(Schedule::class)->events(),
            fn (ScheduledEvent $event): bool => str_contains((string) $event->command, 'game-tables:update-statuses'),
        ));

        $this->assertCount(1, $events);
        $this->assertSame('*/5 * * * *', $events[0]->expression);
        $this->assertTrue($events[0]->withoutOverlapping);
        // A killed run must not block the updates for long
        $this->assertSame(10, $events[0]->expiresAt);
    }

    private function runCommand(string $expectedOutput): void
    {
        $command = $this->artisan('game-tables:update-statuses');
        $this->assertInstanceOf(PendingCommand::class, $command);
        $command->expectsOutput($expectedOutput)->assertSuccessful()->run();
    }
}
