<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Integration\Infrastructure\Services;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Modules\GameTables\Application\DTOs\RegisterParticipantDTO;
use Modules\GameTables\Application\Services\CampaignRosterServiceInterface;
use Modules\GameTables\Application\Services\CampaignRosterSessionSyncInterface;
use Modules\GameTables\Application\Services\RegistrationServiceInterface;
use Modules\GameTables\Domain\Enums\ParticipantRole;
use Modules\GameTables\Domain\Enums\RosterAccess;
use Modules\GameTables\Domain\Events\CampaignRosterMemberAdded;
use Modules\GameTables\Domain\Events\CampaignRosterMemberLeft;
use Modules\GameTables\Domain\Events\CampaignRosterMemberRemoved;
use Modules\GameTables\Domain\Events\ParticipantCancelled;
use Modules\GameTables\Domain\Events\ParticipantRegistered;
use Modules\GameTables\Domain\Events\RosterMemberEnrolledInSession;
use Modules\GameTables\Domain\Exceptions\RosterActionRefusedException;
use Modules\GameTables\Domain\Repositories\CampaignRosterRepositoryInterface;
use Modules\GameTables\Domain\ValueObjects\CampaignId;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\CampaignModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameSystemModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameTableModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\ParticipantModel;
use RuntimeException;
use Tests\Support\Modules\ModuleTestCase;

/**
 * Leaving the roster or being removed from it, rejoining afterwards, and the three settings the
 * direction may change on a published campaign. Real repositories, lock and session sync.
 */
final class CampaignRosterExitTest extends ModuleTestCase
{
    protected ?string $moduleName = 'game-tables';

    protected bool $autoEnableModule = true;

    private const array EVENTS = [
        CampaignRosterMemberAdded::class,
        CampaignRosterMemberLeft::class,
        CampaignRosterMemberRemoved::class,
        ParticipantCancelled::class,
        ParticipantRegistered::class,
        RosterMemberEnrolledInSession::class,
    ];

    private CampaignModel $campaign;

    private CampaignId $campaignId;

    private GameSystemModel $system;

    private UserModel $mary;

    private UserModel $john;

    private UserModel $jane;

    private UserModel $richard;

    /** Started yesterday */
    private GameTableModel $s0;

    /** Published, next week */
    private GameTableModel $s1;

    /** Published, in two weeks */
    private GameTableModel $s2;

    /** Unpublished draft */
    private GameTableModel $s3;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        Event::fake(self::EVENTS);
        config()->set('modules.settings.game-tables', []);
        $this->travelTo(CarbonImmutable::parse('2026-11-02 10:00:00'));

        $this->mary = UserModel::factory()->create(['name' => 'Mary Major', 'email' => 'mary@example.com']);
        $this->john = UserModel::factory()->create(['name' => 'John Doe', 'email' => 'john@example.com']);
        $this->jane = UserModel::factory()->create(['name' => 'Jane Doe', 'email' => 'jane@example.com']);
        $this->richard = UserModel::factory()->create(['name' => 'Richard Roe', 'email' => 'richard@example.com']);

        $this->system = GameSystemModel::create(['id' => (string) Str::uuid(), 'name' => 'Sistema', 'slug' => 'sistema', 'is_active' => true]);
        $this->campaign = CampaignModel::create([
            'id' => (string) Str::uuid(),
            'game_system_id' => $this->system->id,
            'created_by' => $this->mary->id,
            'title' => 'La cripta olvidada',
            'slug' => 'la-cripta-olvidada',
            'status' => 'active',
            'frontend_creation_status' => 'approved',
            'is_published' => true,
            'accepts_new_players' => true,
            'max_players' => 5,
            'roster_access' => 'anyone',
        ]);
        $this->campaignId = new CampaignId($this->campaign->id);

