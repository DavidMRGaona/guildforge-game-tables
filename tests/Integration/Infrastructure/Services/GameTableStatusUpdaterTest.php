<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Integration\Infrastructure\Services;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Carbon\CarbonImmutable;
use DateTimeImmutable;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Modules\GameTables\Application\DTOs\GameTableResponseDTO;
use Modules\GameTables\Application\Services\EligibilityServiceInterface;
use Modules\GameTables\Application\Services\GameTableServiceInterface;
use Modules\GameTables\Domain\Entities\GameTable;
use Modules\GameTables\Domain\Enums\TableStatus;
use Modules\GameTables\Domain\Events\GameTableCompleted;
use Modules\GameTables\Domain\Repositories\GameTableRepositoryInterface;
use Modules\GameTables\Domain\ValueObjects\GameTableId;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameSystemModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameTableModel;
use Modules\GameTables\Infrastructure\Services\GameTableStatusUpdater;
use RuntimeException;
use Tests\Support\Modules\ModuleTestCase;

final class GameTableStatusUpdaterTest extends ModuleTestCase
{
    protected ?string $moduleName = 'game-tables';

    protected bool $autoEnableModule = true;

    private GameSystemModel $system;

    private UserModel $creator;

    protected function setUp(): void
    {
        parent::setUp();

        Event::fake([GameTableCompleted::class]);
        $this->creator = UserModel::factory()->create();
        $this->system = GameSystemModel::create([
            'id' => (string) Str::uuid(),
            'name' => 'Fixture System',
            'slug' => 'fixture-system',
            'is_active' => true,
        ]);
    }

    public function test_a_scheduled_table_starts_exactly_at_its_start_time(): void
    {
        $table = $this->table(TableStatus::Scheduled, '2030-03-10 18:00:00');

        $this->assertSame($this->counts(), $this->update('2030-03-10 17:59:00'));
        $this->assertSame(TableStatus::Scheduled, $table->refresh()->status);

        $this->assertSame($this->counts(started: 1), $this->update('2030-03-10 18:00:00'));
        $this->assertSame(TableStatus::InProgress, $table->refresh()->status);
    }

    public function test_a_full_table_starts_too(): void
    {
        $table = $this->table(TableStatus::Full, '2030-03-10 18:00:00');

        $this->update('2030-03-10 18:05:00');

        $this->assertSame(TableStatus::InProgress, $table->refresh()->status);
    }

    public function test_a_table_in_progress_completes_exactly_at_its_end(): void
    {
        $table = $this->table(TableStatus::InProgress, '2030-03-10 18:00:00', durationMinutes: 240);

        $this->assertSame($this->counts(), $this->update('2030-03-10 21:59:00'));
        $this->assertSame(TableStatus::InProgress, $table->refresh()->status);
        Event::assertNotDispatched(GameTableCompleted::class);

        $this->assertSame($this->counts(completed: 1), $this->update('2030-03-10 22:00:00'));
        $this->assertSame(TableStatus::Completed, $table->refresh()->status);
        Event::assertDispatched(GameTableCompleted::class, fn (GameTableCompleted $event): bool => $event->gameTableId === $table->id);
    }

    public function test_a_scheduled_table_that_already_ended_goes_through_both_steps_in_one_run(): void
    {
        $table = $this->table(TableStatus::Scheduled, '2030-03-10 18:00:00', durationMinutes: 240);

        $this->assertSame($this->counts(started: 1, completed: 1), $this->update('2030-03-11 09:00:00'));

        $this->assertSame(TableStatus::Completed, $table->refresh()->status);
        Event::assertDispatchedTimes(GameTableCompleted::class, 1);
    }

    public function test_a_second_run_changes_and_announces_nothing_more(): void
    {
        $this->table(TableStatus::Scheduled, '2030-03-10 18:00:00');

        $this->update('2030-03-11 09:00:00');
        $this->assertSame($this->counts(), $this->update('2030-03-11 09:05:00'));

        Event::assertDispatchedTimes(GameTableCompleted::class, 1);
    }

