<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Integration\Infrastructure\Services;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Modules\GameTables\Application\Services\RegistrationServiceInterface;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameSystemModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameTableModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\ParticipantModel;
use Tests\Support\Modules\ModuleTestCase;

/**
 * A freed player seat promotes exactly one waiting player, with the real event listeners running.
 */
final class WaitingListPromotionTest extends ModuleTestCase
{
    protected ?string $moduleName = 'game-tables';

    protected bool $autoEnableModule = true;

    private GameTableModel $table;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        $system = GameSystemModel::create(['id' => (string) Str::uuid(), 'name' => 'Sistema', 'slug' => 'sistema', 'is_active' => true]);
        $this->table = GameTableModel::create([
            'id' => (string) Str::uuid(),
            'game_system_id' => $system->id,
            'created_by' => UserModel::factory()->create()->id,
            'title' => 'Mesa llena',
            'slug' => 'mesa-llena',
            'starts_at' => now()->addWeek(),
            'duration_minutes' => 240,
            'table_type' => 'one_shot',
            'table_format' => 'in_person',
            'status' => 'scheduled',
            'min_players' => 1,
            'max_players' => 2,
            'max_spectators' => 2,
            'language' => 'es',
            'is_published' => true,
        ]);
    }

    public function test_a_cancelled_player_seat_promotes_one_waiting_player(): void
    {
        $leaving = $this->participant('player', 'confirmed');
        $this->participant('player', 'confirmed');
        $first = $this->participant('player', 'waiting_list', 1);
        $second = $this->participant('player', 'waiting_list', 2);

        app(RegistrationServiceInterface::class)->cancel($leaving->id);

        $this->assertSame('confirmed', $first->refresh()->status->value);
        $this->assertSame('waiting_list', $second->refresh()->status->value);
        $this->assertSame(2, $this->confirmedPlayers());
    }

    public function test_a_waiting_spectator_does_not_take_a_freed_player_seat(): void
    {
        $leaving = $this->participant('player', 'confirmed');
        $this->participant('player', 'confirmed');
        $spectator = $this->participant('spectator', 'waiting_list', 1);
        $player = $this->participant('player', 'waiting_list', 2);

        app(RegistrationServiceInterface::class)->cancel($leaving->id);

        $this->assertSame('waiting_list', $spectator->refresh()->status->value);
        $this->assertSame('confirmed', $player->refresh()->status->value);
    }

    public function test_a_cancelled_spectator_promotes_no_waiting_player(): void
    {
        $spectator = $this->participant('spectator', 'confirmed');
        $waiting = $this->participant('player', 'waiting_list', 1);

        app(RegistrationServiceInterface::class)->cancel($spectator->id);

        $this->assertSame('waiting_list', $waiting->refresh()->status->value);
    }

    public function test_a_guest_cancelling_by_link_promotes_one_waiting_player(): void
    {
        $guest = $this->participant('player', 'confirmed', guestToken: 'token-de-prueba');
        $this->participant('player', 'confirmed');
        $first = $this->participant('player', 'waiting_list', 1);
        $second = $this->participant('player', 'waiting_list', 2);

        app(RegistrationServiceInterface::class)->cancelByToken('token-de-prueba');

        $this->assertSame('confirmed', $first->refresh()->status->value);
        $this->assertSame('waiting_list', $second->refresh()->status->value);
        $this->assertNotSame('waiting_list', $guest->refresh()->status->value);
    }

    private function confirmedPlayers(): int
    {
        return ParticipantModel::query()
            ->where('game_table_id', $this->table->id)
            ->where('role', 'player')
            ->where('status', 'confirmed')
            ->count();
    }

    private function participant(string $role, string $status, ?int $position = null, ?string $guestToken = null): ParticipantModel
    {
        return ParticipantModel::create([
            'id' => (string) Str::uuid(),
            'game_table_id' => $this->table->id,
            'user_id' => $guestToken === null ? UserModel::factory()->create()->id : null,
            'first_name' => $guestToken === null ? null : 'Invitada',
            'email' => $guestToken === null ? null : 'invitada@example.test',
            'cancellation_token' => $guestToken,
            'role' => $role,
            'status' => $status,
            'waiting_list_position' => $position,
            'confirmed_at' => $status === 'confirmed' ? now() : null,
        ]);
    }
}
