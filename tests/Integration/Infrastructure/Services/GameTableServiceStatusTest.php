<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Integration\Infrastructure\Services;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Modules\GameTables\Application\Services\GameTableServiceInterface;
use Modules\GameTables\Domain\Enums\TableFormat;
use Modules\GameTables\Domain\Enums\TableStatus;
use Modules\GameTables\Domain\Enums\TableType;
use Modules\GameTables\Domain\Events\GameTableCancelled;
use Modules\GameTables\Domain\Events\GameTableCompleted;
use Modules\GameTables\Domain\Exceptions\InvalidStatusTransitionException;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameSystemModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameTableModel;
use Tests\Support\Modules\ModuleTestCase;

final class GameTableServiceStatusTest extends ModuleTestCase
{
    protected ?string $moduleName = 'game-tables';

    protected bool $autoEnableModule = true;

    public function test_starting_and_completing_a_table_saves_it_and_announces_the_completion(): void
    {
        Event::fake([GameTableCompleted::class]);
        $table = $this->table(TableStatus::Scheduled);
        $service = app(GameTableServiceInterface::class);

        $this->assertSame(TableStatus::InProgress, $service->start($table->id)->status);
        $this->assertSame(TableStatus::InProgress, $table->refresh()->status);
        Event::assertNotDispatched(GameTableCompleted::class);

        $this->assertSame(TableStatus::Completed, $service->complete($table->id)->status);
        $this->assertSame(TableStatus::Completed, $table->refresh()->status);
        Event::assertDispatchedTimes(GameTableCompleted::class, 1);
        Event::assertDispatched(GameTableCompleted::class, fn (GameTableCompleted $event): bool => $event->gameTableId === $table->id);
    }

    public function test_a_draft_table_is_not_started(): void
    {
        $table = $this->table(TableStatus::Draft);

        $this->expectException(InvalidStatusTransitionException::class);

        try {
            app(GameTableServiceInterface::class)->start($table->id);
        } finally {
            $this->assertSame(TableStatus::Draft, $table->refresh()->status);
        }
    }

    public function test_a_cancelled_table_is_not_completed_nor_announced(): void
    {
        Event::fake([GameTableCompleted::class]);
        $table = $this->table(TableStatus::Cancelled);

        try {
            app(GameTableServiceInterface::class)->complete($table->id);
            $this->fail('A cancelled table was completed');
        } catch (InvalidStatusTransitionException) {
            // expected
        }

        $this->assertSame(TableStatus::Cancelled, $table->refresh()->status);
        Event::assertNotDispatched(GameTableCompleted::class);
    }

    public function test_a_completed_table_is_not_cancelled_nor_announced(): void
    {
        Event::fake([GameTableCancelled::class]);
        $table = $this->table(TableStatus::Completed);

        try {
            app(GameTableServiceInterface::class)->cancel($table->id);
            $this->fail('A completed table was cancelled');
        } catch (InvalidStatusTransitionException) {
            // expected
        }

        $this->assertSame(TableStatus::Completed, $table->refresh()->status);
        Event::assertNotDispatched(GameTableCancelled::class);
    }

    public function test_changing_the_status_by_hand_follows_the_allowed_transitions(): void
    {
        $table = $this->table(TableStatus::Scheduled);
        $service = app(GameTableServiceInterface::class);

        $this->assertSame(TableStatus::Full, $service->changeStatus($table->id, TableStatus::Full)->status);
        $this->assertSame(TableStatus::Full, $table->refresh()->status);

        $cancelled = $this->table(TableStatus::Cancelled);
        $this->expectException(InvalidStatusTransitionException::class);

        try {
            $service->changeStatus($cancelled->id, TableStatus::Scheduled);
        } finally {
            $this->assertSame(TableStatus::Cancelled, $cancelled->refresh()->status);
        }
    }

    private function table(TableStatus $status): GameTableModel
    {
        $creator = UserModel::factory()->create();
        $system = GameSystemModel::create([
            'id' => (string) Str::uuid(),
            'name' => 'Fixture System',
            'slug' => 'fixture-system-'.bin2hex(random_bytes(4)),
            'game_master_title' => 'Game Master',
            'is_active' => true,
        ]);

        return GameTableModel::create([
            'id' => (string) Str::uuid(),
            'game_system_id' => $system->id,
            'created_by' => $creator->id,
            'title' => 'La cripta olvidada',
            'slug' => 'la-cripta-olvidada-'.bin2hex(random_bytes(4)),
            'starts_at' => '2030-03-10 18:00:00',
            'duration_minutes' => 240,
            'table_type' => TableType::OneShot,
            'table_format' => TableFormat::InPerson,
            'status' => $status,
            'min_players' => 2,
            'max_players' => 6,
            'language' => 'es',
            'auto_confirm' => true,
            'is_published' => $status !== TableStatus::Draft,
        ]);
    }
}
