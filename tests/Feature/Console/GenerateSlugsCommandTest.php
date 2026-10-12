<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Feature\Console;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Support\Str;
use Modules\GameTables\Console\Commands\GenerateSlugsCommand;
use Modules\GameTables\Domain\Enums\TableFormat;
use Modules\GameTables\Domain\Enums\TableStatus;
use Modules\GameTables\Domain\Enums\TableType;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameSystemModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameTableModel;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Console\Tester\CommandTester;
use Tests\Support\Modules\ModuleTestCase;

final class GenerateSlugsCommandTest extends ModuleTestCase
{
    protected ?string $moduleName = 'game-tables';

    protected bool $autoEnableModule = true;

    /**
     * @param  array<string, mixed>  $options
     */
    private function runCommand(array $options = []): int
    {
        // The module is enabled after the console kernel registered its commands,
        // so the command runs directly instead of through Artisan
        $command = $this->app->make(GenerateSlugsCommand::class);
        $command->setLaravel($this->app);

        return (new CommandTester($command))->execute($options);
    }

    public function test_it_fills_in_missing_slugs(): void
    {
        $table = $this->table($this->creator());
        DB::table($table->getTable())->where('id', $table->id)->update(['slug' => null]);

        $this->assertSame(0, $this->runCommand());

        $slug = DB::table($table->getTable())->where('id', $table->id)->value('slug');
        $this->assertIsString($slug);
        $this->assertStringStartsWith('la-cripta-olvidada', $slug);
    }

    public function test_dry_run_changes_nothing(): void
    {
        $table = $this->table($this->creator());
        DB::table($table->getTable())->where('id', $table->id)->update(['slug' => null]);

        $this->assertSame(0, $this->runCommand(['--dry-run' => true]));

        $this->assertNull(DB::table($table->getTable())->where('id', $table->id)->value('slug'));
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
