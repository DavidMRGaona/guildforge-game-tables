<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Feature\Listeners;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Modules\GameTables\Domain\Enums\FrontendCreationStatus;
use Modules\GameTables\Domain\Enums\GameMasterRole;
use Modules\GameTables\Domain\Events\CampaignJoinRequestAccepted;
use Modules\GameTables\Domain\Events\CampaignJoinRequested;
use Modules\GameTables\Domain\Events\CampaignJoinRequestRejected;
use Modules\GameTables\Domain\Events\CampaignRosterMemberAdded;
use Modules\GameTables\Domain\Events\CampaignRosterMemberLeft;
use Modules\GameTables\Domain\Events\CampaignRosterMemberRemoved;
use Modules\GameTables\Domain\Events\RosterMemberEnrolledInSession;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\CampaignJoinRequestModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\CampaignModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameMasterModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameSystemModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameTableModel;
use Modules\GameTables\Notifications\CampaignJoinRequestAcceptedNotification;
use Modules\GameTables\Notifications\CampaignJoinRequestReceivedNotification;
use Modules\GameTables\Notifications\CampaignJoinRequestRejectedNotification;
use Modules\GameTables\Notifications\CampaignPlayerLeftNotification;
use Modules\GameTables\Notifications\CampaignRosterAddedNotification;
use Modules\GameTables\Notifications\CampaignRosterRemovedNotification;
use Modules\GameTables\Notifications\CampaignSessionScheduledNotification;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\Modules\ModuleTestCase;

final class CampaignRosterListenersTest extends ModuleTestCase
{
    protected ?string $moduleName = 'game-tables';

    protected bool $autoEnableModule = true;

    private UserModel $john;

    private UserModel $mary;

    private CampaignModel $campaign;

    private GameTableModel $table;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        config()->set('modules.settings.game-tables', []);
        app()->setLocale('es');

