<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Feature\Http\Controllers;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\GameTables\Application\DTOs\RegisterParticipantDTO;
use Modules\GameTables\Application\Services\RegistrationServiceInterface;
use Modules\GameTables\Domain\Enums\ParticipantRole;
use Modules\GameTables\Domain\Events\GuestRegistered;
use Modules\GameTables\Domain\Events\ParticipantRegistered;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameSystemModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameTableModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\ParticipantModel;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\Modules\ModuleTestCase;

/**
 * The place shown to someone on the waiting list is their rank among those still waiting for the
 * same role (by stored position, then by date), not the stored position: leaving the list leaves
 * gaps in the stored positions, which are never rewritten. Promotions keep their order.
 */
final class WaitingListRankTest extends ModuleTestCase
{
    protected ?string $moduleName = 'game-tables';

    protected bool $autoEnableModule = true;

    private GameSystemModel $gameSystem;

    protected function setUp(): void
    {
        parent::setUp();

        app('router')->getRoutes()->refreshNameLookups();
        config()->set('modules.settings.game-tables', []);

        $this->gameSystem = GameSystemModel::create([
            'id' => (string) Str::uuid(),
            'name' => 'Sistema de prueba',
            'slug' => 'sistema-de-prueba',
            'is_active' => true,
        ]);
    }

    /**
     * @return array<string, array{bool}>
     */
    public static function waitingListSetting(): array
    {
        return [
            'waiting list off' => [false],
            'waiting list on' => [true],
        ];
    }

    // Characterization: the stored positions and the promotion order do not change

    #[DataProvider('waitingListSetting')]
    public function test_leaving_the_waiting_list_does_not_rewrite_the_other_positions(bool $waitingList): void
    {
        $this->setWaitingList($waitingList);
        $table = $this->fullTable();
        [$first, $second, $third] = $this->queueByAdmin($table, 3);

        $this->service()->cancel($first->id);

        $this->assertSame(2, $second->refresh()->waiting_list_position);
        $this->assertSame(3, $third->refresh()->waiting_list_position);
    }

    public function test_freed_seats_go_to_the_waiting_list_in_order_across_a_gap(): void
    {
        $this->setWaitingList(true);
        $table = $this->table(['max_players' => 2]);
        [$seatedA, $seatedB] = $this->rows($table, 'player', 'confirmed', 2);
        [$first, $second, $third] = $this->queueByAdmin($table, 3);

        $this->service()->cancel($second->id);
        $this->service()->cancel($seatedA->id);

        $this->assertSame('confirmed', $first->refresh()->status->value);
        $this->assertSame('waiting_list', $third->refresh()->status->value);

        $this->service()->cancel($seatedB->id);

        $this->assertSame('confirmed', $third->refresh()->status->value);
    }

    // The place shown is the rank among those waiting

    #[DataProvider('waitingListSetting')]
    public function test_the_table_page_shows_the_place_among_those_still_waiting(bool $waitingList): void
    {
        $this->setWaitingList($waitingList);
        $table = $this->fullTable();
        [$first, , $third] = $this->queueByAdmin($table, 3);

        $this->service()->cancel($first->id);

        $this->actingAs($this->userOf($third))->get(route('gametables.show', $table->slug))
            ->assertInertia(fn (Assert $page) => $page
                ->where('userRegistration.status', 'waiting_list')
                ->where('userRegistration.waitingListPosition', 2));
    }

    #[DataProvider('waitingListSetting')]
    public function test_the_profile_shows_the_place_among_those_still_waiting(bool $waitingList): void
    {
        $this->setWaitingList($waitingList);
        $table = $this->fullTable();
        [$first, $second] = $this->queueByAdmin($table, 2);

        $this->service()->cancel($first->id);

        $participations = $this->service()->getByUserForProfile((string) $second->user_id);

        $this->assertCount(1, $participations);
        $this->assertSame(1, $participations[0]->waitingListPosition);
    }

    #[DataProvider('waitingListSetting')]
    public function test_the_profile_gives_each_place_on_several_lists_with_one_query_for_all(bool $waitingList): void
    {
        $this->setWaitingList($waitingList);
        [$oneList, $oneQueries] = $this->profileWaitingAt([2]);
        [$threeLists, $threeQueries] = $this->profileWaitingAt([1, 3, 2]);

        $this->assertSame([3, null], array_values($oneList));
        $this->assertSame([2, 4, 3, null], array_values($threeLists));
        $this->assertSame($oneQueries, $threeQueries);
    }

