<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Integration\Persistence;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Modules\GameTables\Application\DTOs\RegisterParticipantDTO;
use Modules\GameTables\Application\Services\ParticipantActorContextInterface;
use Modules\GameTables\Application\Services\RegistrationServiceInterface;
use Modules\GameTables\Domain\Enums\ParticipantRole;
use Modules\GameTables\Domain\Enums\StatusLogActorKind;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameSystemModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameTableModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\ParticipantModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\ParticipantStatusLogModel;
use Modules\GameTables\Infrastructure\Services\GuestRegistrationExpirer;
use RuntimeException;
use Tests\Support\Modules\ModuleTestCase;

/**
 * Every change to a registration's status leaves one row: who made it (an account, the participant
 * themselves or the system) and why, whatever path wrote it.
 */
final class ParticipantStatusLogTest extends ModuleTestCase
{
    protected ?string $moduleName = 'game-tables';

    protected bool $autoEnableModule = true;

    private GameTableModel $table;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        config()->set('modules.settings.game-tables', []);
        $this->travelTo(CarbonImmutable::parse('2026-11-02 10:00:00'));
        $system = GameSystemModel::create(['id' => (string) Str::uuid(), 'name' => 'Sistema', 'slug' => 'sistema', 'is_active' => true]);
        $this->table = GameTableModel::create([
            'id' => (string) Str::uuid(),
            'game_system_id' => $system->id,
            'created_by' => UserModel::factory()->create()->id,
            'title' => 'Mesa de prueba',
            'slug' => 'mesa-de-prueba',
            'starts_at' => now()->addWeek(),
            'duration_minutes' => 240,
            'table_type' => 'one_shot',
            'table_format' => 'in_person',
            'status' => 'scheduled',
            'min_players' => 1,
            'max_players' => 1,
            'max_spectators' => 0,
            'language' => 'es',
            'registration_type' => 'everyone',
            'auto_confirm' => true,
            'is_published' => true,
        ]);
    }

    public function test_a_user_registering_on_an_auto_confirm_table_leaves_one_row_by_the_participant(): void
    {
        $user = UserModel::factory()->create(['name' => 'John Doe']);
        $this->actingAs($user);

        $participant = $this->service()->register(new RegisterParticipantDTO(
            gameTableId: $this->table->id,
            userId: $user->id,
            role: ParticipantRole::Player,
        ));

        $rows = $this->rows();
        $this->assertCount(1, $rows);
        $this->assertSame($this->table->id, $rows[0]->game_table_id);
        $this->assertSame($participant->id, $rows[0]->participant_id);
        $this->assertSame($user->id, $rows[0]->participant_user_id);
        $this->assertNull($rows[0]->guest_name);
        $this->assertNull($rows[0]->from_status);
        $this->assertSame('confirmed', $rows[0]->to_status);
        $this->assertSame($user->id, $rows[0]->actor_user_id);
        $this->assertSame(StatusLogActorKind::Participant, $rows[0]->actor_kind);
        $this->assertNull($rows[0]->reason);
    }

    public function test_a_guest_row_keeps_the_guest_name_and_no_account(): void
    {
        $this->service()->registerGuest(new RegisterParticipantDTO(
            gameTableId: $this->table->id,
            userId: null,
            role: ParticipantRole::Player,
            firstName: 'Jane',
            email: 'jane@example.test',
            lastName: 'Roe',
        ));

        $rows = $this->rows();
        $this->assertCount(1, $rows);
        $this->assertNull($rows[0]->participant_user_id);
        $this->assertSame('Jane Roe', $rows[0]->guest_name);
        // Nobody signed in, but the guest's own registration is theirs, never the system's
        $this->assertSame(StatusLogActorKind::Participant, $rows[0]->actor_kind);
        $this->assertNull($rows[0]->actor_user_id);
    }

    public function test_a_cancellation_that_promotes_the_first_waiting_player_leaves_two_rows(): void
    {
        $leaving = UserModel::factory()->create();
        $seated = $this->participant($leaving, 'confirmed');
        $waiting = $this->participant(UserModel::factory()->create(), 'waiting_list', ['waiting_list_position' => 1]);
        ParticipantStatusLogModel::query()->delete();

        $this->actingAs($leaving);
        $this->service()->cancel($seated->id);

        $rows = $this->rows();
        $this->assertCount(2, $rows);
        $cancellation = $rows->firstWhere('participant_id', $seated->id);
        $promotion = $rows->firstWhere('participant_id', $waiting->id);
        $this->assertNotNull($cancellation);
        $this->assertNotNull($promotion);
        $this->assertSame(['confirmed', 'cancelled'], [$cancellation->from_status, $cancellation->to_status]);
        $this->assertSame($leaving->id, $cancellation->actor_user_id);
        $this->assertSame(StatusLogActorKind::Participant, $cancellation->actor_kind);
        $this->assertSame(['waiting_list', 'confirmed'], [$promotion->from_status, $promotion->to_status]);
        $this->assertSame(StatusLogActorKind::System, $promotion->actor_kind);
        $this->assertNull($promotion->actor_user_id);
    }

    public function test_a_guest_expired_by_the_scheduler_is_logged_as_the_system(): void
    {
        $guest = $this->service()->registerGuest(new RegisterParticipantDTO(
            gameTableId: $this->table->id,
            userId: null,
            role: ParticipantRole::Player,
            firstName: 'Jane',
            email: 'jane@example.test',
        ));

        $this->travelTo(CarbonImmutable::parse('2026-11-02 12:00:00'));
        app(GuestRegistrationExpirer::class)->run(CarbonImmutable::now()->toDateTimeImmutable());

        $expiry = $this->rows()->firstWhere('to_status', 'expired');
        $this->assertNotNull($expiry);
        $this->assertSame($guest->id, $expiry->participant_id);
        $this->assertSame(StatusLogActorKind::System, $expiry->actor_kind);
        $this->assertNull($expiry->actor_user_id);
    }

    public function test_a_guest_cancelling_with_their_link_is_logged_as_the_participant(): void
    {
        $guest = $this->service()->registerGuest(new RegisterParticipantDTO(
            gameTableId: $this->table->id,
            userId: null,
            role: ParticipantRole::Player,
            firstName: 'Jane',
            email: 'jane@example.test',
        ));
        $token = (string) ParticipantModel::query()->findOrFail($guest->id)->cancellation_token;

        $this->service()->cancelByToken($token);

        $cancellation = $this->rows()->firstWhere('to_status', 'cancelled');
        $this->assertNotNull($cancellation);
        $this->assertSame(StatusLogActorKind::Participant, $cancellation->actor_kind);
        $this->assertNull($cancellation->actor_user_id);
    }

    public function test_a_guest_confirming_their_own_email_is_logged_as_the_participant(): void
    {
        // Wait mode: the guest holds nothing until the link places them
        config()->set('modules.settings.game-tables.guest_confirmation.mode', 'wait');
        $guest = $this->service()->registerGuest(new RegisterParticipantDTO(
            gameTableId: $this->table->id,
            userId: null,
            role: ParticipantRole::Player,
            firstName: 'Jane',
            email: 'jane@example.test',
        ));
        $before = $this->rows()->count();

        $this->service()->confirmGuestEmail((string) ParticipantModel::query()->findOrFail($guest->id)->confirmation_token);

        $rows = $this->rows();
        $this->assertGreaterThan($before, $rows->count());
        $placed = $rows->last();
        $this->assertNotNull($placed);
        $this->assertSame($guest->id, $placed->participant_id);
        $this->assertSame(StatusLogActorKind::Participant, $placed->actor_kind);
        $this->assertNull($placed->actor_user_id);
    }

    public function test_a_user_cancelling_their_own_registration_is_the_participant_and_the_promotion_the_system(): void
    {
        $leaving = UserModel::factory()->create();
        $seated = $this->participant($leaving, 'confirmed');
        $waiting = $this->participant(UserModel::factory()->create(), 'waiting_list', ['waiting_list_position' => 1]);
        ParticipantStatusLogModel::query()->delete();

        $this->actingAs($leaving);
        $this->service()->cancelByUser($this->table->id, $leaving->id);

        $cancellation = $this->rows()->firstWhere('participant_id', $seated->id);
        $promotion = $this->rows()->firstWhere('participant_id', $waiting->id);
        $this->assertNotNull($cancellation);
        $this->assertNotNull($promotion);
        $this->assertSame(StatusLogActorKind::Participant, $cancellation->actor_kind);
        $this->assertSame($leaving->id, $cancellation->actor_user_id);
        $this->assertSame(StatusLogActorKind::System, $promotion->actor_kind);
        $this->assertNull($promotion->actor_user_id);
    }

    // A lapse found while the guest submits again is the clock's doing, not the guest's
    public function test_an_overdue_row_lapsing_during_a_guest_submission_is_the_system(): void
    {
        $this->table->update(['max_players' => 5]);
        $guest = $this->service()->registerGuest(new RegisterParticipantDTO(
            gameTableId: $this->table->id,
            userId: null,
            role: ParticipantRole::Player,
            firstName: 'Jane',
            email: 'jane@example.test',
        ));

        $this->travelTo(CarbonImmutable::parse('2026-11-02 12:00:00'));
        $this->service()->registerGuest(new RegisterParticipantDTO(
            gameTableId: $this->table->id,
            userId: null,
            role: ParticipantRole::Player,
            firstName: 'Jane',
            email: 'jane@example.test',
        ));

        $expiry = $this->rows()->first(fn (ParticipantStatusLogModel $row): bool => $row->participant_id === $guest->id && $row->to_status === 'expired');
        $this->assertNotNull($expiry);
        $this->assertSame(StatusLogActorKind::System, $expiry->actor_kind);
    }

    // An expiry is the clock's doing, even inside a scope that names someone
    public function test_an_expiry_inside_a_named_scope_is_still_the_system(): void
    {
        $guest = $this->service()->registerGuest(new RegisterParticipantDTO(
            gameTableId: $this->table->id,
            userId: null,
            role: ParticipantRole::Player,
            firstName: 'Jane',
            email: 'jane@example.test',
        ));
        $admin = UserModel::factory()->create();
        $this->actingAs($admin);

        $this->travelTo(CarbonImmutable::parse('2026-11-02 12:00:00'));
        $this->context()->run($admin->id, 'Motivo', fn (): bool => $this->service()->expireGuestRegistration($guest->id));

        $expiry = $this->rows()->firstWhere('to_status', 'expired');
        $this->assertNotNull($expiry);
        $this->assertSame(StatusLogActorKind::System, $expiry->actor_kind);
        $this->assertNull($expiry->reason);
    }

    // The guest's name must not outlive the account's anonymization in the history
    public function test_a_guest_row_taken_over_by_an_account_hands_its_history_to_the_account(): void
    {
        $this->table->update(['max_players' => 5]);
        $guest = $this->service()->registerGuestByAdmin(new RegisterParticipantDTO(
            gameTableId: $this->table->id,
            userId: null,
            role: ParticipantRole::Player,
            firstName: 'Jane',
            lastName: 'Roe',
            email: 'john@example.test',
        ));
        $this->assertSame('Jane Roe', $this->rows()->sole()->guest_name);
        $john = UserModel::factory()->create(['name' => 'John Doe', 'email' => 'john@example.test']);

        $taken = $this->service()->register(new RegisterParticipantDTO(
            gameTableId: $this->table->id,
            userId: $john->id,
            role: ParticipantRole::Player,
        ));

        $this->assertSame($guest->id, $taken->id);
        $rows = ParticipantStatusLogModel::query()->where('participant_id', $guest->id)->get();
        $this->assertNotEmpty($rows);
        foreach ($rows as $row) {
            $this->assertSame($john->id, $row->participant_user_id);
            $this->assertNull($row->guest_name);
        }
    }

    // The panel's cancellation never carries a reason, not even an outer scope's
    public function test_a_panel_cancellation_logs_the_admin_without_a_reason(): void
    {
        $row = $this->participant(UserModel::factory()->create(), 'confirmed');
        $admin = UserModel::factory()->create();
        $this->actingAs($admin);

        $this->context()->run($admin->id, 'Motivo de otra cosa', fn () => $this->service()->cancelByAdmin($row->id));

        $cancellation = $this->rows()->firstWhere('to_status', 'cancelled');
        $this->assertNotNull($cancellation);
        $this->assertSame($admin->id, $cancellation->actor_user_id);
        $this->assertSame(StatusLogActorKind::Account, $cancellation->actor_kind);
        $this->assertNull($cancellation->reason);
    }

    public function test_a_query_delete_of_the_participant_keeps_its_rows(): void
    {
        $row = $this->participant(UserModel::factory()->create(), 'cancelled');

        ParticipantModel::query()->whereKey($row->id)->delete();

        $rows = $this->rows();
        $this->assertCount(1, $rows);
        $this->assertNull($rows[0]->participant_id);
        $this->assertSame('cancelled', $rows[0]->to_status);
    }

    public function test_an_update_that_does_not_touch_the_status_writes_nothing(): void
    {
        $row = $this->participant(UserModel::factory()->create(), 'confirmed');

        $row->update(['notes' => 'Trae dados']);

        $this->assertCount(1, $this->rows());
    }

    public function test_a_failing_log_never_breaks_the_registration(): void
    {
        Exceptions::fake();
        Schema::drop('gametables_participant_status_log');
        $user = UserModel::factory()->create();

        $participant = $this->service()->register(new RegisterParticipantDTO(
            gameTableId: $this->table->id,
            userId: $user->id,
            role: ParticipantRole::Player,
        ));

        $this->assertSame('confirmed', ParticipantModel::query()->findOrFail($participant->id)->status->value);
        Exceptions::assertReported(fn (QueryException $e): bool => str_contains($e->getMessage(), 'gametables_participant_status_log'));
    }

    public function test_without_a_scope_the_actor_is_the_signed_in_user(): void
    {
        $context = $this->context();
        $this->assertNull($context->actorUserId());
        $this->assertSame(StatusLogActorKind::System, $context->actorKind());

        $user = UserModel::factory()->create();
        $this->actingAs($user);

        $this->assertSame($user->id, $context->actorUserId());
        $this->assertSame(StatusLogActorKind::Account, $context->actorKind());
        $this->assertNull($context->reason());
    }

    public function test_nested_scopes_restore_the_outer_one_even_when_they_throw(): void
    {
        $context = $this->context();
        $director = UserModel::factory()->create();

        $context->run($director->id, 'Mesa completa', function () use ($context, $director): void {
            $context->system(function () use ($context): void {
                $this->assertSame(StatusLogActorKind::System, $context->actorKind());
                $this->assertNull($context->reason());
            });

            $context->asParticipant(null, function () use ($context): void {
                $this->assertSame(StatusLogActorKind::Participant, $context->actorKind());
                $this->assertNull($context->actorUserId());
                $this->assertNull($context->reason());
            });

            try {
                $context->run('other', 'Otro', static fn () => throw new RuntimeException('boom'));
            } catch (RuntimeException) {
            }

            $this->assertSame($director->id, $context->actorUserId());
            $this->assertSame(StatusLogActorKind::Account, $context->actorKind());
            $this->assertSame('Mesa completa', $context->reason());
        });

        $this->assertNull($context->actorUserId());
        $this->assertNull($context->reason());
    }

    // One per request or job: a scope left behind never reaches the next one
    public function test_the_context_is_scoped(): void
    {
        $this->assertSame($this->context(), $this->context());

        $first = $this->context();
        $this->app->forgetScopedInstances();

        $this->assertNotSame($first, $this->context());
    }

    /**
     * @return Collection<int, ParticipantStatusLogModel>
     */
    private function rows(): Collection
    {
        return ParticipantStatusLogModel::query()->orderBy('created_at')->get()->values();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function participant(UserModel $user, string $status, array $attributes = []): ParticipantModel
    {
        return ParticipantModel::create([
            'id' => (string) Str::uuid(),
            'game_table_id' => $this->table->id,
            'user_id' => $user->id,
            'role' => 'player',
            'status' => $status,
            'confirmed_at' => $status === 'confirmed' ? now() : null,
            ...$attributes,
        ]);
    }

    private function service(): RegistrationServiceInterface
    {
        return app(RegistrationServiceInterface::class);
    }

    private function context(): ParticipantActorContextInterface
    {
        return app(ParticipantActorContextInterface::class);
    }
}
