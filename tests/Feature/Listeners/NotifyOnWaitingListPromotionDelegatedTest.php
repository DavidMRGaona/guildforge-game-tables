<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Feature\Listeners;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Modules\GameTables\Domain\Enums\GameMasterRole;
use Modules\GameTables\Domain\Events\ParticipantPromotedFromWaitingList;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameMasterModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameSystemModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameTableModel;
use Modules\GameTables\Notifications\WaitingListPromotionGmNotification;
use Tests\Support\Modules\ModuleTestCase;

/**
 * The directors' copy of a waiting-list promotion: on a table that delegates its registrations,
 * a promotion awaiting approval sends the linked directors to «Gestionar inscritos»; everyone else
 * keeps the panel's wording. An address never gets two copies.
 */
final class NotifyOnWaitingListPromotionDelegatedTest extends ModuleTestCase
{
    protected ?string $moduleName = 'game-tables';

    protected bool $autoEnableModule = true;

    private UserModel $director;

    private GameSystemModel $system;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        config()->set('modules.settings.game-tables', []);
        app()->setLocale('es');

        $this->director = UserModel::factory()->create(['name' => 'John Doe', 'email' => 'John@Example.com']);
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

    private function promoted(GameTableModel $table, bool $confirmed): void
    {
        event(new ParticipantPromotedFromWaitingList((string) Str::uuid(), $table->id, null, $confirmed));
    }

    /**
     * @return array<string, array{string|null, list<string>}> lower-cased address => [action URL, lines], one entry per sent notification
     */
    private function sent(): array
    {
        $sent = [];
        $count = 0;

        // To accounts and to plain addresses alike: directors with an account get it as the account
        foreach (Notification::sentNotifications() as $byKey) {
            foreach ($byKey as $byClass) {
                foreach ($byClass[WaitingListPromotionGmNotification::class] ?? [] as $entry) {
                    $count++;
                    $notifiable = $entry['notifiable'];
                    $address = $notifiable instanceof AnonymousNotifiable ? $notifiable->routes['mail'] : $notifiable->email;
                    $mail = $entry['notification']->toMail($notifiable);
                    $sent[strtolower((string) $address)] = [$mail->actionUrl, array_values(array_map(strval(...), $mail->introLines))];
                }
            }
        }
        $this->assertCount($count, $sent, 'An address received more than one copy');
        // Accounts and plain addresses are recorded apart: compare by address, not by sending order
        ksort($sent);

        return $sent;
    }

    public function test_a_pending_promotion_sends_the_director_to_the_registrations_page(): void
    {
        $this->promoted($this->table(true), confirmed: false);

        $sent = $this->sent();

        $this->assertCount(2, $sent);
        [$directorUrl, $directorLines] = $sent['john@example.com'];
        $this->assertSame(url('/mesas/mis-mesas/mesa-de-prueba/inscritos'), $directorUrl);
        $this->assertContains(__('game-tables::emails.waiting_list_promotion_gm.pending_manage'), $directorLines);
        $this->assertNotContains(__('game-tables::emails.waiting_list_promotion_gm.pending'), $directorLines);

        [$tableUrl, $tableLines] = $sent['table@example.com'];
        $this->assertStringEndsWith('/mesas/'.$this->tableId(), (string) $tableUrl);
        $this->assertContains(__('game-tables::emails.waiting_list_promotion_gm.pending'), $tableLines);
    }

    public function test_a_table_that_does_not_delegate_keeps_the_panel_wording(): void
    {
        $this->promoted($this->table(false), confirmed: false);

        foreach ($this->sent() as [$url, $lines]) {
            $this->assertStringNotContainsString('/inscritos', (string) $url);
            $this->assertContains(__('game-tables::emails.waiting_list_promotion_gm.pending'), $lines);
        }
    }

    public function test_a_confirmed_promotion_gives_no_link(): void
    {
        $this->promoted($this->table(true), confirmed: true);

        $sent = $this->sent();

        $this->assertCount(2, $sent);
        foreach ($sent as [$url, $lines]) {
            $this->assertStringNotContainsString('/inscritos', (string) $url);
            $this->assertNotContains(__('game-tables::emails.waiting_list_promotion_gm.pending'), $lines);
            $this->assertNotContains(__('game-tables::emails.waiting_list_promotion_gm.pending_manage'), $lines);
        }
    }

    public function test_a_director_who_is_also_the_table_email_gets_the_link_once(): void
    {
        $this->promoted($this->table(true, 'JOHN@example.com'), confirmed: false);

        $sent = $this->sent();

        $this->assertCount(1, $sent);
        $this->assertSame(url('/mesas/mis-mesas/mesa-de-prueba/inscritos'), $sent['john@example.com'][0]);
    }

    public function test_a_director_without_email_notifications_gets_nothing(): void
    {
        $this->promoted($this->table(true, 'table@example.com', false), confirmed: false);

        $this->assertSame(['table@example.com'], array_keys($this->sent()));
    }

    public function test_with_the_setting_off_the_directors_get_nothing(): void
    {
        config()->set('modules.settings.game-tables.notifications.notify_waiting_list_promotion', false);

        $this->promoted($this->table(true), confirmed: false);

        $this->assertSame([], $this->sent());
    }

    private function tableId(): string
    {
        return (string) GameTableModel::query()->value('id');
    }
}
