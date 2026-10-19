<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Integration\Infrastructure\Persistence\Eloquent\Repositories;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Str;
use Modules\GameTables\Domain\Entities\Participant;
use Modules\GameTables\Domain\Enums\ParticipantRole;
use Modules\GameTables\Domain\Enums\ParticipantStatus;
use Modules\GameTables\Domain\Exceptions\AlreadyRegisteredException;
use Modules\GameTables\Domain\Repositories\ParticipantRepositoryInterface;
use Modules\GameTables\Domain\ValueObjects\GameTableId;
use Modules\GameTables\Domain\ValueObjects\ParticipantId;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameSystemModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameTableModel;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\Modules\ModuleTestCase;

final class ActiveGuestEmailIndexTest extends ModuleTestCase
{
    protected ?string $moduleName = 'game-tables';

    protected bool $autoEnableModule = true;

    private GameTableModel $table;

    private GameSystemModel $system;

    private UserModel $creator;

    private ParticipantRepositoryInterface $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->creator = UserModel::factory()->create();
        $this->system = GameSystemModel::create([
            'id' => (string) Str::uuid(),
            'name' => 'Fixture System',
            'slug' => 'fixture-system',
            'is_active' => true,
        ]);
        $this->table = $this->createTable();
        $this->repository = app(ParticipantRepositoryInterface::class);
    }

    public function test_two_active_guests_with_the_same_email_in_another_case_are_refused(): void
    {
        $this->repository->save($this->guest('ana@example.com'));

        $this->expectException(AlreadyRegisteredException::class);

        $this->repository->save($this->guest('Ana@Example.com'));
    }

    /**
     * @return array<string, array{ParticipantStatus}>
     */
    public static function inactiveStatuses(): array
    {
        return [
            'cancelled' => [ParticipantStatus::Cancelled],
            'expired' => [ParticipantStatus::Expired],
            'rejected' => [ParticipantStatus::Rejected],
        ];
    }

    #[DataProvider('inactiveStatuses')]
    public function test_a_cancelled_or_expired_guest_does_not_block_a_new_one(ParticipantStatus $status): void
    {
        $this->repository->save($this->guest('ana@example.com', $status));
        $this->repository->save($this->guest('ana@example.com'));

        // And the other way round: an active guest does not block the old row being written
        $this->repository->save($this->guest('ana@example.com', $status));

        $this->assertCount(3, $this->repository->getByGameTable(new GameTableId($this->table->id)));
    }

    public function test_user_rows_are_not_part_of_the_index(): void
    {
        $first = UserModel::factory()->create();
        $second = UserModel::factory()->create();

        $this->repository->save($this->user($first->id));
        $this->repository->save($this->user($second->id));
        $this->repository->save($this->guest('ana@example.com'));

        $this->assertCount(3, $this->repository->getByGameTable(new GameTableId($this->table->id)));
    }

    public function test_the_same_email_at_two_tables_is_allowed(): void
    {
        $other = $this->createTable();

        $this->repository->save($this->guest('ana@example.com'));
        $this->repository->save($this->guest('ana@example.com', tableId: $other->id));

        $this->assertCount(1, $this->repository->getByGameTable(new GameTableId($other->id)));
    }

    public function test_another_unique_violation_is_rethrown(): void
    {
        $first = $this->guest('ana@example.com');
        $first->setCancellationToken('same-token');
        $second = $this->guest('luis@example.com');
        $second->setCancellationToken('same-token');
        $this->repository->save($first);

        $this->expectException(UniqueConstraintViolationException::class);

        $this->repository->save($second);
    }

    private function guest(string $email, ParticipantStatus $status = ParticipantStatus::Pending, ?string $tableId = null): Participant
    {
        return new Participant(
            id: ParticipantId::generate(),
            gameTableId: new GameTableId($tableId ?? $this->table->id),
            userId: null,
            role: ParticipantRole::Player,
            status: $status,
            firstName: 'Ana',
            email: $email,
        );
    }

    private function user(string $userId): Participant
    {
        return new Participant(
            id: ParticipantId::generate(),
            gameTableId: new GameTableId($this->table->id),
            userId: $userId,
            role: ParticipantRole::Player,
            status: ParticipantStatus::Pending,
        );
    }

    private function createTable(): GameTableModel
    {
        return GameTableModel::create([
            'id' => (string) Str::uuid(),
            'game_system_id' => $this->system->id,
            'created_by' => $this->creator->id,
            'title' => 'La cripta olvidada',
            'slug' => 'la-cripta-olvidada-'.Str::lower(Str::random(8)),
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
}
