<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Feature\Listeners;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Modules\GameTables\Domain\Enums\GameMasterRole;
use Modules\GameTables\Domain\Enums\ParticipantStatus;
use Modules\GameTables\Domain\Events\GuestRegistered;
use Modules\GameTables\Domain\Events\ParticipantRegistered;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameMasterModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameSystemModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameTableModel;
use Modules\GameTables\Notifications\ParticipantRegisteredNotification;
use Tests\Support\Modules\ModuleTestCase;

final class NotifyOnRegistrationDelegatedTest extends ModuleTestCase
{
    protected ?string $moduleName = 'game-tables';

    protected bool $autoEnableModule = true;

    private UserModel $director;

    private UserModel $mary;

    private GameSystemModel $system;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        config()->set('modules.settings.game-tables', []);
        app()->setLocale('es');

        $this->director = UserModel::factory()->create(['name' => 'John Doe', 'email' => 'John@Example.com']);
        $this->mary = UserModel::factory()->create(['name' => 'Mary Major', 'email' => 'mary@example.com']);
        $this->system = GameSystemModel::create(['id' => (string) Str::uuid(), 'name' => 'Sistema de prueba', 'slug' => 'sistema-de-prueba', 'is_active' => true]);
    }

    private function table(bool $delegates, ?string $notificationEmail = 'table@example.com', bool $notify = true): GameTableModel
    {
        $table = GameTableModel::create([
            'id' => (string) Str::uuid(),
            'game_system_id' => $this->system->id,
            'created_by' => $this->director->id,
            'title' => 'Mesa de prueba',
            'slug' => 'mesa-de-prueba',
            'starts_at' => '2026-11-15 18:00:00',
            'duration_minutes' => 240,
            'table_type' => 'one_shot',
            'table_format' => 'in_person',
            'status' => 'scheduled',
            'min_players' => 1,
            'max_players' => 5,
            'language' => 'es',
            'registration_type' => 'everyone',
            'auto_confirm' => false,
            'is_published' => true,
            'delegates_registrations' => $delegates,
            'notification_email' => $notificationEmail,
        ]);
        $gm = GameMasterModel::create([
            'id' => (string) Str::uuid(),
            'user_id' => $this->director->id,
            'first_name' => 'GM',
            'last_name' => 'Doe',
            'role' => GameMasterRole::Main,
            'notify_by_email' => $notify,
            'is_name_public' => true,
        ]);
        $table->gameMasters()->attach($gm->id, ['source' => 'local', 'excluded' => false, 'sort_order' => 1]);

        return $table;
    }

    private function registered(GameTableModel $table, ParticipantStatus $status): void
    {
        event(new ParticipantRegistered((string) Str::uuid(), $table->id, $this->mary->id, 'player', $status));
    }

    private function guest(GameTableModel $table, ParticipantStatus $status): void
    {
        event(new GuestRegistered((string) Str::uuid(), $table->id, 'guest@example.com', 'Guest', 'player', 'token', $status));
    }

    /**
     * @return array<string, ?string> lower-cased address => manage URL, one entry per sent notification
     */
    private function sent(): array
    {
        $sent = [];
        $count = 0;
        Notification::assertSentOnDemand(ParticipantRegisteredNotification::class, function (ParticipantRegisteredNotification $n, array $channels, AnonymousNotifiable $notifiable) use (&$sent, &$count): bool {
            $count++;
            $sent[strtolower((string) $notifiable->routes['mail'])] = (fn () => $this->manageUrl)->call($n);

            return true;
        });
        $this->assertCount($count, $sent, 'An address received more than one copy');

        return $sent;
    }

    public function test_pending_registration_links_the_director_only(): void
    {
        $this->registered($this->table(true), ParticipantStatus::Pending);

        $sent = $this->sent();

        $this->assertCount(2, $sent);
        $this->assertStringEndsWith('/mesas/mis-mesas/mesa-de-prueba/inscritos', (string) $sent['john@example.com']);
        $this->assertNull($sent['table@example.com']);
    }

    public function test_not_delegated_gives_no_link(): void
    {
        $this->registered($this->table(false), ParticipantStatus::Pending);

        $this->assertSame(['john@example.com' => null, 'table@example.com' => null], $this->sent());
    }

    public function test_confirmed_registration_gives_no_link(): void
    {
        $this->registered($this->table(true), ParticipantStatus::Confirmed);

        $this->assertSame(['john@example.com' => null, 'table@example.com' => null], $this->sent());
    }

    public function test_director_who_is_also_the_table_email_gets_the_link_once(): void
    {
        $this->registered($this->table(true, 'JOHN@example.com'), ParticipantStatus::Pending);

        $sent = $this->sent();

        $this->assertCount(1, $sent);
        $this->assertStringEndsWith('/inscritos', (string) $sent['john@example.com']);
    }

    public function test_director_without_email_notifications_gets_no_link(): void
    {
        $this->registered($this->table(true, 'table@example.com', false), ParticipantStatus::Pending);

        $this->assertSame(['table@example.com' => null], $this->sent());
    }

    public function test_guest_pending_registration_links_the_director_only(): void
    {
        $this->guest($this->table(true), ParticipantStatus::Pending);

        $sent = $this->sent();

        $this->assertCount(2, $sent);
        $this->assertStringEndsWith('/mesas/mis-mesas/mesa-de-prueba/inscritos', (string) $sent['john@example.com']);
        $this->assertNull($sent['table@example.com']);
    }

    public function test_guest_not_delegated_or_confirmed_gives_no_link(): void
    {
        $this->guest($this->table(false), ParticipantStatus::Pending);

        $this->assertSame(['john@example.com' => null, 'table@example.com' => null], $this->sent());
    }

    public function test_mail_shows_the_action_and_pending_line_only_with_the_link(): void
    {
        $with = (new ParticipantRegisteredNotification('John Doe', 'id', 'Mesa', null, null, 'player', 'https://example.test/mesas/mis-mesas/x/inscritos'))->toMail(new AnonymousNotifiable);
        $without = (new ParticipantRegisteredNotification('John Doe', 'id', 'Mesa', null, null, 'player'))->toMail(new AnonymousNotifiable);

        $this->assertSame('Gestionar inscritos', $with->actionText);
        $this->assertSame('https://example.test/mesas/mis-mesas/x/inscritos', $with->actionUrl);
        $this->assertContains(__('game-tables::emails.registration.pending_intro'), $with->introLines);
        $this->assertSame('Ver mesa', $without->actionText);
        $this->assertNotContains(__('game-tables::emails.registration.pending_intro'), $without->introLines);
    }
}