    public function test_drafts_cancelled_and_completed_tables_are_never_touched(): void
    {
        $draft = $this->table(TableStatus::Draft, '2030-03-10 18:00:00');
        $cancelled = $this->table(TableStatus::Cancelled, '2030-03-10 18:00:00');
        $completed = $this->table(TableStatus::Completed, '2030-03-10 18:00:00');

        $this->assertSame($this->counts(), $this->update('2030-03-12 09:00:00'));

        $this->assertSame(TableStatus::Draft, $draft->refresh()->status);
        $this->assertSame(TableStatus::Cancelled, $cancelled->refresh()->status);
        $this->assertSame(TableStatus::Completed, $completed->refresh()->status);
        Event::assertNotDispatched(GameTableCompleted::class);
    }

    // The list was read before someone cancelled the table by hand: it is read again and skipped
    public function test_a_table_changed_by_hand_after_the_query_is_skipped(): void
    {
        $cancelled = $this->table(TableStatus::Cancelled, '2030-03-10 18:00:00');
        $real = app(GameTableRepositoryInterface::class);
        $repository = $this->createStub(GameTableRepositoryInterface::class);
        $repository->method('idsStartedInStatuses')->willReturnCallback(
            fn (array $statuses): array => $statuses === [TableStatus::Scheduled, TableStatus::Full] ? [$cancelled->id] : [],
        );
        $repository->method('find')->willReturnCallback(fn (GameTableId $id): ?GameTable => $real->find($id));

        $counts = (new GameTableStatusUpdater($repository, app(GameTableServiceInterface::class)))
            ->update(new DateTimeImmutable('2030-03-10 18:05:00'));

        $this->assertSame($this->counts(skipped: 1), $counts);
        $this->assertSame(TableStatus::Cancelled, $cancelled->refresh()->status);
    }

    // Someone cancelled the table by hand after it was read again: the save refuses the transition
    public function test_a_table_changed_by_hand_between_the_read_and_the_save_is_skipped_without_an_error(): void
    {
        $log = Log::spy();
        $table = $this->table(TableStatus::Scheduled, '2030-03-10 18:00:00');

        $counts = $this->updateWithFind(function (GameTable $read) use ($table): void {
            GameTableModel::query()->whereKey($table->id)->update(['status' => TableStatus::Cancelled->value]);
        });

        $this->assertSame($this->counts(skipped: 1), $counts);
        $this->assertSame(TableStatus::Cancelled, $table->refresh()->status);
        $log->shouldNotHaveReceived('error');
    }

    // Someone deleted the table after it was read again: nothing left to move, so it is skipped
    public function test_a_table_deleted_between_the_read_and_the_save_is_skipped_without_an_error(): void
    {
        $log = Log::spy();
        $table = $this->table(TableStatus::Scheduled, '2030-03-10 18:00:00');

        $counts = $this->updateWithFind(function (GameTable $read) use ($table): void {
            GameTableModel::query()->whereKey($table->id)->delete();
        });

        $this->assertSame($this->counts(skipped: 1), $counts);
        $this->assertFalse(GameTableModel::query()->whereKey($table->id)->exists());
        $log->shouldNotHaveReceived('error');
    }

