<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Feature\Console;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Event as ScheduledEvent;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Testing\PendingCommand;
use Modules\GameTables\Console\Commands\SendTableRemindersCommand;
use Modules\GameTables\Domain\Enums\TableStatus;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameSystemModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameTableModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\ParticipantModel;
use Modules\GameTables\Notifications\TableReminderNotification;
use Tests\Support\Modules\ModuleTestCase;

/**
 * The reminder before a table is ready but off until enabled (notifications.send_reminder).
 */
final class SendTableRemindersCommandTest extends ModuleTestCase
{
    protected ?string $moduleName = 'game-tables';

    protected bool $autoEnableModule = true;

    private CarbonImmutable $now;

    private GameSystemModel $system;

    protected function setUp(): void
    {
        parent::setUp();

        app()->setLocale('es');
        app('router')->getRoutes()->refreshNameLookups();
        Notification::fake();
        config()->set('modules.settings.game-tables', []);
        $this->now = CarbonImmutable::parse('2026-11-02 10:00:00');
        $this->travelTo($this->now);
        $this->app->make(Kernel::class)->registerCommand($this->app->make(SendTableRemindersCommand::class));

        $this->system = GameSystemModel::create([
            'id' => (string) Str::uuid(),
            'name' => 'Fixture System',
            'slug' => 'fixture-system',
            'is_active' => true,
        ]);
    }

    public function test_nothing_is_sent_while_reminders_are_off(): void
    {
        $row = $this->participant($this->table($this->now->addHours(10)), ['email' => 'jugadora@example.com', 'email_confirmed_at' => $this->now]);

        $this->sendReminders();

        Notification::assertNothingSent();
        $this->assertNull($row->refresh()->reminder_sent_at);
    }

    public function test_confirmed_participants_of_a_table_starting_soon_are_reminded_once(): void
    {
        $this->enable();
        $table = $this->table($this->now->addHours(10));
        $user = UserModel::factory()->create(['email' => 'cuenta@example.com']);
        $this->participant($table, ['user_id' => $user->id]);
        $this->participant($table, ['email' => 'invitada@example.com', 'first_name' => 'John', 'email_confirmed_at' => $this->now]);
        $this->participant($table, ['email' => 'pendiente@example.com', 'email_confirmed_at' => null]);
        $this->participant($table, ['email' => 'espera@example.com', 'email_confirmed_at' => $this->now, 'status' => 'waiting_list', 'waiting_list_position' => 1]);

        $this->sendReminders();
        $this->sendReminders();

        Notification::assertSentToTimes($user, TableReminderNotification::class, 1);
        Notification::assertSentOnDemandTimes(TableReminderNotification::class, 1);
        Notification::assertSentOnDemand(
            TableReminderNotification::class,
            function (TableReminderNotification $n, array $channels, AnonymousNotifiable $notifiable): bool {
                $message = $n->toMail($notifiable);

                return $notifiable->routes['mail'] === 'invitada@example.com'
                    && $message->subject === 'Recordatorio: La cripta olvidada, 02/11/2026 20:00'
                    && $message->greeting === '¡Hola, John!'
                    && $message->actionUrl === route('gametables.show', 'la-cripta-olvidada');
            },
        );
    }

    public function test_tables_outside_the_window_or_not_running_are_skipped(): void
    {
        $this->enable();
        foreach ([
            $this->table($this->now->addHours(30), slug: 'mas-tarde'),
            $this->table($this->now->subHour(), slug: 'empezada'),
            $this->table($this->now->addHours(5), ['status' => TableStatus::Cancelled->value], 'cancelada'),
            $this->table($this->now->addHours(5), ['is_published' => false], 'borrador'),
        ] as $table) {
            $this->participant($table, ['user_id' => UserModel::factory()->create()->id]);
        }

        $this->sendReminders();

        Notification::assertNothingSent();
    }

    public function test_anonymised_accounts_and_nameless_guests_are_handled(): void
    {
        $this->enable();
        $table = $this->table($this->now->addHours(10));
        $anonymised = UserModel::factory()->create(['anonymized_at' => $this->now->subDay()]);
        $this->participant($table, ['user_id' => $anonymised->id]);
        $this->participant($table, ['email' => 'sin.nombre@example.com', 'first_name' => null, 'email_confirmed_at' => $this->now]);

        $this->sendReminders();

        Notification::assertNotSentTo($anonymised, TableReminderNotification::class);
        Notification::assertSentOnDemand(
            TableReminderNotification::class,
            fn (TableReminderNotification $n, array $channels, AnonymousNotifiable $notifiable): bool => $n->toMail($notifiable)->greeting === '¡Hola!',
        );
    }

    public function test_the_lead_time_is_configurable(): void
    {
        $this->enable(hours: 48);
        $user = UserModel::factory()->create();
        $this->participant($this->table($this->now->addHours(30)), ['user_id' => $user->id]);

        $this->sendReminders();

        Notification::assertSentTo($user, TableReminderNotification::class);
    }

    public function test_the_command_is_scheduled_hourly_without_overlapping(): void
    {
        $events = array_values(array_filter(
            app(Schedule::class)->events(),
            fn (ScheduledEvent $event): bool => str_contains((string) $event->command, 'game-tables:send-reminders'),
        ));

        $this->assertCount(1, $events);
        $this->assertSame('0 * * * *', $events[0]->expression);
        $this->assertTrue($events[0]->withoutOverlapping);
    }

    private function sendReminders(): void
    {
        $command = $this->artisan('game-tables:send-reminders');
        $this->assertInstanceOf(PendingCommand::class, $command);
        $command->assertSuccessful()->run();
    }

    private function enable(int $hours = 24): void
    {
        config()->set('modules.settings.game-tables.notifications', ['send_reminder' => true, 'reminder_hours_before' => $hours]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function table(CarbonImmutable $startsAt, array $attributes = [], string $slug = 'la-cripta-olvidada'): GameTableModel
    {
        return GameTableModel::create([
            'id' => (string) Str::uuid(),
            'game_system_id' => $this->system->id,
            'created_by' => UserModel::factory()->create()->id,
            'title' => 'La cripta olvidada',
            'slug' => $slug,
            'starts_at' => $startsAt,
            'duration_minutes' => 240,
            'table_type' => 'one_shot',
            'table_format' => 'in_person',
            'status' => TableStatus::Scheduled->value,
            'min_players' => 2,
            'max_players' => 6,
            'language' => 'es',
            'is_published' => true,
            ...$attributes,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function participant(GameTableModel $table, array $attributes): ParticipantModel
    {
        return ParticipantModel::create([
            'id' => (string) Str::uuid(),
            'game_table_id' => $table->id,
            'role' => 'player',
            'status' => 'confirmed',
            'confirmed_at' => $this->now->subDay(),
            'cancellation_token' => Str::random(64),
            ...$attributes,
        ]);
    }
}
