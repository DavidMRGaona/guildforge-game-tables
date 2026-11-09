<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Feature\Mail;

use App\Application\Mail\Services\MailPreferenceServiceInterface;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Modules\GameTables\Console\Commands\SendTableRemindersCommand;
use Modules\GameTables\Domain\Enums\GameMasterRole;
use Modules\GameTables\Domain\Enums\ParticipantStatus;
use Modules\GameTables\Domain\Enums\TableStatus;
use Modules\GameTables\Domain\Events\ParticipantRegistered;
use Modules\GameTables\Infrastructure\Listeners\NotifyOnRegistration;
use Modules\GameTables\Infrastructure\Mail\GameTablesMailCategories;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameMasterModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameSystemModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameTableModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\ParticipantModel;
use Modules\GameTables\Notifications\ParticipantRegisteredNotification;
use Modules\GameTables\Notifications\RegistrationConfirmation;
use Modules\GameTables\Notifications\TableReminderNotification;
use Tests\Support\Modules\ModuleTestCase;

/**
 * Mail is really sent here (array mailer, sync queue): the core's filter runs on
 * NotificationSending, which Notification::fake() never dispatches.
 */
final class MailPreferenceDeliveryTest extends ModuleTestCase
{
    protected ?string $moduleName = 'game-tables';

    protected bool $autoEnableModule = true;

    /**
     * @var list<array{class-string, string}> notification class and the address it went to
     */
    private array $sent = [];

    private CarbonImmutable $now;

    private GameSystemModel $system;

    protected function setUp(): void
    {
        parent::setUp();

        app()->setLocale('es');
        config()->set('modules.settings.game-tables', []);
        $this->now = CarbonImmutable::parse('2026-11-02 10:00:00', 'Europe/Madrid');
        $this->travelTo($this->now);

        $this->system = GameSystemModel::create([
            'id' => (string) Str::uuid(),
            'name' => 'Fixture System',
            'slug' => 'fixture-system',
            'is_active' => true,
        ]);

        Event::listen(NotificationSent::class, function (NotificationSent $event): void {
            $address = $event->notifiable instanceof AnonymousNotifiable
                ? (string) $event->notifiable->routeNotificationFor('mail')
                : (string) $event->notifiable->email;

            $this->sent[] = [$event->notification::class, $address];
        });
    }

    public function test_a_reminder_the_person_turned_off_is_not_sent(): void
    {
        config()->set('modules.settings.game-tables.notifications', ['send_reminder' => true, 'reminder_hours_before' => 24]);
        $this->app->make(Kernel::class)->registerCommand($this->app->make(SendTableRemindersCommand::class));
        $table = $this->table();
        $wantsIt = UserModel::factory()->create(['email' => 'quiere@example.com']);
        $optedOut = UserModel::factory()->create(['email' => 'no-quiere@example.com']);
        $this->participant($table, $wantsIt);
        $this->participant($table, $optedOut);
        $this->preferences()->update($optedOut, [GameTablesMailCategories::REMINDERS => false]);

        $this->artisan('game-tables:send-reminders')->assertSuccessful();

        $this->assertSame([[TableReminderNotification::class, 'quiere@example.com']], $this->sent);
    }

    public function test_a_director_who_turned_off_the_notices_gets_none_but_the_table_mailbox_still_does(): void
    {
        $table = $this->table(['notification_email' => 'mesa@example.com']);
        $director = UserModel::factory()->create(['email' => 'directora@example.com']);
        $this->gameMaster($table, $director);
        $this->preferences()->update($director, [GameTablesMailCategories::DIRECTION => false]);

        $this->app->make(NotifyOnRegistration::class)->handle(new ParticipantRegistered(
            participantId: (string) Str::uuid(),
            gameTableId: $table->id,
            userId: null,
            role: 'player',
            status: ParticipantStatus::Confirmed,
        ));

        $this->assertSame([[ParticipantRegisteredNotification::class, 'mesa@example.com']], $this->sent);
    }

    public function test_a_director_who_keeps_the_notices_gets_them_as_an_account(): void
    {
        $table = $this->table();
        $director = UserModel::factory()->create(['email' => 'directora@example.com']);
        $this->gameMaster($table, $director);

        $this->app->make(NotifyOnRegistration::class)->handle(new ParticipantRegistered(
            participantId: (string) Str::uuid(),
            gameTableId: $table->id,
            userId: null,
            role: 'player',
            status: ParticipantStatus::Confirmed,
        ));

        $this->assertSame([[ParticipantRegisteredNotification::class, 'directora@example.com']], $this->sent);
    }

    public function test_mandatory_mail_is_sent_whatever_the_person_turned_off(): void
    {
        $table = $this->table();
        $user = UserModel::factory()->create(['email' => 'cuenta@example.com']);
        $this->preferences()->update($user, [
            GameTablesMailCategories::REMINDERS => false,
            GameTablesMailCategories::CAMPAIGN_NEWS => false,
        ]);

        event(new ParticipantRegistered(
            participantId: (string) Str::uuid(),
            gameTableId: $table->id,
            userId: $user->id,
            role: 'player',
            status: ParticipantStatus::Confirmed,
            notifyOrganization: false,
        ));

        $this->assertContains([RegistrationConfirmation::class, 'cuenta@example.com'], $this->sent);
    }

    private function preferences(): MailPreferenceServiceInterface
    {
        return app(MailPreferenceServiceInterface::class);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function table(array $attributes = []): GameTableModel
    {
        return GameTableModel::create([
            'id' => (string) Str::uuid(),
            'game_system_id' => $this->system->id,
            'created_by' => UserModel::factory()->create()->id,
            'title' => 'La cripta olvidada',
            'slug' => 'la-cripta-olvidada',
            'starts_at' => $this->now->addHours(10),
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

    private function participant(GameTableModel $table, UserModel $user): void
    {
        ParticipantModel::create([
            'id' => (string) Str::uuid(),
            'game_table_id' => $table->id,
            'user_id' => $user->id,
            'role' => 'player',
            'status' => 'confirmed',
        ]);
    }

    private function gameMaster(GameTableModel $table, UserModel $user): void
    {
        $gm = GameMasterModel::create([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'first_name' => 'GM',
            'role' => GameMasterRole::Main,
            'notify_by_email' => true,
            'is_name_public' => true,
        ]);
        $table->gameMasters()->attach($gm->id, ['source' => 'local', 'excluded' => false, 'sort_order' => 1]);
    }
}