    public function test_a_failing_table_is_logged_and_the_rest_still_change(): void
    {
        $log = Log::spy();
        $broken = $this->table(TableStatus::Scheduled, '2030-03-10 18:00:00');
        $healthy = $this->table(TableStatus::Scheduled, '2030-03-10 18:00:00');
        $real = app(GameTableServiceInterface::class);
        $service = $this->createStub(GameTableServiceInterface::class);
        $service->method('start')->willReturnCallback(
            fn (string $id): GameTableResponseDTO => $id === $broken->id
                ? throw new RuntimeException('Connection lost')
                : $real->start($id),
        );

        $counts = (new GameTableStatusUpdater(app(GameTableRepositoryInterface::class), $service))
            ->update(new DateTimeImmutable('2030-03-10 18:05:00'));

        $this->assertSame($this->counts(started: 1, failed: 1), $counts);
        $this->assertSame(TableStatus::Scheduled, $broken->refresh()->status);
        $this->assertSame(TableStatus::InProgress, $healthy->refresh()->status);
        $log->shouldHaveReceived('error')
            ->once()
            ->withArgs(fn (string $message, array $context): bool => $message === 'game-tables: automatic status update failed'
                && $context['game_table_id'] === $broken->id
                && $context['to'] === 'in_progress');
    }

    // The eligibility service reads the real clock, so this test uses real times
    public function test_a_table_that_admits_registrations_in_play_keeps_admitting_them_once_started(): void
    {
        $table = $this->table(
            TableStatus::Scheduled,
            CarbonImmutable::now()->subHour()->format('Y-m-d H:i:s'),
            durationMinutes: 240,
            attributes: ['accepts_registrations_in_progress' => true],
        );

        app(GameTableStatusUpdater::class)->update(CarbonImmutable::now());

        $this->assertSame(TableStatus::InProgress, $table->refresh()->status);
        $this->assertTrue(app(EligibilityServiceInterface::class)->guestEligibility($table->id)['eligible']);
    }

    /**
     * @return array{started: int, completed: int, skipped: int, failed: int}
     */
    private function update(string $now): array
    {
        return app(GameTableStatusUpdater::class)->update(new DateTimeImmutable($now));
    }

    /**
     * Runs the start step for the scheduled tables at 18:05 with the real service, but runs
     * $afterRead right after the updater reads each table again (before the service saves it).
     *
     * @param  callable(GameTable): void  $afterRead
     * @return array{started: int, completed: int, skipped: int, failed: int}
     */
    private function updateWithFind(callable $afterRead): array
    {
        $real = app(GameTableRepositoryInterface::class);
        $repository = $this->createStub(GameTableRepositoryInterface::class);
        $repository->method('idsStartedInStatuses')->willReturnCallback(
            fn (array $statuses, DateTimeImmutable $now): array => $statuses === [TableStatus::Scheduled, TableStatus::Full]
                ? $real->idsStartedInStatuses($statuses, $now)
                : [],
        );
        $repository->method('find')->willReturnCallback(function (GameTableId $id) use ($real, $afterRead): ?GameTable {
            $table = $real->find($id);
            if ($table !== null) {
                $afterRead($table);
            }

            return $table;
        });

        return (new GameTableStatusUpdater($repository, app(GameTableServiceInterface::class)))
            ->update(new DateTimeImmutable('2030-03-10 18:05:00'));
    }

    /**
     * @return array{started: int, completed: int, skipped: int, failed: int}
     */
    private function counts(int $started = 0, int $completed = 0, int $skipped = 0, int $failed = 0): array
    {
        return ['started' => $started, 'completed' => $completed, 'skipped' => $skipped, 'failed' => $failed];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function table(TableStatus $status, string $startsAt, int $durationMinutes = 240, array $attributes = []): GameTableModel
    {
        return GameTableModel::create([
            'id' => (string) Str::uuid(),
            'game_system_id' => $this->system->id,
            'created_by' => $this->creator->id,
            'title' => 'La cripta olvidada',
            'slug' => 'la-cripta-olvidada-'.Str::lower(Str::random(8)),
            'starts_at' => $startsAt,
            'duration_minutes' => $durationMinutes,
            'table_type' => 'one_shot',
            'table_format' => 'in_person',
            'status' => $status->value,
            'min_players' => 2,
            'max_players' => 6,
            'language' => 'es',
            'registration_type' => 'everyone',
            'auto_confirm' => true,
            'is_published' => $status !== TableStatus::Draft,
            ...$attributes,
        ]);
    }
}
