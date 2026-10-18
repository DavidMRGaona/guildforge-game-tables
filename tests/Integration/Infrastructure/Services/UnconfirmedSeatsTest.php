<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Integration\Infrastructure\Services;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Support\Str;
use Modules\GameTables\Domain\Enums\ParticipantRole;
use Modules\GameTables\Domain\Repositories\GameTableRepositoryInterface;
use Modules\GameTables\Domain\Repositories\ParticipantRepositoryInterface;
use Modules\GameTables\Domain\ValueObjects\GameTableId;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameSystemModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameTableModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\ParticipantModel;
use Modules\GameTables\Infrastructure\Services\SeatCounter;
use Tests\Support\Modules\ModuleTestCase;

/**
 * Pending rows hold seats (waiting list on, manual confirmation), except the guests whose email is
 * not confirmed and who were not given a seat (mode wait).
 */
final class UnconfirmedSeatsTest extends ModuleTestCase
{
    protected ?string $moduleName = 'game-tables';

    protected bool $autoEnableModule = true;

    private GameTableModel $table;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('modules.settings.game-tables.registration.waiting_list_enabled', true);
        $system = GameSystemModel::create(['id' => (string) Str::uuid(), 'name' => 'Sistema', 'slug' => 'sistema', 'is_active' => true]);
        $this->table = GameTableModel::create([
            'id' => (string) Str::uuid(),
            'game_system_id' => $system->id,
            'created_by' => UserModel::factory()->create()->id,
            'title' => 'Mesa',
            'slug' => 'mesa',
            'starts_at' => now()->addWeek(),
            'duration_minutes' => 240,
            'table_type' => 'one_shot',
            'table_format' => 'in_person',
            'status' => 'scheduled',
            'min_players' => 1,
            'max_players' => 5,
            'max_spectators' => 2,
            'language' => 'es',
            'is_published' => true,
            'auto_confirm' => false,
        ]);
    }

    public function test_an_unconfirmed_wait_row_takes_no_seat(): void
    {
        $this->guest('pending', 'hold', 'a@example.test');
        $before = $this->takenSeats();
        $this->assertSame(1, $before);

        $this->guest('pending', 'wait', 'b@example.test');
        $this->guest('pending', 'wait', 's@example.test', 'spectator');

        $this->assertSame($before, $this->takenSeats());
        $this->assertSame(0, app(SeatCounter::class)->takenSpectatorSeats($this->domainTable()));

        $listed = SeatCounter::withPlayerSeatCounts(GameTableModel::query())->findOrFail($this->table->id);
        $this->assertSame($before, app(SeatCounter::class)->takenPlayerSeatsOf($listed));

        $loaded = GameTableModel::query()->with('participants')->findOrFail($this->table->id);
        $this->assertSame($before, app(SeatCounter::class)->takenPlayerSeatsOf($loaded));
    }

    public function test_an_unconfirmed_hold_row_takes_a_seat(): void
    {
        $this->guest('pending', 'hold', 'a@example.test');

        $this->assertSame(1, $this->takenSeats());
        $listed = SeatCounter::withPlayerSeatCounts(GameTableModel::query())->findOrFail($this->table->id);
        $this->assertSame(1, app(SeatCounter::class)->takenPlayerSeatsOf($listed));
        $loaded = GameTableModel::query()->with('participants')->findOrFail($this->table->id);
        $this->assertSame(1, app(SeatCounter::class)->takenPlayerSeatsOf($loaded));
    }

    public function test_a_confirmed_wait_row_and_a_user_row_take_a_seat(): void
    {
        $this->guest('pending', 'wait', 'a@example.test', confirmedEmail: true);
        ParticipantModel::create([
            'id' => (string) Str::uuid(),
            'game_table_id' => $this->table->id,
            'user_id' => UserModel::factory()->create()->id,
            'role' => 'player',
            'status' => 'pending',
            'confirmation_mode' => 'wait',
        ]);

        $this->assertSame(2, $this->takenSeats());
    }

    public function test_a_row_with_null_mode_is_still_counted(): void
    {
        $this->guest('pending', null, 'a@example.test');

        $this->assertSame(1, $this->takenSeats());
        $listed = SeatCounter::withPlayerSeatCounts(GameTableModel::query())->findOrFail($this->table->id);
        $this->assertSame(1, app(SeatCounter::class)->takenPlayerSeatsOf($listed));
    }

    public function test_promotion_skips_an_unconfirmed_waiting_guest(): void
    {
        $this->guest('waiting_list', 'wait', 'first@example.test', position: 1);
        $second = $this->guest('waiting_list', 'wait', 'second@example.test', position: 2, confirmedEmail: true);

        $next = app(ParticipantRepositoryInterface::class)->getFirstInWaitingList(new GameTableId($this->table->id), ParticipantRole::Player);

        $this->assertNotNull($next);
        $this->assertSame($second->id, $next->id->value);
    }

    public function test_the_unconfirmed_waiting_guest_keeps_its_place(): void
    {
        $first = $this->guest('waiting_list', 'wait', 'first@example.test', position: 1);

        $this->assertSame(1, $first->refresh()->waiting_list_position);
        $this->assertNull(app(ParticipantRepositoryInterface::class)->getFirstInWaitingList(new GameTableId($this->table->id)));
        $this->assertSame(1, app(ParticipantRepositoryInterface::class)->countWaiting(new GameTableId($this->table->id), ParticipantRole::Player));
    }

    public function test_find_by_confirmation_token(): void
    {
        $guest = $this->guest('pending', 'hold', 'a@example.test', token: str_repeat('a', 64));
        $repository = app(ParticipantRepositoryInterface::class);

        $found = $repository->findByConfirmationToken(str_repeat('a', 64));

        $this->assertNotNull($found);
        $this->assertSame($guest->id, $found->id->value);
        $this->assertNull($repository->findByConfirmationToken(str_repeat('b', 64)));
    }

    private function takenSeats(): int
    {
        return app(SeatCounter::class)->takenPlayerSeats($this->domainTable());
    }

    private function domainTable(): \Modules\GameTables\Domain\Entities\GameTable
    {
        return app(GameTableRepositoryInterface::class)->findOrFail(new GameTableId($this->table->id));
    }

    private function guest(
        string $status,
        ?string $mode,
        string $email,
        string $role = 'player',
        ?int $position = null,
        bool $confirmedEmail = false,
        ?string $token = null,
    ): ParticipantModel {
        return ParticipantModel::create([
            'id' => (string) Str::uuid(),
            'game_table_id' => $this->table->id,
            'user_id' => null,
            'first_name' => 'Invitada',
            'email' => $email,
            'cancellation_token' => (string) Str::uuid(),
            'role' => $role,
            'status' => $status,
            'waiting_list_position' => $position,
            'email_confirmed_at' => $confirmedEmail ? now() : null,
            'confirmation_mode' => $mode,
            'confirmation_token' => $token,
        ]);
    }
}
