<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Feature\Http\Controllers;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Modules\GameTables\Domain\Enums\GameMasterRole;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameMasterModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameSystemModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameTableModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\ParticipantModel;
use Modules\GameTables\Notifications\CancellationConfirmation;
use Modules\GameTables\Notifications\GuestCancellationConfirmation;
use Modules\GameTables\Notifications\OrganizationCancellationNotification;
use Modules\GameTables\Notifications\ParticipantCancelledNotification;
use Modules\GameTables\Notifications\WaitingListPromotionNotification;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\Modules\ModuleTestCase;

/**
 * A participant who cancels from the web: their confirmation email, the directors' notice and
 * the promotion of the next waiting player, with the real listeners running.
 */
final class CancellationMailsTest extends ModuleTestCase
{
    protected ?string $moduleName = 'game-tables';

    protected bool $autoEnableModule = true;

    private GameSystemModel $gameSystem;

    protected function setUp(): void
    {
        parent::setUp();

        app('router')->getRoutes()->refreshNameLookups();
        config()->set('modules.settings.game-tables', []);
        Notification::fake();

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

    #[DataProvider('waitingListSetting')]
    public function test_a_user_who_cancels_gets_the_confirmation_and_the_directors_are_told(bool $waitingList): void
    {
        $this->setWaitingList($waitingList);
        $table = $this->tableWithDirector();
        $user = UserModel::factory()->create(['email' => 'jugadora@example.test']);
        $row = $this->participant($table, 'confirmed', ['user_id' => $user->id]);

        $this->actingAs($user)->delete(route('gametables.cancel', $table->slug))
            ->assertSessionHas('success', __('game-tables::messages.success.cancelled'));

        $this->assertSame('cancelled', $row->refresh()->status->value);
        $this->assertMailedTo(CancellationConfirmation::class, 'jugadora@example.test');
        $this->assertMailedTo(ParticipantCancelledNotification::class, 'director@example.test');
        Notification::assertSentTimes(OrganizationCancellationNotification::class, 0);
    }

    #[DataProvider('waitingListSetting')]
    public function test_a_guest_who_cancels_by_link_gets_the_confirmation_and_the_directors_are_told(bool $waitingList): void
    {
        $this->setWaitingList($waitingList);
        $table = $this->tableWithDirector();
        $row = $this->participant($table, 'confirmed', [
            'user_id' => null,
            'first_name' => 'Invitada',
            'email' => 'invitada@example.test',
            'cancellation_token' => 'token-de-prueba',
        ]);

        $this->delete(route('gametables.cancel-by-token', 'token-de-prueba'))
            ->assertSessionHas('success', __('game-tables::messages.success.cancelled'));

        $this->assertSame('cancelled', $row->refresh()->status->value);
        $this->assertMailedTo(GuestCancellationConfirmation::class, 'invitada@example.test');
        $this->assertMailedTo(ParticipantCancelledNotification::class, 'director@example.test');
        Notification::assertSentTimes(OrganizationCancellationNotification::class, 0);
    }

    #[DataProvider('waitingListSetting')]
    public function test_a_user_who_cancels_a_seat_promotes_the_first_waiting_player_with_their_notice(bool $waitingList): void
    {
        $this->setWaitingList($waitingList);
        $table = $this->tableWithDirector(['max_players' => 1]);
        $user = UserModel::factory()->create();
        $this->participant($table, 'confirmed', ['user_id' => $user->id]);
        $promotedUser = UserModel::factory()->create(['email' => 'siguiente@example.test']);
        $waiting = $this->participant($table, 'waiting_list', ['user_id' => $promotedUser->id, 'waiting_list_position' => 1]);

        $this->actingAs($user)->delete(route('gametables.cancel', $table->slug));

        $this->assertSame('confirmed', $waiting->refresh()->status->value);
        $this->assertMailedTo(WaitingListPromotionNotification::class, 'siguiente@example.test');
    }

    /**
     * @param  class-string  $notification
     */
    private function assertMailedTo(string $notification, string $email): void
    {
        Notification::assertSentOnDemand(
            $notification,
            fn (object $sent, array $channels, AnonymousNotifiable $notifiable): bool => $notifiable->routes['mail'] === $email,
        );
    }

    private function setWaitingList(bool $enabled): void
    {
        config()->set('modules.settings.game-tables.registration.waiting_list_enabled', $enabled);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function participant(GameTableModel $table, string $status, array $attributes = []): ParticipantModel
    {
        return ParticipantModel::create([
            'id' => (string) Str::uuid(),
            'game_table_id' => $table->id,
            'user_id' => UserModel::factory()->create()->id,
            'role' => 'player',
            'status' => $status,
            'confirmed_at' => $status === 'confirmed' ? now() : null,
            ...$attributes,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function tableWithDirector(array $attributes = []): GameTableModel
    {
        $table = GameTableModel::create([
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

        $director = GameMasterModel::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Directora',
            'email' => 'director@example.test',
            'role' => GameMasterRole::Main,
            'notify_by_email' => true,
            'is_name_public' => true,
        ]);
        $table->gameMasters()->attach($director->id, ['source' => 'local', 'excluded' => false, 'sort_order' => 1]);

        return $table;
    }
}
