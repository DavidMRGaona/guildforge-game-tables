<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Integration\Infrastructure\Services;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Modules\GameTables\Domain\Entities\GameTable;
use Modules\GameTables\Domain\Enums\GuestConfirmationMode;
use Modules\GameTables\Domain\Repositories\GameTableRepositoryInterface;
use Modules\GameTables\Domain\Repositories\ParticipantRepositoryInterface;
use Modules\GameTables\Domain\ValueObjects\GameTableId;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameSystemModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameTableModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\ParticipantModel;
use Modules\GameTables\Infrastructure\Services\GuestConfirmationPolicy;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\Modules\ModuleTestCase;

final class GuestConfirmationPolicyTest extends ModuleTestCase
{
    protected ?string $moduleName = 'game-tables';

    protected bool $autoEnableModule = true;

    private GameTableModel $model;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('modules.settings.game-tables', []);
        $this->travelTo(CarbonImmutable::parse('2026-11-01 10:00:00'));
        $this->model = $this->createTable(CarbonImmutable::now()->addWeek());
    }

    public function test_the_deadline_is_two_hours_from_now(): void
    {
        $now = CarbonImmutable::now();

        $deadline = $this->policy()->deadlineFor($this->table($this->model), $now);

        $this->assertSame('2026-11-01 12:00:00', $deadline->format('Y-m-d H:i:s'));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function clockChangeNights(): array
    {
        return [
            // 01:00 summer time; the clocks go back at 03:00, so two hours later is 02:00 winter time
            'october' => ['2026-10-24 23:00:00', '2026-10-25T02:00:00+01:00'],
            // 01:30 winter time; the clocks go forward at 02:00, so two hours later is 04:30 summer time
            'march' => ['2026-03-29 00:30:00', '2026-03-29T04:30:00+02:00'],
        ];
    }

    // The seat is held for the configured hours of real time, whatever the clocks do that night
    #[DataProvider('clockChangeNights')]
    public function test_the_deadline_lasts_the_hold_hours_across_a_clock_change(string $utcNow, string $expected): void
    {
        $now = CarbonImmutable::parse($utcNow, 'UTC')->setTimezone('Europe/Madrid');

        $deadline = $this->policy()->deadlineFor($this->table($this->model), $now);

        $this->assertSame($expected, $deadline->format('c'));
        $this->assertSame(2 * 3600, $deadline->getTimestamp() - $now->getTimestamp());
    }

    public function test_the_deadline_never_passes_the_closing(): void
    {
        $model = $this->createTable(CarbonImmutable::now()->addMinutes(30));
        $now = CarbonImmutable::now();

        $deadline = $this->policy()->deadlineFor($this->table($model), $now);

        $this->assertSame('2026-11-01 10:30:00', $deadline->format('Y-m-d H:i:s'));
    }

    public function test_the_hold_hours_setting_moves_the_deadline(): void
    {
        config()->set('modules.settings.game-tables.guest_confirmation.hold_hours', 5);

        $deadline = $this->policy()->deadlineFor($this->table($this->model), CarbonImmutable::now());

        $this->assertSame('2026-11-01 15:00:00', $deadline->format('Y-m-d H:i:s'));
    }

    public function test_the_third_unconfirmed_guest_waits(): void
    {
        $this->assertSame(GuestConfirmationMode::Hold, $this->policy()->modeFor($this->table($this->model)));

        $this->guest('pending', 'hold');
        $this->guest('confirmed', 'hold');
        $this->assertSame(GuestConfirmationMode::Wait, $this->policy()->modeFor($this->table($this->model)));
    }

    public function test_rows_that_hold_nothing_do_not_count_toward_the_cap(): void
    {
        $this->guest('pending', 'hold');
        // Waiting list, a confirmed email, a wait row, a cancelled row and a signed-in user hold no unconfirmed seat
        $this->guest('waiting_list', 'hold');
        $this->guest('confirmed', 'hold', confirmed: true);
        $this->guest('confirmed', 'wait');
        $this->guest('cancelled', 'hold');
        ParticipantModel::create([
            'id' => (string) Str::uuid(),
            'game_table_id' => $this->model->id,
            'user_id' => UserModel::factory()->create()->id,
            'role' => 'player',
            'status' => 'confirmed',
        ]);

        $this->assertSame(1, app(ParticipantRepositoryInterface::class)->countUnconfirmedHoldingSeats(new GameTableId($this->model->id)));
        $this->assertSame(GuestConfirmationMode::Hold, $this->policy()->modeFor($this->table($this->model)));
    }

    public function test_the_cap_counts_per_table(): void
    {
        $other = $this->createTable(CarbonImmutable::now()->addWeek(), 'otra');
        $this->guest('pending', 'hold', table: $other);
        $this->guest('pending', 'hold', table: $other);

        $this->assertSame(GuestConfirmationMode::Hold, $this->policy()->modeFor($this->table($this->model)));
    }

    public function test_cap_zero_always_waits(): void
    {
        config()->set('modules.settings.game-tables.guest_confirmation.max_unconfirmed_held', 0);

        $this->assertSame(GuestConfirmationMode::Wait, $this->policy()->modeFor($this->table($this->model)));
    }

    public function test_wait_setting_ignores_the_cap(): void
    {
        config()->set('modules.settings.game-tables.guest_confirmation.mode', 'wait');
        config()->set('modules.settings.game-tables.guest_confirmation.max_unconfirmed_held', 50);

        $this->assertSame(GuestConfirmationMode::Wait, $this->policy()->modeFor($this->table($this->model)));
    }

    public function test_notice_carries_the_deadline_in_hold_mode(): void
    {
        $notice = $this->policy()->noticeFor($this->table($this->model), CarbonImmutable::now());

        $this->assertSame('hold', $notice['mode']);
        $this->assertNotNull($notice['deadline']);
        $this->assertSame('2026-11-01 12:00:00', CarbonImmutable::parse($notice['deadline'])->format('Y-m-d H:i:s'));
    }

    public function test_notice_has_no_deadline_in_wait_mode(): void
    {
        config()->set('modules.settings.game-tables.guest_confirmation.mode', 'wait');

        $this->assertSame(
            ['mode' => 'wait', 'deadline' => null],
            $this->policy()->noticeFor($this->table($this->model), CarbonImmutable::now()),
        );
    }

    private function policy(): GuestConfirmationPolicy
    {
        return new GuestConfirmationPolicy(app(ParticipantRepositoryInterface::class));
    }

    private function table(GameTableModel $model): GameTable
    {
        return app(GameTableRepositoryInterface::class)->findOrFail(new GameTableId($model->id));
    }

    private function createTable(CarbonImmutable $startsAt, string $slug = 'mesa'): GameTableModel
    {
        $system = GameSystemModel::query()->first()
            ?? GameSystemModel::create(['id' => (string) Str::uuid(), 'name' => 'Sistema', 'slug' => 'sistema', 'is_active' => true]);

        return GameTableModel::create([
            'id' => (string) Str::uuid(),
            'game_system_id' => $system->id,
            'created_by' => UserModel::factory()->create()->id,
            'title' => 'Mesa '.$slug,
            'slug' => $slug.'-'.Str::random(5),
            'starts_at' => $startsAt,
            'duration_minutes' => 240,
            'table_type' => 'one_shot',
            'table_format' => 'in_person',
            'status' => 'scheduled',
            'min_players' => 1,
            'max_players' => 5,
            'max_spectators' => 0,
            'language' => 'es',
            'is_published' => true,
        ]);
    }

    private function guest(string $status, string $mode, bool $confirmed = false, ?GameTableModel $table = null): ParticipantModel
    {
        return ParticipantModel::create([
            'id' => (string) Str::uuid(),
            'game_table_id' => ($table ?? $this->model)->id,
            'user_id' => null,
            'first_name' => 'Invitada',
            'email' => Str::random(8).'@example.test',
            'cancellation_token' => Str::random(32),
            'role' => 'player',
            'status' => $status,
            'confirmation_mode' => $mode,
            'email_confirmed_at' => $confirmed ? now() : null,
            'waiting_list_position' => $status === 'waiting_list' ? 1 : null,
        ]);
    }
}
