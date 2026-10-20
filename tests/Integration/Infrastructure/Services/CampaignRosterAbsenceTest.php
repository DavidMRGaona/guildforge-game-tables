<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Integration\Infrastructure\Services;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Modules\GameTables\Application\Services\CampaignRosterSessionSyncInterface;
use Modules\GameTables\Domain\Enums\GameMasterRole;
use Modules\GameTables\Domain\Exceptions\RosterActionRefusedException;
use Modules\GameTables\Domain\Repositories\CampaignRosterRepositoryInterface;
use Modules\GameTables\Domain\ValueObjects\CampaignId;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\CampaignModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameMasterModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameSystemModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameTableModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\ParticipantModel;
use Modules\GameTables\Notifications\CancellationConfirmation;
use Modules\GameTables\Notifications\OrganizationCancellationNotification;
use Modules\GameTables\Notifications\ParticipantCancelledNotification;
use Modules\GameTables\Notifications\ParticipantRegisteredNotification;
use Modules\GameTables\Notifications\RegistrationConfirmation;
use Tests\Support\Modules\ModuleTestCase;

/**
 * The direction marks a member absent from one session, or clears it, without touching the
 * roster. Real services and listeners; only the mail is faked.
 */
final class CampaignRosterAbsenceTest extends ModuleTestCase
{
    protected ?string $moduleName = 'game-tables';

    protected bool $autoEnableModule = true;

    private CampaignModel $campaign;

    private CampaignId $campaignId;

    private GameSystemModel $system;

    private UserModel $mary;

    private UserModel $john;

    private UserModel $jane;

    private GameTableModel $session;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        config()->set('modules.settings.game-tables', []);
        $this->travelTo(CarbonImmutable::parse('2026-11-02 10:00:00'));

        $this->mary = UserModel::factory()->create(['name' => 'Mary Major', 'email' => 'mary@example.com']);
        $this->john = UserModel::factory()->create(['name' => 'John Doe', 'email' => 'john@example.com']);
        $this->jane = UserModel::factory()->create(['name' => 'Jane Doe', 'email' => 'jane@example.com']);

        $this->system = GameSystemModel::create(['id' => (string) Str::uuid(), 'name' => 'Sistema', 'slug' => 'sistema', 'is_active' => true]);
        $this->campaign = $this->campaign('La cripta olvidada');
        $this->campaignId = new CampaignId($this->campaign->id);
        $this->session = $this->campaignSession($this->campaign, 'Sesión 1', CarbonImmutable::now()->addWeek());

        $director = GameMasterModel::create([
            'id' => (string) Str::uuid(),
            'user_id' => $this->mary->id,
            'first_name' => 'Mary',
            'last_name' => 'Major',
            'email' => 'mary@example.com',
            'role' => GameMasterRole::Main,
            'notify_by_email' => true,
            'is_name_public' => true,
        ]);
        $this->session->gameMasters()->attach($director->id, ['source' => 'local', 'excluded' => false, 'sort_order' => 1]);