        $this->john = UserModel::factory()->create(['name' => 'John Doe', 'email' => 'john@example.com']);
        $this->mary = UserModel::factory()->create(['name' => 'Mary Major', 'email' => 'mary@example.com']);
        $system = GameSystemModel::create(['id' => (string) Str::uuid(), 'name' => 'Sistema de prueba', 'slug' => 'sistema-de-prueba', 'is_active' => true]);
        $this->campaign = CampaignModel::create([
            'id' => (string) Str::uuid(),
            'game_system_id' => $system->id,
            'created_by' => $this->john->id,
            'title' => 'Campaña de prueba',
            'slug' => 'campana-de-prueba',
            'status' => 'recruiting',
            'frontend_creation_status' => FrontendCreationStatus::Approved,
        ]);
        $this->table = GameTableModel::create([
            'id' => (string) Str::uuid(),
            'game_system_id' => $system->id,
            'campaign_id' => $this->campaign->id,
            'created_by' => $this->john->id,
            'title' => 'Sesión 2',
            'slug' => 'sesion-2',
            'starts_at' => '2026-11-15 18:00:00',
            'duration_minutes' => 240,
            'table_type' => 'one_shot',
            'table_format' => 'in_person',
            'status' => 'scheduled',
            'min_players' => 1,
            'max_players' => 5,
            'language' => 'es',
            'registration_type' => 'everyone',
            'auto_confirm' => true,
            'is_published' => true,
            'location' => 'Sala de juegos',
        ]);
    }

    private function request(?string $message = 'Me encantaría jugar'): CampaignJoinRequestModel
    {
        return CampaignJoinRequestModel::create([
            'id' => (string) Str::uuid(),
            'campaign_id' => $this->campaign->id,
            'user_id' => $this->mary->id,
            'status' => 'pending',
            'message' => $message,
        ]);
    }

    private function gm(UserModel $user, bool $notify = true): void
    {
        $gm = GameMasterModel::create([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'first_name' => 'GM',
            'last_name' => 'Doe',
            'role' => GameMasterRole::Main,
            'notify_by_email' => $notify,
            'is_name_public' => true,
        ]);
        $this->campaign->gameMasters()->attach($gm->id, ['sort_order' => $this->campaign->gameMasters()->count() + 1]);
    }

    /**
     * Fire the event the way the application does and return the dispatch for the given key.
     */
    private function fire(string $key): void
    {
        $sessions = [['game_table_id' => $this->table->id, 'waiting' => true]];
        $request = $this->request();

        match ($key) {
            'session_scheduled' => event(new RosterMemberEnrolledInSession((string) Str::uuid(), $this->table->id, $this->mary->id, false)),
            'join_request_received' => event(new CampaignJoinRequested($request->id, $this->campaign->id, $this->mary->id)),
            'join_request_accepted' => event(new CampaignJoinRequestAccepted($request->id, $this->campaign->id, $this->mary->id, $sessions)),
            'join_request_rejected' => event(new CampaignJoinRequestRejected($request->id, $this->campaign->id, $this->mary->id, 'Completa')),
            'roster_added' => event(new CampaignRosterMemberAdded($this->campaign->id, $this->mary->id, $sessions)),
            'roster_removed' => event(new CampaignRosterMemberRemoved($this->campaign->id, $this->mary->id)),
            'player_left' => event(new CampaignRosterMemberLeft($this->campaign->id, $this->mary->id)),
            default => throw new InvalidArgumentException("Unknown notification key: {$key}"),
        };
    }

    /**
     * @return array<string, array{string, string, class-string}>
     */
    public static function emails(): array
    {
        return [
            'session scheduled' => ['session_scheduled', 'mary@example.com', CampaignSessionScheduledNotification::class],
            'join request received' => ['join_request_received', 'john@example.com', CampaignJoinRequestReceivedNotification::class],
            'join request accepted' => ['join_request_accepted', 'mary@example.com', CampaignJoinRequestAcceptedNotification::class],
            'join request rejected' => ['join_request_rejected', 'mary@example.com', CampaignJoinRequestRejectedNotification::class],
            'roster added' => ['roster_added', 'mary@example.com', CampaignRosterAddedNotification::class],
            'roster removed' => ['roster_removed', 'mary@example.com', CampaignRosterRemovedNotification::class],
            'player left' => ['player_left', 'john@example.com', CampaignPlayerLeftNotification::class],
        ];
    }

    #[DataProvider('emails')]
    public function test_each_event_sends_its_email_to_the_right_address(string $key, string $email, string $class): void
    {
        $this->fire($key);

        Notification::assertSentOnDemand($class, fn ($notification, array $channels, AnonymousNotifiable $notifiable): bool => $notifiable->routes['mail'] === $email);
        Notification::assertSentOnDemandTimes($class, 1);
    }

    #[DataProvider('emails')]
    public function test_each_toggle_silences_its_email(string $key, string $email, string $class): void
    {
        config()->set('modules.settings.game-tables', ['campaign_notifications' => [$key => false]]);

        $this->fire($key);

        Notification::assertNothingSent();
    }

    #[DataProvider('emails')]
    public function test_no_email_about_an_anonymized_player(string $key, string $email, string $class): void
    {
        $this->mary->forceFill(['anonymized_at' => now()])->save();

        $this->fire($key);

        Notification::assertNothingSent();
    }

    #[DataProvider('emails')]
    public function test_no_email_to_an_anonymized_account(string $key, string $email, string $class): void
    {
        $this->john->forceFill(['anonymized_at' => now()])->save();

        $this->fire($key);

        if ($email === 'john@example.com') {
            Notification::assertNothingSent();
        } else {
            Notification::assertSentOnDemandTimes($class, 1);
        }
    }

    public function test_the_accepted_email_carries_titles_dates_and_waiting(): void
    {
        $this->fire('join_request_accepted');

        Notification::assertSentOnDemand(CampaignJoinRequestAcceptedNotification::class, function ($notification): bool {
            $mail = $notification->toMail(new AnonymousNotifiable);

            return in_array('«Sesión 2» — 15/11/2026 18:00 (en lista de espera, con prioridad por ser de la plantilla)', $mail->introLines, true)
                && $mail->actionUrl === route('campaigns.show', 'campana-de-prueba');
        });
    }

    public function test_the_received_email_carries_the_player_and_message(): void
    {
        $this->fire('join_request_received');

        Notification::assertSentOnDemand(CampaignJoinRequestReceivedNotification::class, function ($notification): bool {
            $mail = $notification->toMail(new AnonymousNotifiable);

            return $mail->subject === 'Solicitud para unirse a Campaña de prueba'
                && in_array('<p>Mary Major quiere unirse a la campaña «Campaña de prueba».</p>', array_map('strval', $mail->introLines), true)
                && in_array('<p>Mensaje: «Me encantaría jugar»</p>', array_map('strval', $mail->introLines), true);
        });
    }

    public function test_the_direction_gets_one_email_per_address(): void
    {
        $this->gm($this->john);

        $this->fire('join_request_received');

        Notification::assertSentOnDemandTimes(CampaignJoinRequestReceivedNotification::class, 1);
    }

    public function test_every_director_with_notices_is_emailed(): void
    {
        $jane = UserModel::factory()->create(['name' => 'Jane Doe', 'email' => 'jane@example.com']);
        $this->gm($jane);

        $this->fire('player_left');

        Notification::assertSentOnDemandTimes(CampaignPlayerLeftNotification::class, 2);
    }

    public function test_a_gm_with_notify_by_email_off_gets_nothing(): void
    {
        $jane = UserModel::factory()->create(['name' => 'Jane Doe', 'email' => 'jane@example.com']);
        $this->gm($jane, notify: false);

        $this->fire('join_request_received');

        Notification::assertSentOnDemandTimes(CampaignJoinRequestReceivedNotification::class, 1);
        Notification::assertSentOnDemand(CampaignJoinRequestReceivedNotification::class, fn ($n, array $c, AnonymousNotifiable $notifiable): bool => $notifiable->routes['mail'] === 'john@example.com');
    }

    public function test_the_scheduled_email_marks_a_waiting_member(): void
    {
        event(new RosterMemberEnrolledInSession((string) Str::uuid(), $this->table->id, $this->mary->id, true));

        Notification::assertSentOnDemand(CampaignSessionScheduledNotification::class, function ($notification): bool {
            $mail = $notification->toMail(new AnonymousNotifiable);

            return $mail->subject === 'Nueva sesión de Campaña de prueba: 15/11/2026 18:00'
                && in_array('Se ha programado una nueva sesión de la campaña «Campaña de prueba». Está completa: estás en la lista de espera, con prioridad por ser de la plantilla.', $mail->introLines, true);
        });
    }

    public function test_a_session_outside_a_campaign_sends_nothing(): void
    {
        $this->table->forceFill(['campaign_id' => null])->save();

        event(new RosterMemberEnrolledInSession((string) Str::uuid(), $this->table->id, $this->mary->id, false));

        Notification::assertNothingSent();
    }
}
