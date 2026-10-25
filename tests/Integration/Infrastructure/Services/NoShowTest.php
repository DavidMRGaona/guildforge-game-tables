<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Integration\Infrastructure\Services;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Modules\GameTables\Application\Services\RegistrationServiceInterface;
use Modules\GameTables\Domain\Exceptions\InvalidStatusTransitionException;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameSystemModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameTableModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\ParticipantModel;
use Tests\Support\Modules\ModuleTestCase;

/**
 * A no-show is recorded only for a confirmed participant once the table has started.
 */
final class NoShowTest extends ModuleTestCase
{
    protected ?string $moduleName = 'game-tables';

    protected bool $autoEnableModule = true;

    protected function setUp(): void
    {
        parent::setUp();

        // Far from a clock change: start times are stored as wall-clock times, ambiguous in the hour
        // repeated in October (GuildForge#107), and these tests store times minutes or hours from now
        $this->travelTo(CarbonImmutable::parse('2026-11-04 18:00:00'));
    }

    public function test_a_confirmed_participant_is_marked_once_the_table_has_started(): void
    {
        $row = $this->confirmedAt($this->table(now()->subMinute()));

        $result = app(RegistrationServiceInterface::class)->markAsNoShow($row->id);

        $this->assertSame('no_show', $result->status->value);
        $this->assertSame('no_show', $row->refresh()->status->value);
    }

    public function test_nobody_is_marked_before_the_table_starts(): void
    {
        $row = $this->confirmedAt($this->table(now()->addHour()));

        try {
            app(RegistrationServiceInterface::class)->markAsNoShow($row->id);
            $this->fail('A participant was marked as no-show before the table started.');
        } catch (InvalidStatusTransitionException) {
            $this->assertSame('confirmed', $row->refresh()->status->value);
        }
    }

    private function confirmedAt(GameTableModel $table): ParticipantModel
    {
        return ParticipantModel::create([
            'id' => (string) Str::uuid(),
            'game_table_id' => $table->id,
            'user_id' => UserModel::factory()->create()->id,
            'role' => 'player',
            'status' => 'confirmed',
            'confirmed_at' => now(),
        ]);
    }

    private function table(\DateTimeInterface $startsAt): GameTableModel
    {
        $system = GameSystemModel::create(['id' => (string) Str::uuid(), 'name' => 'Sistema', 'slug' => 'sistema-'.Str::lower(Str::random(6)), 'is_active' => true]);

        return GameTableModel::create([
            'id' => (string) Str::uuid(),
            'game_system_id' => $system->id,
            'created_by' => UserModel::factory()->create()->id,
            'title' => 'Mesa',
            'slug' => 'mesa-'.Str::lower(Str::random(6)),
            'starts_at' => $startsAt,
            'duration_minutes' => 240,
            'table_type' => 'one_shot',
            'table_format' => 'in_person',
            'status' => 'scheduled',
            'min_players' => 1,
            'max_players' => 2,
            'language' => 'es',
            'is_published' => true,
        ]);
    }
}
