<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Feature\Http\Controllers;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use DateTimeInterface;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameSystemModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameTableModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\ParticipantModel;
use Tests\Support\Modules\ModuleTestCase;

/**
 * Participants leave a table by themselves (the table page or the emailed link) only before it
 * starts; the pages offer the cancellation with the same rule.
 */
final class WithdrawalAfterStartTest extends ModuleTestCase
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

    public function test_a_user_cannot_cancel_once_the_table_has_started(): void
    {
        $table = $this->table(now()->subMinute());
        $user = UserModel::factory()->create();
        $row = $this->participant($table, ['user_id' => $user->id]);

        $this->actingAs($user)
            ->from(route('gametables.show', $table->slug))
            ->delete(route('gametables.cancel', $table->slug))
            ->assertRedirect(route('gametables.show', $table->slug))
            ->assertSessionHas('error', 'La partida ya ha comenzado: ya no puedes cancelar la inscripción');

        $this->assertSame('confirmed', $row->refresh()->status->value);
    }

    public function test_a_user_cancels_before_the_start(): void
    {
        $table = $this->table(now()->addHour());
        $user = UserModel::factory()->create();
        $row = $this->participant($table, ['user_id' => $user->id]);

        $this->actingAs($user)
            ->from(route('gametables.show', $table->slug))
            ->delete(route('gametables.cancel', $table->slug))
            ->assertSessionHas('success');

        $this->assertSame('cancelled', $row->refresh()->status->value);
    }

    public function test_a_guest_link_cannot_cancel_once_the_table_has_started(): void
    {
        $table = $this->table(now()->subMinute());
        $row = $this->guest($table);

        $this->delete(route('gametables.cancel-by-token', 'token-de-prueba'))
            ->assertRedirect(route('gametables.index'))
            ->assertSessionHas('error', 'La partida ya ha comenzado: ya no puedes cancelar la inscripción');

        $this->assertSame('confirmed', $row->refresh()->status->value);
    }

    public function test_the_cancellation_page_says_the_table_has_started(): void
    {
        $this->guest($this->table(now()->subMinute()));

        $this->get(route('gametables.cancel-confirmation', 'token-de-prueba'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('GameTables/CancelRegistration', shouldExist: false)
                ->where('canCancel', false)
                ->where('tableStarted', true));
    }

    public function test_the_cancellation_page_offers_the_cancellation_before_the_start(): void
    {
        $this->guest($this->table(now()->addHour()));

        $this->get(route('gametables.cancel-confirmation', 'token-de-prueba'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('canCancel', true)
                ->where('tableStarted', false));
    }

    // An inactive registration keeps its own message, whatever the time
    public function test_the_cancellation_page_of_a_cancelled_registration_does_not_blame_the_start(): void
    {
        $this->guest($this->table(now()->subMinute()), ['status' => 'cancelled', 'confirmed_at' => null]);

        $this->get(route('gametables.cancel-confirmation', 'token-de-prueba'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('canCancel', false)
                ->where('tableStarted', false));
    }

    public function test_the_table_page_offers_the_cancellation_only_before_the_start(): void
    {
        $user = UserModel::factory()->create();
        $upcoming = $this->table(now()->addHour());
        $started = $this->table(now()->subMinute());
        $this->participant($upcoming, ['user_id' => $user->id]);
        $this->participant($started, ['user_id' => $user->id]);

        $this->actingAs($user)
            ->get(route('gametables.show', $upcoming->slug))
            ->assertInertia(fn (Assert $page) => $page->where('eligibility.canCancel', true));
        $this->actingAs($user)
            ->get(route('gametables.show', $started->slug))
            ->assertInertia(fn (Assert $page) => $page->where('eligibility.canCancel', false));
    }

    public function test_without_a_registration_the_table_page_offers_no_cancellation(): void
    {
        $table = $this->table(now()->addHour());

        $this->actingAs(UserModel::factory()->create())
            ->get(route('gametables.show', $table->slug))
            ->assertInertia(fn (Assert $page) => $page->where('eligibility.canCancel', false));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function participant(GameTableModel $table, array $attributes = []): ParticipantModel
    {
        return ParticipantModel::create([
            'id' => (string) Str::uuid(),
            'game_table_id' => $table->id,
            'role' => 'player',
            'status' => 'confirmed',
            'confirmed_at' => now(),
            ...$attributes,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function guest(GameTableModel $table, array $attributes = []): ParticipantModel
    {
        return $this->participant($table, [
            'user_id' => null,
            'first_name' => 'Invitada',
            'email' => 'invitada@example.test',
            'cancellation_token' => 'token-de-prueba',
            ...$attributes,
        ]);
    }

    private function table(DateTimeInterface $startsAt): GameTableModel
    {
        return GameTableModel::create([
            'id' => (string) Str::uuid(),
            'game_system_id' => $this->gameSystem->id,
            'created_by' => UserModel::factory()->create()->id,
            'title' => 'Mesa de prueba',
            'slug' => 'mesa-de-prueba-'.Str::lower(Str::random(6)),
            'starts_at' => $startsAt,
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
        ]);
    }
}