        // Created before anyone is in the roster, so publication enrols nobody yet
        $this->s0 = $this->campaignSession('Sesión 0', CarbonImmutable::now()->subDay(), ['status' => 'in_progress']);
        $this->s1 = $this->campaignSession('Sesión 1', CarbonImmutable::now()->addWeek());
        $this->s2 = $this->campaignSession('Sesión 2', CarbonImmutable::now()->addWeeks(2));
        $this->s3 = $this->campaignSession('Sesión 3', CarbonImmutable::now()->addWeeks(3), ['status' => 'draft', 'is_published' => false]);

        foreach ([$this->john, $this->jane] as $member) {
            $this->roster()->add($this->campaignId, $member->id, CarbonImmutable::now());
            $this->rosterRow($this->s0, $member);
            $this->rosterRow($this->s3, $member);
        }
        foreach ([$this->s1, $this->s2] as $session) {
            $this->registrations()->enrollRoster($session->id);
        }
    }

    public function test_leaving_cancels_the_upcoming_roster_rows_only(): void
    {
        $this->service()->leave($this->campaign->id, $this->jane->id);

        $this->assertFalse($this->roster()->contains($this->campaignId, $this->jane->id));
        foreach ([$this->s1, $this->s2, $this->s3] as $session) {
            $row = $this->rowOf($session, $this->jane);
            $this->assertSame('cancelled', $row->status->value, $session->title);
            $this->assertSame('left_roster', $row->getRawOriginal('cancellation_reason'), $session->title);
        }
        $started = $this->rowOf($this->s0, $this->jane);
        $this->assertSame('confirmed', $started->status->value);
        $this->assertNull($started->getRawOriginal('cancellation_reason'));
        // The other member keeps every seat
        foreach ([$this->s0, $this->s1, $this->s2, $this->s3] as $session) {
            $this->assertSame('confirmed', $this->rowOf($session, $this->john)->status->value, $session->title);
        }
        Event::assertDispatched(
            CampaignRosterMemberLeft::class,
            fn (CampaignRosterMemberLeft $event): bool => $event->campaignId === $this->campaign->id
                && $event->userId === $this->jane->id,
        );
        Event::assertNotDispatched(CampaignRosterMemberRemoved::class);
        Event::assertNotDispatched(ParticipantCancelled::class);
    }

    public function test_a_removed_member_gets_the_removal_event(): void
    {
        $this->service()->removeMember($this->campaign->id, $this->john->id, $this->mary->id);

        $this->assertFalse($this->roster()->contains($this->campaignId, $this->john->id));
        $this->assertSame('left_roster', $this->rowOf($this->s1, $this->john)->getRawOriginal('cancellation_reason'));
        Event::assertDispatched(
            CampaignRosterMemberRemoved::class,
            fn (CampaignRosterMemberRemoved $event): bool => $event->campaignId === $this->campaign->id
                && $event->userId === $this->john->id,
        );
        Event::assertNotDispatched(CampaignRosterMemberLeft::class);
        Event::assertNotDispatched(ParticipantCancelled::class);
    }

    public function test_removing_someone_outside_the_roster_is_refused(): void
    {
        $this->assertRefused(
            'not_member',
            'Esa persona no forma parte de la plantilla.',
            fn () => $this->service()->removeMember($this->campaign->id, $this->richard->id, $this->mary->id),
        );
        $this->assertRefused(
            'not_member',
            'Esa persona no forma parte de la plantilla.',
            fn () => $this->service()->leave($this->campaign->id, $this->richard->id),
        );
        Event::assertNotDispatched(CampaignRosterMemberRemoved::class);
        Event::assertNotDispatched(CampaignRosterMemberLeft::class);
    }

    public function test_rejoining_restores_the_rows_lost_by_leaving(): void
    {
        $s1Row = $this->rowOf($this->s1, $this->jane);
        $s2Row = $this->rowOf($this->s2, $this->jane);
        $this->service()->leave($this->campaign->id, $this->jane->id);

        $this->service()->addUser($this->campaign->id, $this->jane->id, $this->mary->id);

        $this->assertTrue($this->roster()->contains($this->campaignId, $this->jane->id));
        foreach ([$this->s1, $this->s2] as $session) {
            $row = $this->rowOf($session, $this->jane);
            $this->assertSame('confirmed', $row->status->value, $session->title);
            $this->assertNull($row->getRawOriginal('cancellation_reason'), $session->title);
        }
        $this->assertSame($s1Row->id, $this->rowOf($this->s1, $this->jane)->id);
        $this->assertSame($s2Row->id, $this->rowOf($this->s2, $this->jane)->id);
        Event::assertDispatched(
            CampaignRosterMemberAdded::class,
            fn (CampaignRosterMemberAdded $event): bool => $event->userId === $this->jane->id
                && $event->sessions === [
                    ['game_table_id' => $this->s1->id, 'waiting' => false],
                    ['game_table_id' => $this->s2->id, 'waiting' => false],
                ],
        );
    }

    public function test_an_absence_survives_leaving_and_rejoining(): void
    {
        $this->sync()->markAbsence($this->campaign->id, $this->s1->id, $this->jane->id);
        $this->service()->leave($this->campaign->id, $this->jane->id);

        $this->service()->addUser($this->campaign->id, $this->jane->id, $this->mary->id);

        $absence = $this->rowOf($this->s1, $this->jane);
        $this->assertSame('cancelled', $absence->status->value);
        $this->assertSame('session_absence', $absence->getRawOriginal('cancellation_reason'));
        $this->assertSame('confirmed', $this->rowOf($this->s2, $this->jane)->status->value);
        Event::assertDispatched(
            CampaignRosterMemberAdded::class,
            fn (CampaignRosterMemberAdded $event): bool => $event->userId === $this->jane->id
                && $event->sessions === [['game_table_id' => $this->s2->id, 'waiting' => false]],
        );
    }

    public function test_joining_skips_sessions_whose_registrations_closed(): void
    {
        $this->s1->update(['registration_closes_at' => CarbonImmutable::now()->subHour()]);

        $this->service()->addUser($this->campaign->id, $this->richard->id, $this->mary->id);

        $this->assertNull($this->findRow($this->s1, $this->richard));
        $this->assertSame('confirmed', $this->rowOf($this->s2, $this->richard)->status->value);
        Event::assertDispatched(
            CampaignRosterMemberAdded::class,
            fn (CampaignRosterMemberAdded $event): bool => $event->userId === $this->richard->id
                && $event->sessions === [['game_table_id' => $this->s2->id, 'waiting' => false]],
        );
    }

    public function test_a_finished_campaign_can_still_remove(): void
    {
        $this->campaign->update(['status' => 'completed']);

        $this->service()->removeMember($this->campaign->id, $this->john->id, $this->mary->id);

        $this->assertFalse($this->roster()->contains($this->campaignId, $this->john->id));
        Event::assertDispatched(
            CampaignRosterMemberRemoved::class,
            fn (CampaignRosterMemberRemoved $event): bool => $event->userId === $this->john->id,
        );
    }

    public function test_a_failing_session_sync_still_removes_and_announces(): void
    {
        Exceptions::fake();
        $sync = $this->createStub(CampaignRosterSessionSyncInterface::class);
        $sync->method('onMemberLeft')->willThrowException(new RuntimeException('Sync failed'));
        $this->app->instance(CampaignRosterSessionSyncInterface::class, $sync);

        $this->service()->removeMember($this->campaign->id, $this->john->id, $this->mary->id);
        $this->service()->leave($this->campaign->id, $this->jane->id);

        $this->assertSame(0, $this->roster()->count($this->campaignId));
        Event::assertDispatched(CampaignRosterMemberRemoved::class);
        Event::assertDispatched(CampaignRosterMemberLeft::class);
        Exceptions::assertReported(RuntimeException::class);
    }

    // A row left by leaving the roster (or an absence) is reused by any later registration: only
    // the roster path marks it as the roster's

    public function test_an_ex_member_registering_loosely_gets_no_roster_mark(): void
    {
        $this->service()->leave($this->campaign->id, $this->jane->id);

        $registration = $this->registrations()->register(new RegisterParticipantDTO($this->s1->id, $this->jane->id));

        $this->assertFalse($registration->fromRoster);
        $row = $this->rowOf($this->s1, $this->jane);
        $this->assertSame('pending', $row->status->value);
        $this->assertFalse($row->from_roster);
        $this->assertNull($row->getRawOriginal('cancellation_reason'));
    }

    public function test_an_ex_member_registering_loosely_gets_no_waiting_list_priority(): void
    {
        config()->set('modules.settings.game-tables', ['registration' => ['waiting_list_enabled' => true]]);
        $this->service()->leave($this->campaign->id, $this->jane->id);
        $this->s1->update(['max_players' => 1]);

        // An outsider waits first; the ex-member comes after them and must stay after them
        $this->registrations()->register(new RegisterParticipantDTO($this->s1->id, $this->richard->id));
        $this->registrations()->register(new RegisterParticipantDTO($this->s1->id, $this->jane->id));
        $this->assertSame('waiting_list', $this->rowOf($this->s1, $this->jane)->status->value);
        $this->assertFalse($this->rowOf($this->s1, $this->jane)->from_roster);

        $this->registrations()->cancelByUser($this->s1->id, $this->john->id);

        // The outsider takes the seat, as pending (the table confirms by hand); the ex-member keeps waiting
        $this->assertSame('pending', $this->rowOf($this->s1, $this->richard)->status->value);
        $this->assertSame('waiting_list', $this->rowOf($this->s1, $this->jane)->status->value);
    }

    public function test_staff_registering_an_ex_member_add_no_roster_mark(): void
    {
        $this->service()->leave($this->campaign->id, $this->jane->id);

        $registration = $this->registrations()->registerByAdmin(new RegisterParticipantDTO($this->s1->id, $this->jane->id));

        $this->assertFalse($registration->fromRoster);
        $this->assertFalse($this->rowOf($this->s1, $this->jane)->from_roster);
    }

    public function test_a_member_registering_as_spectator_over_an_absence_gets_no_roster_mark(): void
    {
        $this->s1->update(['max_spectators' => 2]);
        $this->sync()->markAbsence($this->campaign->id, $this->s1->id, $this->john->id);

        $registration = $this->registrations()->register(new RegisterParticipantDTO($this->s1->id, $this->john->id, ParticipantRole::Spectator));

        $this->assertFalse($registration->fromRoster);
        $row = $this->rowOf($this->s1, $this->john);
        $this->assertSame('spectator', $row->role->value);
        $this->assertFalse($row->from_roster);
    }

    public function test_one_failing_session_does_not_stop_the_others_when_joining(): void
    {
        Exceptions::fake();
        $this->registrationsFailingOn($this->s1);

        $this->service()->addUser($this->campaign->id, $this->richard->id, $this->mary->id);

        $this->assertNull($this->findRow($this->s1, $this->richard));
        $this->assertSame('confirmed', $this->rowOf($this->s2, $this->richard)->status->value);
        // The welcome email lists the sessions actually enrolled
        Event::assertDispatched(
            CampaignRosterMemberAdded::class,
            fn (CampaignRosterMemberAdded $event): bool => $event->userId === $this->richard->id
                && $event->sessions === [['game_table_id' => $this->s2->id, 'waiting' => false]],
        );
        Exceptions::assertReported(fn (RuntimeException $e): bool => $e->getMessage() === 'Session sync failed');
    }

    public function test_one_failing_session_does_not_keep_the_leaver_in_the_others(): void
    {
        Exceptions::fake();
        $this->registrationsFailingOn($this->s1);

        $this->service()->leave($this->campaign->id, $this->jane->id);

        $this->assertSame('confirmed', $this->rowOf($this->s1, $this->jane)->status->value);
        foreach ([$this->s2, $this->s3] as $session) {
            $row = $this->rowOf($session, $this->jane);
            $this->assertSame('cancelled', $row->status->value, $session->title);
            $this->assertSame('left_roster', $row->getRawOriginal('cancellation_reason'), $session->title);
        }
        Event::assertDispatched(CampaignRosterMemberLeft::class);
        Exceptions::assertReported(fn (RuntimeException $e): bool => $e->getMessage() === 'Session sync failed');
    }

    public function test_settings_change_without_review(): void
    {
        $this->service()->updateSettings($this->campaign->id, false, RosterAccess::MembersOnly, 4);

        $stored = $this->campaign->refresh();
        $this->assertFalse($stored->accepts_new_players);
        $this->assertSame('members_only', $stored->getRawOriginal('roster_access'));
        $this->assertSame(4, $stored->max_players);
        $this->assertTrue($stored->is_published);
        $this->assertSame('approved', $stored->getRawOriginal('frontend_creation_status'));
    }

    public function test_max_below_the_roster_is_refused(): void
    {
        $this->assertRefused(
            'max_below_roster',
            'El máximo de jugadores no puede ser menor que la plantilla actual (2).',
            fn () => $this->service()->updateSettings($this->campaign->id, true, RosterAccess::Anyone, 1),
        );

        $this->assertSame(5, $this->campaign->refresh()->max_players);
    }

    public function test_a_finished_campaign_keeps_its_settings(): void
    {
        $this->campaign->update(['status' => 'cancelled']);

        $this->assertRefused(
            'finished',
            'La campaña ha terminado: su plantilla ya no cambia.',
            fn () => $this->service()->updateSettings($this->campaign->id, false, RosterAccess::Anyone, null),
        );

        $this->assertTrue($this->campaign->refresh()->accepts_new_players);
    }

    private function service(): CampaignRosterServiceInterface
    {
        return $this->app->make(CampaignRosterServiceInterface::class);
    }

    private function sync(): CampaignRosterSessionSyncInterface
    {
        return $this->app->make(CampaignRosterSessionSyncInterface::class);
    }

    private function registrations(): RegistrationServiceInterface
    {
        return $this->app->make(RegistrationServiceInterface::class);
    }

    /**
     * The real registration service, except that every roster call on one session throws.
     */
    private function registrationsFailingOn(GameTableModel $failing): void
    {
        $real = $this->registrations();
        $double = $this->createStub(RegistrationServiceInterface::class);
        $double->method('enrollRoster')->willReturnCallback(
            fn (string $gameTableId, ?string $onlyUserId = null): array => $gameTableId === $failing->id
                ? throw new RuntimeException('Session sync failed')
                : $real->enrollRoster($gameTableId, $onlyUserId),
        );
        $double->method('withdrawRosterMember')->willReturnCallback(
            fn (string $gameTableId, string $userId): bool => $gameTableId === $failing->id
                ? throw new RuntimeException('Session sync failed')
                : $real->withdrawRosterMember($gameTableId, $userId),
        );
        $this->app->instance(RegistrationServiceInterface::class, $double);
    }

    private function roster(): CampaignRosterRepositoryInterface
    {
        return $this->app->make(CampaignRosterRepositoryInterface::class);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function campaignSession(string $title, CarbonImmutable $startsAt, array $attributes = []): GameTableModel
    {
        return GameTableModel::create([
            'id' => (string) Str::uuid(),
            'game_system_id' => $this->system->id,
            'campaign_id' => $this->campaign->id,
            'created_by' => $this->mary->id,
            'title' => $title,
            'slug' => Str::slug($title),
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
            ...$attributes,
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

    private function findRow(GameTableModel $session, UserModel $user): ?ParticipantModel
    {
        return ParticipantModel::query()
            ->where('game_table_id', $session->id)
            ->where('user_id', $user->id)
            ->first();
    }

    private function rowOf(GameTableModel $session, UserModel $user): ParticipantModel
    {
        return ParticipantModel::query()
            ->where('game_table_id', $session->id)
            ->where('user_id', $user->id)
            ->sole();
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
