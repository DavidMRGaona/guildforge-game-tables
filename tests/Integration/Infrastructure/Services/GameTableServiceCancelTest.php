<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Integration\Infrastructure\Services;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Support\Str;
use Modules\GameTables\Domain\Enums\TableFormat;
use Modules\GameTables\Domain\Enums\TableStatus;
use Modules\GameTables\Domain\Enums\TableType;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameSystemModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameTableModel;
use Illuminate\Support\Facades\Event;
use Modules\GameTables\Application\Services\GameTableServiceInterface;
use Modules\GameTables\Domain\Events\GameTableCancelled;
use Tests\Support\Modules\ModuleTestCase;

final class GameTableServiceCancelTest extends ModuleTestCase
{
    protected ?string $moduleName = 'game-tables';

    protected bool $autoEnableModule = true;

    public function test_cancelling_a_table_announces_it_with_its_start_time(): void
    {
        Event::fake([GameTableCancelled::class]);
        $table = $this->table($this->creator());

        $result = app(GameTableServiceInterface::class)->cancel($table->id);

        $this->assertSame(TableStatus::Cancelled, $result->status);
        Event::assertDispatched(
            GameTableCancelled::class,
            fn (GameTableCancelled $event): bool => $event->gameTableId === $table->id
                && $event->startsAt->format('Y-m-d H:i') === '2026-05-09 17:00',
        );
    }

    private function creator(): UserModel
    {
        return UserModel::create([
            'id' => (string) Str::uuid(),
            'name' => 'Table Creator',
            'email' => 'creator-'.bin2hex(random_bytes(4)).'@example.com',
            'password' => 'password',
        ]);
    }

    private function table(UserModel $creator): GameTableModel
    {
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
            'starts_at' => '2026-05-09 17:00:00',
            'duration_minutes' => 240,
            'table_type' => TableType::OneShot,
            'table_format' => TableFormat::InPerson,
            'status' => TableStatus::Scheduled,
            'min_players' => 2,
            'max_players' => 6,
            'language' => 'es',
            'auto_confirm' => true,
            'is_published' => true,
            'published_at' => now(),
        ]);
    }
}