    public function test_the_message_and_the_email_after_joining_give_the_place_among_those_waiting(): void
    {
        $this->setWaitingList(true);
        Event::fake([ParticipantRegistered::class]);
        $table = $this->fullTable();
        [$first] = $this->queueByAdmin($table, 3);
        $this->service()->cancel($first->id);
        $user = UserModel::factory()->create();

        $this->actingAs($user)
            ->postJson(route('gametables.register', $table->slug), ['role' => 'player'])
            ->assertRedirect()
            ->assertSessionHas('success', __('game-tables::messages.success.waiting_list', ['position' => 3]));

        $row = ParticipantModel::query()->where('game_table_id', $table->id)->where('user_id', $user->id)->firstOrFail();
        $this->assertSame(4, $row->waiting_list_position);
        Event::assertDispatched(
            ParticipantRegistered::class,
            fn (ParticipantRegistered $event): bool => $event->participantId === $row->id && $event->waitingListPosition === 3,
        );
    }

    // The web guest's registration email waits for the confirmation: the admin guest's goes out at once
    public function test_the_message_after_a_guest_joins_and_the_email_of_an_admin_guest_give_the_place_among_those_waiting(): void
    {
        // 18:00 in Madrid (CET): the place is held until 20:00
        $this->travelTo(CarbonImmutable::parse('2026-11-04 18:00:00', 'Europe/Madrid'));
        $this->setWaitingList(true);
        Event::fake([GuestRegistered::class]);
        $table = $this->fullTable();
        [$first] = $this->queueByAdmin($table, 2);
        $this->service()->cancel($first->id);

        $this->postJson(route('gametables.register-guest', $table->slug), [
            'first_name' => 'Invitada',
            'email' => 'invitada@example.test',
            'role' => 'player',
            'gdpr_consent' => true,
        ])
            ->assertRedirect()
            ->assertSessionHas(
                'success',
                'Te hemos enviado un email para confirmar la inscripción. Confírmala antes del 04/11/2026 20:00 para conservar tu puesto 2 en la lista de espera.',
            );
        Event::assertNotDispatched(GuestRegistered::class);

        $this->service()->registerGuestByAdmin(new RegisterParticipantDTO(
            gameTableId: $table->id,
            userId: null,
            role: ParticipantRole::Player,
            firstName: 'Otra',
            email: 'otra@example.test',
        ));

        Event::assertDispatched(
            GuestRegistered::class,
            fn (GuestRegistered $event): bool => $event->email === 'otra@example.test' && $event->waitingListPosition === 3,
        );
    }

    #[DataProvider('waitingListSetting')]
    public function test_an_admin_adding_someone_to_the_waiting_list_sends_the_place_among_those_waiting(bool $waitingList): void
    {
        $this->setWaitingList($waitingList);
        Event::fake([ParticipantRegistered::class]);
        $table = $this->fullTable();
        [$first] = $this->queueByAdmin($table, 2);
        $this->service()->cancel($first->id);

        $added = $this->service()->registerByAdmin(new RegisterParticipantDTO(
            gameTableId: $table->id,
            userId: UserModel::factory()->create()->id,
            role: ParticipantRole::Player,
        ));

        $this->assertSame(3, $added->waitingListPosition);
        $this->assertSame(2, $added->waitingListRank);
        Event::assertDispatched(
            ParticipantRegistered::class,
            fn (ParticipantRegistered $event): bool => $event->participantId === $added->id && $event->waitingListPosition === 2,
        );
    }

    #[DataProvider('waitingListSetting')]
    public function test_waiting_spectators_do_not_count_in_the_place_of_a_waiting_player(bool $waitingList): void
    {
        $this->setWaitingList($waitingList);
        $table = $this->fullTable();
        $this->rows($table, 'spectator', 'waiting_list', 1, ['waiting_list_position' => 1]);
        [$player] = $this->rows($table, 'player', 'waiting_list', 1, ['waiting_list_position' => 2]);

        $this->actingAs($this->userOf($player))->get(route('gametables.show', $table->slug))
            ->assertInertia(fn (Assert $page) => $page->where('userRegistration.waitingListPosition', 1));
    }

    public function test_on_the_same_position_the_earlier_registration_goes_first_in_the_place_and_the_promotion(): void
    {
        $this->setWaitingList(true);
        $table = $this->table(['max_players' => 1]);
        [$seated] = $this->rows($table, 'player', 'confirmed', 1);
        // Stored first, registered later
        [$later] = $this->rows($table, 'player', 'waiting_list', 1, ['waiting_list_position' => 1]);
        [$earlier] = $this->rows($table, 'player', 'waiting_list', 1, ['waiting_list_position' => 1]);
        ParticipantModel::query()->whereKey($later->id)->update(['created_at' => now()->subHour()]);
        ParticipantModel::query()->whereKey($earlier->id)->update(['created_at' => now()->subDay()]);

        $this->actingAs($this->userOf($later))->get(route('gametables.show', $table->slug))
            ->assertInertia(fn (Assert $page) => $page->where('userRegistration.waitingListPosition', 2));

        $this->service()->cancel($seated->id);

        $this->assertSame('confirmed', $earlier->refresh()->status->value);
        $this->assertSame('waiting_list', $later->refresh()->status->value);
    }

