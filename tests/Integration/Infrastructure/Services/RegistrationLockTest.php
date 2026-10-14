<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Integration\Infrastructure\Services;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Modules\GameTables\Application\DTOs\RegisterParticipantDTO;
use Modules\GameTables\Application\Services\RegistrationLockInterface;
use Modules\GameTables\Application\Services\RegistrationServiceInterface;
use Modules\GameTables\Domain\Enums\ParticipantRole;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameSystemModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameTableModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\ParticipantModel;
use Modules\GameTables\Infrastructure\Services\DatabaseRegistrationLock;
use Tests\Support\Modules\ModuleTestCase;

/**
 * Registrations, cancellations and promotions of a table run inside a transaction holding its lock.
 */
final class RegistrationLockTest extends ModuleTestCase
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
            'title' => 'Mesa',
            'slug' => 'mesa',
            'starts_at' => now()->addWeek(),
            'duration_minutes' => 240,
            'table_type' => 'one_shot',
            'table_format' => 'in_person',
            'status' => 'scheduled',
            'min_players' => 1,
            'max_players' => 2,
            'max_spectators' => 0,
            'language' => 'es',
            'is_published' => true,
        ]);
    }

    public function test_the_lock_runs_the_callback_in_a_transaction_and_returns_its_value(): void
    {
        $lock = new DatabaseRegistrationLock();
        $level = DB::transactionLevel();

        $result = $lock->forTable($this->table->id, fn (): int => DB::transactionLevel());

        $this->assertSame($level + 1, $result);
    }

    public function test_the_lock_of_a_participant_and_of_a_token_find_the_table(): void
    {
        $participant = ParticipantModel::create([
            'id' => (string) Str::uuid(),
            'game_table_id' => $this->table->id,
            'first_name' => 'Invitada',
            'email' => 'invitada@example.test',
            'cancellation_token' => 'token-de-prueba',
            'role' => 'player',
            'status' => 'confirmed',
        ]);
        $lock = new DatabaseRegistrationLock();

        $this->assertSame('ok', $lock->forParticipant($participant->id, fn (): string => 'ok'));
        $this->assertSame('ok', $lock->forCancellationToken('token-de-prueba', fn (): string => 'ok'));
    }

    // The callback reports the missing participant itself
    public function test_without_a_participant_the_callback_still_runs(): void
    {
        $lock = new DatabaseRegistrationLock();

        $this->assertSame('ok', $lock->forParticipant((string) Str::uuid(), fn (): string => 'ok'));
        $this->assertSame('ok', $lock->forCancellationToken('desconocido', fn (): string => 'ok'));
    }

    public function test_registrations_and_cancellations_go_through_the_table_lock(): void
    {
        $lock = $this->recordingLock();
        $user = UserModel::factory()->create();
        $service = app(RegistrationServiceInterface::class);

        $registration = $service->register(new RegisterParticipantDTO(
            gameTableId: $this->table->id,
            userId: $user->id,
            role: ParticipantRole::Player,
        ));
        $service->cancel($registration->id);
        $guest = $service->registerGuest(new RegisterParticipantDTO(
            gameTableId: $this->table->id,
            userId: null,
            role: ParticipantRole::Player,
            firstName: 'Invitada',
            email: 'invitada@example.test',
        ));
        $service->cancelByToken((string) ParticipantModel::query()->findOrFail($guest->id)->cancellation_token);

        $this->assertSame([
            'table:'.$this->table->id,
            'participant:'.$registration->id,
            'table:'.$this->table->id,
            'token',
        ], $lock->calls);
    }

    public function test_admin_additions_and_promotions_go_through_the_table_lock(): void
    {
        $lock = $this->recordingLock();
        $service = app(RegistrationServiceInterface::class);

        $byAdmin = $service->registerByAdmin(new RegisterParticipantDTO(
            gameTableId: $this->table->id,
            userId: UserModel::factory()->create()->id,
            role: ParticipantRole::Player,
        ));
        $service->registerGuestByAdmin(new RegisterParticipantDTO(
            gameTableId: $this->table->id,
            userId: null,
            role: ParticipantRole::Player,
            firstName: 'Invitada',
            email: 'invitada@example.test',
        ));
        $service->promoteFromWaitingList($this->table->id);
        $service->promoteParticipant($byAdmin->id);
        $service->confirm($byAdmin->id);

        $this->assertSame([
            'table:'.$this->table->id,
            'table:'.$this->table->id,
            'table:'.$this->table->id,
            'participant:'.$byAdmin->id,
            'participant:'.$byAdmin->id,
        ], $lock->calls);
    }

    public function test_an_admin_guest_email_is_stored_in_lowercase(): void
    {
        $guest = app(RegistrationServiceInterface::class)->registerGuestByAdmin(new RegisterParticipantDTO(
            gameTableId: $this->table->id,
            userId: null,
            role: ParticipantRole::Player,
            firstName: 'Invitada',
            email: 'Invitada@Example.TEST',
        ));

        $this->assertSame('invitada@example.test', ParticipantModel::query()->findOrFail($guest->id)->email);
    }

    /**
     * @return RegistrationLockInterface&object{calls: list<string>}
     */
    private function recordingLock(): RegistrationLockInterface
    {
        $lock = new class(new DatabaseRegistrationLock()) implements RegistrationLockInterface
        {
            /** @var list<string> */
            public array $calls = [];

            public function __construct(private readonly DatabaseRegistrationLock $inner) {}

            public function forTable(string $gameTableId, Closure $callback): mixed
            {
                $this->calls[] = 'table:'.$gameTableId;

                return $this->inner->forTable($gameTableId, $callback);
            }

            public function forParticipant(string $participantId, Closure $callback): mixed
            {
                $this->calls[] = 'participant:'.$participantId;

                return $this->inner->forParticipant($participantId, $callback);
            }

            public function forCancellationToken(string $token, Closure $callback): mixed
            {
                $this->calls[] = 'token';

                return $this->inner->forCancellationToken($token, $callback);
            }
        };

        $this->app->instance(RegistrationLockInterface::class, $lock);

        return $lock;
    }
}
