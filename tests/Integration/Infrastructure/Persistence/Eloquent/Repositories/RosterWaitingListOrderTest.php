<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Integration\Infrastructure\Persistence\Eloquent\Repositories;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Support\Str;
use Modules\GameTables\Domain\Enums\ParticipantRole;
use Modules\GameTables\Domain\Repositories\ParticipantRepositoryInterface;
use Modules\GameTables\Domain\ValueObjects\GameTableId;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameSystemModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameTableModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\ParticipantModel;
use Tests\Support\Modules\ModuleTestCase;

final class RosterWaitingListOrderTest extends ModuleTestCase
{
    protected ?string $moduleName = 'game-tables';

    protected bool $autoEnableModule = true;

    private GameTableModel $table;

    /** @var array<string, string> */
    private array $ids = [];

    protected function setUp(): void
    {
        parent::setUp();

        $creator = UserModel::factory()->create();
        $system = GameSystemModel::create([
            'id' => (string) Str::uuid(),
            'name' => 'Fixture System',
            'slug' => 'fixture-system',
            'is_active' => true,
        ]);
        $this->table = GameTableModel::create([
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
        ]);

        $this->ids['outsider_1'] = $this->waiting(1, false);
        $this->ids['outsider_2'] = $this->waiting(2, false);
        $this->ids['roster'] = $this->waiting(3, true);
    }

    public function test_the_roster_row_is_promoted_before_outsiders_ahead_of_it(): void
    {
        $first = app(ParticipantRepositoryInterface::class)
            ->getFirstInWaitingList(new GameTableId($this->table->id), ParticipantRole::Player);

        $this->assertNotNull($first);
        $this->assertSame($this->ids['roster'], $first->id->value);
        $this->assertTrue($first->fromRoster);
    }

    public function test_the_roster_row_is_shown_first_in_the_ranks(): void
    {
        $ranks = app(ParticipantRepositoryInterface::class)
            ->getWaitingListRanks([new GameTableId($this->table->id)]);

        $this->assertSame(1, $ranks[$this->ids['roster']]);
        $this->assertSame(2, $ranks[$this->ids['outsider_1']]);
        $this->assertSame(3, $ranks[$this->ids['outsider_2']]);
    }

    private function waiting(int $position, bool $fromRoster): string
    {
        $user = UserModel::factory()->create();

        return ParticipantModel::create([
            'id' => (string) Str::uuid(),
            'game_table_id' => $this->table->id,
            'user_id' => $user->id,
            'role' => 'player',
            'status' => 'waiting_list',
            'waiting_list_position' => $position,
            'from_roster' => $fromRoster,
        ])->id;
    }
}
