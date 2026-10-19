<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Integration\Database;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameSystemModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameTableModel;
use Tests\Support\Modules\ModuleTestCase;

final class GuestConfirmationMigrationTest extends ModuleTestCase
{
    protected ?string $moduleName = 'game-tables';

    protected bool $autoEnableModule = true;

    public function test_existing_rows_count_as_confirmed(): void
    {
        $migration = require dirname(__DIR__, 3).'/database/migrations/2026_10_19_000001_add_guest_confirmation_to_gametables_participants_table.php';
        $this->assertInstanceOf(Migration::class, $migration);

        $tableId = $this->createTable();
        $migration->down();

        DB::table('gametables_participants')->insert([
            'id' => (string) Str::uuid(),
            'game_table_id' => $tableId,
            'first_name' => 'Ana',
            'email' => 'ana@example.com',
            'role' => 'player',
            'status' => 'confirmed',
            'created_at' => '2030-02-03 04:05:06',
            'updated_at' => '2030-02-03 04:05:06',
        ]);

        $migration->up();

        $row = DB::table('gametables_participants')->first();
        $this->assertNotNull($row);
        $this->assertSame('2030-02-03 04:05:06', $row->email_confirmed_at);
        $this->assertNull($row->confirmation_mode);
        $this->assertNull($row->confirmation_token);
    }

    private function createTable(): string
    {
        $system = GameSystemModel::create([
            'id' => (string) Str::uuid(),
            'name' => 'Fixture System',
            'slug' => 'fixture-system',
            'is_active' => true,
        ]);
        $creator = UserModel::factory()->create();

        return GameTableModel::create([
            'id' => (string) Str::uuid(),
            'game_system_id' => $system->id,
            'created_by' => $creator->id,
            'title' => 'La cripta olvidada',
            'slug' => 'la-cripta-olvidada',
            'starts_at' => '2030-03-10 18:00:00',
            'duration_minutes' => 240,
            'table_type' => 'one_shot',
            'table_format' => 'in_person',
            'status' => 'scheduled',
            'min_players' => 2,
            'max_players' => 6,
            'language' => 'es',
            'is_published' => true,
        ])->id;
    }
}