        foreach ([$this->john, $this->jane] as $member) {
            $this->roster()->add($this->campaignId, $member->id, CarbonImmutable::now());
            $this->rosterRow($this->session, $member);
        }
    }

    public function test_marking_an_absence_notifies_the_player_and_keeps_the_roster(): void
    {
        $this->sync()->markAbsence($this->campaign->id, $this->session->id, $this->jane->id);

        $row = $this->rowOf($this->session, $this->jane);
        $this->assertSame('cancelled', $row->status->value);
        $this->assertSame('session_absence', $row->getRawOriginal('cancellation_reason'));
        $this->assertTrue($this->roster()->contains($this->campaignId, $this->jane->id));
        $this->assertMailedTo(OrganizationCancellationNotification::class, 'jane@example.com');
        Notification::assertSentTimes(CancellationConfirmation::class, 0);
        Notification::assertSentTimes(ParticipantCancelledNotification::class, 0);
    }

    public function test_clearing_an_absence_re_enrols_and_confirms_by_email(): void
    {
        $this->sync()->markAbsence($this->campaign->id, $this->session->id, $this->jane->id);

        $this->sync()->clearAbsence($this->campaign->id, $this->session->id, $this->jane->id);

        $row = $this->rowOf($this->session, $this->jane);
        $this->assertSame('confirmed', $row->status->value);
        $this->assertNull($row->getRawOriginal('cancellation_reason'));
        $this->assertTrue($row->from_roster);
        $this->assertMailedTo(RegistrationConfirmation::class, 'jane@example.com');
        Notification::assertSentTimes(ParticipantRegisteredNotification::class, 0);
    }

    public function test_marking_after_the_start_is_refused(): void
    {
        $this->travelTo(CarbonImmutable::now()->addWeek()->addHour());

        $this->assertRefused(
            'session_started',
            'La sesión ya ha empezado.',
            fn () => $this->sync()->markAbsence($this->campaign->id, $this->session->id, $this->jane->id),
        );

        $this->assertSame('confirmed', $this->rowOf($this->session, $this->jane)->status->value);
        Notification::assertNothingSent();
    }

    public function test_a_session_of_another_campaign_is_refused(): void
    {
        $other = $this->campaignSession($this->campaign('La torre del mago'), 'Sesión 1', CarbonImmutable::now()->addWeek());
        $this->rosterRow($other, $this->jane);

        foreach ([$other->id, (string) Str::uuid(), 'not-a-uuid'] as $gameTableId) {
            $this->assertRefused(
                'session_not_in_campaign',
                'Esa sesión no es de esta campaña.',
                fn () => $this->sync()->markAbsence($this->campaign->id, $gameTableId, $this->jane->id),
            );
            $this->assertRefused(
                'session_not_in_campaign',
                'Esa sesión no es de esta campaña.',
                fn () => $this->sync()->clearAbsence($this->campaign->id, $gameTableId, $this->jane->id),
            );
        }

        $this->assertSame('confirmed', $this->rowOf($other, $this->jane)->status->value);
        Notification::assertNothingSent();
    }

    public function test_someone_outside_the_roster_is_refused(): void
    {
        $this->roster()->remove($this->campaignId, $this->jane->id);

        $this->assertRefused(
            'not_member',
            'Esa persona no forma parte de la plantilla.',
            fn () => $this->sync()->markAbsence($this->campaign->id, $this->session->id, $this->jane->id),
        );
        $this->assertRefused(
            'not_member',
            'Esa persona no forma parte de la plantilla.',
            fn () => $this->sync()->clearAbsence($this->campaign->id, $this->session->id, $this->jane->id),
        );
        $this->assertSame('confirmed', $this->rowOf($this->session, $this->jane)->status->value);
    }

    public function test_marking_twice_is_refused(): void
    {
        $this->sync()->markAbsence($this->campaign->id, $this->session->id, $this->jane->id);

        $this->assertRefused(
            'nothing_to_change',
            'No hay nada que cambiar en esa sesión.',
            fn () => $this->sync()->markAbsence($this->campaign->id, $this->session->id, $this->jane->id),
        );
        Notification::assertSentTimes(OrganizationCancellationNotification::class, 1);
    }

    public function test_clearing_without_absence_is_refused(): void
    {
        $this->assertRefused(
            'nothing_to_change',
            'No hay nada que cambiar en esa sesión.',
            fn () => $this->sync()->clearAbsence($this->campaign->id, $this->session->id, $this->jane->id),
        );

        $this->assertSame('confirmed', $this->rowOf($this->session, $this->jane)->status->value);
        Notification::assertNothingSent();
    }

    private function sync(): CampaignRosterSessionSyncInterface
    {
        return $this->app->make(CampaignRosterSessionSyncInterface::class);
    }

    private function roster(): CampaignRosterRepositoryInterface
    {
        return $this->app->make(CampaignRosterRepositoryInterface::class);
    }

    private function campaign(string $title): CampaignModel
    {
        return CampaignModel::create([
            'id' => (string) Str::uuid(),
            'game_system_id' => $this->system->id,
            'created_by' => $this->mary->id,
            'title' => $title,
            'slug' => Str::slug($title),
            'status' => 'active',
            'frontend_creation_status' => 'approved',
            'is_published' => true,
            'accepts_new_players' => true,
            'roster_access' => 'anyone',
        ]);
    }

    private function campaignSession(CampaignModel $campaign, string $title, CarbonImmutable $startsAt): GameTableModel
    {
        return GameTableModel::create([
            'id' => (string) Str::uuid(),
            'game_system_id' => $this->system->id,
            'campaign_id' => $campaign->id,
            'created_by' => $this->mary->id,
            'title' => $title,
            'slug' => Str::slug($campaign->title.' '.$title),
            'starts_at' => $startsAt,
            'duration_minutes' => 240,
            'table_type' => 'campaign_session',
            'table_format' => 'in_person',
            'status' => 'scheduled',
            'min_players' => 1,
            'max_players' => 5,
            'max_spectators' => 0,
            'language' => 'es',
            'is_published' => true,
            'auto_confirm' => false,
        ]);
    }

    private function rosterRow(GameTableModel $session, UserModel $user): ParticipantModel
    {
        return ParticipantModel::create([
            'id' => (string) Str::uuid(),
            'game_table_id' => $session->id,
            'user_id' => $user->id,
            'role' => 'player',
            'status' => 'confirmed',
            'from_roster' => true,
            'confirmed_at' => now(),
        ]);
    }

    private function rowOf(GameTableModel $session, UserModel $user): ParticipantModel
    {
        return ParticipantModel::query()
            ->where('game_table_id', $session->id)
            ->where('user_id', $user->id)
            ->sole();
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

    private function assertRefused(string $reason, string $message, Closure $action): void
    {
        try {
            $action();
            $this->fail("Expected the roster action to be refused with {$reason}");
        } catch (RosterActionRefusedException $e) {
            $this->assertSame($reason, $e->reason);
            $this->assertSame($message, $e->getMessage());
        }
    }
}
