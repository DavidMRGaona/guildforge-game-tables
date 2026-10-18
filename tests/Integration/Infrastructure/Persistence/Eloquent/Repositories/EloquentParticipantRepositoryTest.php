<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Integration\Infrastructure\Persistence\Eloquent\Repositories;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use DateTimeImmutable;
use Illuminate\Support\Str;
use Modules\GameTables\Domain\Entities\Participant;
use Modules\GameTables\Domain\Enums\GuestConfirmationMode;
use Modules\GameTables\Domain\Enums\ParticipantRole;
use Modules\GameTables\Domain\Enums\ParticipantStatus;
use Modules\GameTables\Domain\Repositories\ParticipantRepositoryInterface;
use Modules\GameTables\Domain\ValueObjects\GameTableId;
use Modules\GameTables\Domain\ValueObjects\ParticipantId;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameSystemModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameTableModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\ParticipantModel;
use Tests\Support\Modules\ModuleTestCase;

final class EloquentParticipantRepositoryTest extends ModuleTestCase
{
    protected ?string $moduleName = 'game-tables';

    protected bool $autoEnableModule = true;

    private GameTableModel $table;

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
    }

    public function test_new_fields_survive_save_and_find(): void
    {
        $repository = app(ParticipantRepositoryInterface::class);
        $guest = new Participant(
            id: ParticipantId::generate(),
            gameTableId: new GameTableId($this->table->id),
            userId: null,
            role: ParticipantRole::Player,
            status: ParticipantStatus::Expired,
            firstName: 'Ana',
            email: 'ana@example.com',
            emailConfirmedAt: new DateTimeImmutable('2030-01-01 10:01:00'),
            confirmationExpiresAt: new DateTimeImmutable('2030-01-01 12:00:00'),
            confirmationToken: str_repeat('a', 64),
            confirmationSentAt: new DateTimeImmutable('2030-01-01 10:00:00'),
            confirmationMode: GuestConfirmationMode::Wait,
            fingerprint: str_repeat('b', 64),
        );

        $repository->save($guest);
        $found = $repository->findOrFail($guest->id);

        $this->assertSame(ParticipantStatus::Expired, $found->status);
        $this->assertEquals(new DateTimeImmutable('2030-01-01 10:01:00'), $found->emailConfirmedAt);
        $this->assertEquals(new DateTimeImmutable('2030-01-01 12:00:00'), $found->confirmationExpiresAt);
        $this->assertSame(str_repeat('a', 64), $found->confirmationToken);
        $this->assertEquals(new DateTimeImmutable('2030-01-01 10:00:00'), $found->confirmationSentAt);
        $this->assertSame(GuestConfirmationMode::Wait, $found->confirmationMode);
        $this->assertSame(str_repeat('b', 64), $found->fingerprint);
    }

    public function test_a_row_without_confirmation_data_keeps_the_fields_empty(): void
    {
        $repository = app(ParticipantRepositoryInterface::class);
        $guest = new Participant(
            id: ParticipantId::generate(),
            gameTableId: new GameTableId($this->table->id),
            userId: null,
            role: ParticipantRole::Player,
            status: ParticipantStatus::Pending,
            firstName: 'Ana',
            email: 'ana@example.com',
        );

        $repository->save($guest);
        $found = $repository->findOrFail($guest->id);

        $this->assertNull($found->emailConfirmedAt);
        $this->assertNull($found->confirmationToken);
        $this->assertNull($found->confirmationMode);
        $this->assertNull($found->fingerprint);
    }

    public function test_the_active_registrations_of_a_user_leave_out_expired_rows(): void
    {
        $user = UserModel::factory()->create();
        ParticipantModel::create([
            'id' => (string) Str::uuid(),
            'game_table_id' => $this->table->id,
            'user_id' => $user->id,
            'role' => 'player',
            'status' => 'expired',
        ]);

        $rows = app(ParticipantRepositoryInterface::class)->getActiveByUserWithTableData($user->id);

        $this->assertSame([], $rows);
    }
}