    #[DataProvider('waitingListSetting')]
    public function test_someone_no_longer_waiting_has_no_place(bool $waitingList): void
    {
        $this->setWaitingList($waitingList);
        $table = $this->fullTable();
        [$first] = $this->queueByAdmin($table, 1);

        $this->service()->cancel($first->id);

        $this->actingAs($this->userOf($first))->get(route('gametables.show', $table->slug))
            ->assertInertia(fn (Assert $page) => $page
                ->where('userRegistration.status', 'cancelled')
                ->where('userRegistration.waitingListPosition', null));
    }

    /**
     * A user waiting behind the given number of people at each of several tables (cancelled rows
     * in between), and confirmed at one more: the places the profile gives and its query count.
     *
     * @param  list<int>  $aheadAtEachTable
     * @return array{array<string, int|null>, int}
     */
    private function profileWaitingAt(array $aheadAtEachTable): array
    {
        $user = UserModel::factory()->create();

        foreach ($aheadAtEachTable as $ahead) {
            $table = $this->fullTable();
            $this->rows($table, 'player', 'waiting_list', $ahead, ['waiting_list_position' => 1]);
            $this->rows($table, 'player', 'cancelled', 1, ['waiting_list_position' => 1]);
            $this->rows($table, 'player', 'waiting_list', 1, ['user_id' => $user->id, 'waiting_list_position' => 9]);
            $this->travel(1)->minutes();
        }
        $this->rows($this->table(['starts_at' => now()->addMonth()]), 'player', 'confirmed', 1, ['user_id' => $user->id]);

        DB::flushQueryLog();
        DB::enableQueryLog();
        $participations = $this->service()->getByUserForProfile((string) $user->id);
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        $places = [];
        foreach ($participations as $participation) {
            $places[$participation->gameTableId] = $participation->waitingListPosition;
        }

        return [$places, $queries];
    }

    private function service(): RegistrationServiceInterface
    {
        return app(RegistrationServiceInterface::class);
    }

    private function setWaitingList(bool $enabled): void
    {
        config()->set('modules.settings.game-tables.registration.waiting_list_enabled', $enabled);
    }

    private function userOf(ParticipantModel $row): UserModel
    {
        return UserModel::query()->findOrFail($row->user_id);
    }

    /**
     * Queues users through the admin path, which puts them on the waiting list of a full table
     * whatever the setting.
     *
     * @return list<ParticipantModel>
     */
    private function queueByAdmin(GameTableModel $table, int $count): array
    {
        $rows = [];
        for ($i = 0; $i < $count; $i++) {
            $dto = $this->service()->registerByAdmin(new RegisterParticipantDTO(
                gameTableId: $table->id,
                userId: UserModel::factory()->create()->id,
                role: ParticipantRole::Player,
            ));
            $rows[] = ParticipantModel::query()->findOrFail($dto->id);
        }

        foreach ($rows as $position => $row) {
            $this->assertSame('waiting_list', $row->status->value);
            $this->assertSame($position + 1, $row->waiting_list_position);
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return list<ParticipantModel>
     */
    private function rows(GameTableModel $table, string $role, string $status, int $count, array $attributes = []): array
    {
        $rows = [];
        for ($i = 0; $i < $count; $i++) {
            $rows[] = ParticipantModel::create([
                'id' => (string) Str::uuid(),
                'game_table_id' => $table->id,
                'user_id' => UserModel::factory()->create()->id,
                'role' => $role,
                'status' => $status,
                'confirmed_at' => $status === 'confirmed' ? now() : null,
                ...$attributes,
            ]);
        }

        return $rows;
    }

    private function fullTable(): GameTableModel
    {
        $table = $this->table(['max_players' => 1]);
        $this->rows($table, 'player', 'confirmed', 1);

        return $table;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function table(array $attributes = []): GameTableModel
    {
        return GameTableModel::create([
            'id' => (string) Str::uuid(),
            'game_system_id' => $this->gameSystem->id,
            'created_by' => UserModel::factory()->create()->id,
            'title' => 'Mesa de prueba',
            'slug' => 'mesa-de-prueba-'.Str::lower(Str::random(6)),
            'starts_at' => now()->addWeek(),
            'duration_minutes' => 240,
            'table_type' => 'one_shot',
            'table_format' => 'in_person',
            'status' => 'scheduled',
            'min_players' => 1,
            'max_players' => 5,
            'max_spectators' => 0,
            'language' => 'es',
            'registration_type' => 'everyone',
            'auto_confirm' => true,
            'is_published' => true,
            ...$attributes,
        ]);
    }
}
