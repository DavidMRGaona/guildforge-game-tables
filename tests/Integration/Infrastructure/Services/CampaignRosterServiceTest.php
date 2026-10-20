<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Integration\Infrastructure\Services;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Modules\GameTables\Application\Services\CampaignRosterServiceInterface;
use Modules\GameTables\Application\Services\CampaignRosterSessionSyncInterface;
use Modules\GameTables\Application\Services\MembershipCheckerInterface;
use Modules\GameTables\Domain\Entities\CampaignJoinRequest;
use Modules\GameTables\Domain\Enums\JoinRequestStatus;
use Modules\GameTables\Domain\Events\CampaignJoinRequestAccepted;
use Modules\GameTables\Domain\Events\CampaignJoinRequested;
use Modules\GameTables\Domain\Events\CampaignJoinRequestRejected;
use Modules\GameTables\Domain\Events\CampaignRosterMemberAdded;
use Modules\GameTables\Domain\Events\RosterMemberEnrolledInSession;
use Modules\GameTables\Domain\Exceptions\CampaignNotFoundException;
use Modules\GameTables\Domain\Exceptions\RosterActionRefusedException;
use Modules\GameTables\Domain\Repositories\CampaignJoinRequestRepositoryInterface;
use Modules\GameTables\Domain\Repositories\CampaignRosterRepositoryInterface;
use Modules\GameTables\Domain\ValueObjects\CampaignId;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\CampaignModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameSystemModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameTableModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\ParticipantModel;
use RuntimeException;
use Tests\Support\Modules\ModuleTestCase;

/**
 * Requests to join a campaign, their decision and the direction adding players directly. Real
 * repositories, lock and session sync.
 */
final class CampaignRosterServiceTest extends ModuleTestCase
{
    protected ?string $moduleName = 'game-tables';

    protected bool $autoEnableModule = true;

    private const array EVENTS = [
        CampaignJoinRequested::class,
        CampaignJoinRequestAccepted::class,
        CampaignJoinRequestRejected::class,
        CampaignRosterMemberAdded::class,
        RosterMemberEnrolledInSession::class,
    ];

    private CampaignModel $campaign;

    private CampaignId $campaignId;

    private GameSystemModel $system;

    private UserModel $mary;

    private UserModel $john;

    private UserModel $jane;

    private UserModel $richard;

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
            'status' => 'recruiting',
            'frontend_creation_status' => 'approved',
            'accepts_new_players' => true,
            'max_players' => 3,
            'roster_access' => 'anyone',
        ]);
        $this->campaignId = new CampaignId($this->campaign->id);
    }

    private function service(): CampaignRosterServiceInterface
    {
        return $this->app->make(CampaignRosterServiceInterface::class);
    }

    private function roster(): CampaignRosterRepositoryInterface
    {
        return $this->app->make(CampaignRosterRepositoryInterface::class);
    }

    private function requests(): CampaignJoinRequestRepositoryInterface
    {
        return $this->app->make(CampaignJoinRequestRepositoryInterface::class);
    }

    private function addToRoster(UserModel ...$members): void
    {
        foreach ($members as $member) {
            $this->roster()->add($this->campaignId, $member->id, CarbonImmutable::now());
        }
    }

    private function pendingOf(UserModel $user): CampaignJoinRequest
    {
        $request = $this->requests()->findPending($this->campaignId, $user->id);
        $this->assertNotNull($request);

        return $request;
    }

    private function campaignSession(string $title, CarbonImmutable $startsAt): GameTableModel
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
        ]);
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

    private function assertRequestRefused(string $reason, string $message, UserModel $user): void
    {
        $this->assertRefused($reason, $message, fn () => $this->service()->requestToJoin($this->campaign->id, $user->id, 'Hola'));
        Event::assertNotDispatched(CampaignJoinRequested::class);
    }

    public function test_a_request_is_stored_pending_with_its_message(): void
    {
        $this->service()->requestToJoin($this->campaign->id, $this->john->id, '  Me encantaría jugar  ');

        $request = $this->pendingOf($this->john);
        $this->assertSame(JoinRequestStatus::Pending, $request->status);
        $this->assertSame('Me encantaría jugar', $request->message);
        $this->assertFalse($this->roster()->contains($this->campaignId, $this->john->id));
        Event::assertDispatched(
            CampaignJoinRequested::class,
            fn (CampaignJoinRequested $event): bool => $event->requestId === $request->id->value
                && $event->campaignId === $this->campaign->id
                && $event->userId === $this->john->id,
        );
    }

    public function test_a_blank_message_is_stored_as_null(): void
    {
        $this->service()->requestToJoin($this->campaign->id, $this->john->id, '   ');

        $this->assertNull($this->pendingOf($this->john)->message);
    }

    public function test_a_second_request_while_pending_is_refused(): void
    {
        $this->service()->requestToJoin($this->campaign->id, $this->john->id, null);

        $this->assertRefused(
            'request_pending',
            'Ya tienes una solicitud pendiente.',
            fn () => $this->service()->requestToJoin($this->campaign->id, $this->john->id, null),
        );
        Event::assertDispatchedTimes(CampaignJoinRequested::class, 1);
    }

    public function test_after_a_rejection_a_new_request_is_allowed(): void
    {
        $this->service()->requestToJoin($this->campaign->id, $this->john->id, null);
        $this->service()->rejectRequest($this->pendingOf($this->john)->id->value, $this->mary->id, null);

        $this->service()->requestToJoin($this->campaign->id, $this->john->id, 'Otra vez');

        $this->assertSame('Otra vez', $this->pendingOf($this->john)->message);
        Event::assertDispatchedTimes(CampaignJoinRequested::class, 2);
    }

    public function test_a_draft_campaign_is_unavailable(): void
    {
        $this->campaign->update(['frontend_creation_status' => 'draft', 'is_published' => false]);

        $this->assertRequestRefused('unavailable', 'Esta campaña no está publicada.', $this->john);
    }

    public function test_a_completed_campaign_is_finished(): void
    {
        $this->campaign->update(['status' => 'completed']);

        $this->assertRequestRefused('finished', 'La campaña ha terminado: su plantilla ya no cambia.', $this->john);
    }

    public function test_a_campaign_on_hold_is_not_accepting(): void
    {
        $this->campaign->update(['status' => 'on_hold']);

        $this->assertRequestRefused('not_accepting', 'Esta campaña no acepta jugadores nuevos.', $this->john);
    }

    public function test_a_campaign_closed_to_new_players_is_not_accepting(): void
    {
        $this->campaign->update(['accepts_new_players' => false]);

        $this->assertRequestRefused('not_accepting', 'Esta campaña no acepta jugadores nuevos.', $this->john);
    }

    public function test_a_full_roster_refuses_requests(): void
    {
        $this->campaign->update(['max_players' => 2]);
        $this->addToRoster($this->jane, $this->richard);

        $this->assertRequestRefused('roster_full', 'La plantilla está completa.', $this->john);
    }

    public function test_a_members_only_campaign_refuses_someone_who_is_not_a_socio(): void
    {
        $this->campaign->update(['roster_access' => 'members_only']);

        $this->assertRequestRefused('members_only', 'Esta campaña solo admite solicitudes de socios.', $this->john);
    }

    public function test_a_members_only_campaign_takes_requests_from_socios(): void
    {
        $this->campaign->update(['roster_access' => 'members_only']);
        $johnId = $this->john->id;
        $this->app->instance(MembershipCheckerInterface::class, new class($johnId) implements MembershipCheckerInterface {
            public function __construct(private readonly string $memberId) {}

            public function isActiveMember(string $userId): bool
            {
                return $userId === $this->memberId;
            }
        });

        $this->service()->requestToJoin($this->campaign->id, $this->john->id, null);

        $this->pendingOf($this->john);
    }

    public function test_the_director_cannot_ask_to_join(): void
    {
        $this->assertRequestRefused('is_director', 'Quien dirige la campaña no puede entrar en la plantilla como jugador.', $this->mary);
    }

    public function test_a_member_cannot_ask_to_join(): void
    {
        $this->addToRoster($this->john);

        $this->assertRequestRefused('already_member', 'Ya formas parte de la plantilla.', $this->john);
    }

    public function test_accepting_enrols_the_member_in_open_sessions_and_reports_them(): void
    {
        $first = $this->campaignSession('Sesión 1', CarbonImmutable::now()->addWeek());
        $second = $this->campaignSession('Sesión 2', CarbonImmutable::now()->addWeeks(2));
        $this->service()->requestToJoin($this->campaign->id, $this->john->id, null);
        $request = $this->pendingOf($this->john);

        $this->service()->acceptRequest($request->id->value, $this->mary->id);

        $this->assertTrue($this->roster()->contains($this->campaignId, $this->john->id));
        $stored = $this->requests()->find($request->id);
        $this->assertNotNull($stored);
        $this->assertSame(JoinRequestStatus::Accepted, $stored->status);
        $this->assertSame($this->mary->id, $stored->decidedBy);
        $rows = ParticipantModel::query()->where('user_id', $this->john->id)->get();
        $this->assertCount(2, $rows);
        foreach ($rows as $row) {
            $this->assertSame('confirmed', $row->status->value);
            $this->assertTrue($row->from_roster);
        }
        Event::assertDispatched(
            CampaignJoinRequestAccepted::class,
            fn (CampaignJoinRequestAccepted $event): bool => $event->requestId === $request->id->value
                && $event->campaignId === $this->campaign->id
                && $event->userId === $this->john->id
                && $event->sessions === [
                    ['game_table_id' => $first->id, 'waiting' => false],
                    ['game_table_id' => $second->id, 'waiting' => false],
                ],
        );
        // The joining email lists the sessions: no per-session notice
        Event::assertNotDispatched(RosterMemberEnrolledInSession::class);
    }

    public function test_accepting_when_full_is_refused_and_the_request_stays_pending(): void
    {
        $this->service()->requestToJoin($this->campaign->id, $this->john->id, null);
        $request = $this->pendingOf($this->john);
        $this->campaign->update(['max_players' => 2]);
        $this->addToRoster($this->jane, $this->richard);

        $this->assertRefused(
            'roster_full',
            'La plantilla está completa.',
            fn () => $this->service()->acceptRequest($request->id->value, $this->mary->id),
        );

        $this->assertSame(JoinRequestStatus::Pending, $this->pendingOf($this->john)->status);
        $this->assertFalse($this->roster()->contains($this->campaignId, $this->john->id));
        Event::assertNotDispatched(CampaignJoinRequestAccepted::class);
    }

    public function test_accepting_in_a_closed_campaign_is_allowed(): void
    {
        $this->service()->requestToJoin($this->campaign->id, $this->john->id, null);
        $request = $this->pendingOf($this->john);
        $this->campaign->update(['accepts_new_players' => false]);

        $this->service()->acceptRequest($request->id->value, $this->mary->id);

        $this->assertTrue($this->roster()->contains($this->campaignId, $this->john->id));
        $this->assertSame(JoinRequestStatus::Accepted, $this->requests()->find($request->id)?->status);
        Event::assertDispatched(
            CampaignJoinRequestAccepted::class,
            fn (CampaignJoinRequestAccepted $event): bool => $event->sessions === [],
        );
    }

    public function test_accepting_in_a_finished_campaign_is_refused(): void
    {
        $this->service()->requestToJoin($this->campaign->id, $this->john->id, null);
        $request = $this->pendingOf($this->john);
        $this->campaign->update(['status' => 'cancelled']);

        $this->assertRefused(
            'finished',
            'La campaña ha terminado: su plantilla ya no cambia.',
            fn () => $this->service()->acceptRequest($request->id->value, $this->mary->id),
        );
        $this->assertFalse($this->roster()->contains($this->campaignId, $this->john->id));
    }

    public function test_accepting_an_unknown_request_is_refused(): void
    {
        $this->assertRefused(
            'not_pending',
            'Esta solicitud ya no está pendiente.',
            fn () => $this->service()->acceptRequest((string) Str::uuid(), $this->mary->id),
        );
    }

    public function test_rejecting_keeps_the_reason(): void
    {
        $this->service()->requestToJoin($this->campaign->id, $this->john->id, null);
        $request = $this->pendingOf($this->john);

        $this->service()->rejectRequest($request->id->value, $this->mary->id, 'Ya no hay hueco en la mesa');

        $stored = $this->requests()->find($request->id);
        $this->assertNotNull($stored);
        $this->assertSame(JoinRequestStatus::Rejected, $stored->status);
        $this->assertSame('Ya no hay hueco en la mesa', $stored->decisionReason);
        $this->assertSame($this->mary->id, $stored->decidedBy);
        $this->assertFalse($this->roster()->contains($this->campaignId, $this->john->id));
        Event::assertDispatched(
            CampaignJoinRequestRejected::class,
            fn (CampaignJoinRequestRejected $event): bool => $event->requestId === $request->id->value
                && $event->campaignId === $this->campaign->id
                && $event->userId === $this->john->id
                && $event->reason === 'Ya no hay hueco en la mesa',
        );
    }

    public function test_rejecting_twice_is_refused(): void
    {
        $this->service()->requestToJoin($this->campaign->id, $this->john->id, null);
        $request = $this->pendingOf($this->john);
        $this->service()->rejectRequest($request->id->value, $this->mary->id, null);

        $this->assertRefused(
            'not_pending',
            'Esta solicitud ya no está pendiente.',
            fn () => $this->service()->rejectRequest($request->id->value, $this->mary->id, null),
        );
        Event::assertDispatchedTimes(CampaignJoinRequestRejected::class, 1);
    }

    public function test_withdrawing_twice_is_refused(): void
    {
        $this->service()->requestToJoin($this->campaign->id, $this->john->id, null);
        $request = $this->pendingOf($this->john);

        $this->service()->withdrawRequest($this->campaign->id, $this->john->id);

        $this->assertSame(JoinRequestStatus::Withdrawn, $this->requests()->find($request->id)?->status);
        $this->assertRefused(
            'not_pending',
            'Esta solicitud ya no está pendiente.',
            fn () => $this->service()->withdrawRequest($this->campaign->id, $this->john->id),
        );
        Event::assertNotDispatched(CampaignJoinRequestRejected::class);
        Event::assertNotDispatched(CampaignJoinRequestAccepted::class);
    }

    public function test_add_by_email_ignores_case_and_spaces(): void
    {
        $session = $this->campaignSession('Sesión 1', CarbonImmutable::now()->addWeek());

        $this->service()->addByEmail($this->campaign->id, ' John@Example.com ', $this->mary->id);

        $this->assertTrue($this->roster()->contains($this->campaignId, $this->john->id));
        Event::assertDispatched(
            CampaignRosterMemberAdded::class,
            fn (CampaignRosterMemberAdded $event): bool => $event->campaignId === $this->campaign->id
                && $event->userId === $this->john->id
                && $event->sessions === [['game_table_id' => $session->id, 'waiting' => false]],
        );
    }

    public function test_an_unknown_email_gets_the_generic_message(): void
    {
        $this->richard->forceFill(['anonymized_at' => CarbonImmutable::now()])->save();

        foreach (['nobody@example.com', 'richard@example.com'] as $email) {
            $this->assertRefused(
                'account_not_found',
                'No se ha podido añadir a esa cuenta. Comprueba el email.',
                fn () => $this->service()->addByEmail($this->campaign->id, $email, $this->mary->id),
            );
        }
        $this->assertSame(0, $this->roster()->count($this->campaignId));
        Event::assertNotDispatched(CampaignRosterMemberAdded::class);
    }

    public function test_adding_an_anonymized_or_unknown_account_is_refused(): void
    {
        $this->richard->forceFill(['anonymized_at' => CarbonImmutable::now()])->save();

        foreach ([$this->richard->id, (string) Str::uuid()] as $userId) {
            $this->assertRefused(
                'account_not_found',
                'No se ha podido añadir a esa cuenta. Comprueba el email.',
                fn () => $this->service()->addUser($this->campaign->id, $userId, $this->mary->id),
            );
        }
        $this->assertSame(0, DB::table('gametables_campaign_players')->where('campaign_id', $this->campaign->id)->count());
        Event::assertNotDispatched(CampaignRosterMemberAdded::class);
    }

    public function test_a_failing_session_sync_keeps_the_new_member_and_still_announces_it(): void
    {
        Exceptions::fake();
        $sync = $this->createStub(CampaignRosterSessionSyncInterface::class);
        $sync->method('onMemberJoined')->willThrowException(new RuntimeException('Sync failed'));
        $this->app->instance(CampaignRosterSessionSyncInterface::class, $sync);
        $this->service()->requestToJoin($this->campaign->id, $this->john->id, null);
        $request = $this->pendingOf($this->john);

        $this->service()->acceptRequest($request->id->value, $this->mary->id);
        $this->service()->addUser($this->campaign->id, $this->jane->id, $this->mary->id);

        $this->assertTrue($this->roster()->contains($this->campaignId, $this->john->id));
        $this->assertTrue($this->roster()->contains($this->campaignId, $this->jane->id));
        Event::assertDispatched(
            CampaignJoinRequestAccepted::class,
            fn (CampaignJoinRequestAccepted $event): bool => $event->userId === $this->john->id && $event->sessions === [],
        );
        Event::assertDispatched(
            CampaignRosterMemberAdded::class,
            fn (CampaignRosterMemberAdded $event): bool => $event->userId === $this->jane->id && $event->sessions === [],
        );
        Exceptions::assertReported(RuntimeException::class);
    }

    public function test_a_closed_campaign_says_so_before_saying_it_is_full(): void
    {
        $this->campaign->update(['max_players' => 2, 'accepts_new_players' => false]);
        $this->addToRoster($this->jane, $this->richard);

        $this->assertRequestRefused('not_accepting', 'Esta campaña no acepta jugadores nuevos.', $this->john);
    }

    public function test_a_member_with_a_pending_request_is_told_they_are_a_member(): void
    {
        $this->service()->requestToJoin($this->campaign->id, $this->john->id, null);
        $this->addToRoster($this->john);

        $this->assertRefused(
            'already_member',
            'Ya formas parte de la plantilla.',
            fn () => $this->service()->requestToJoin($this->campaign->id, $this->john->id, null),
        );
    }

    public function test_adding_a_member_again_is_refused(): void
    {
        $this->addToRoster($this->john);

        $this->assertRefused(
            'account_already_member',
            'Esa cuenta ya forma parte de la plantilla.',
            fn () => $this->service()->addUser($this->campaign->id, $this->john->id, $this->mary->id),
        );
    }

    public function test_adding_to_a_full_roster_is_refused(): void
    {
        $this->addToRoster($this->john, $this->jane, $this->richard);
        $extra = UserModel::factory()->create();

        $this->assertRefused(
            'roster_full',
            'La plantilla está completa.',
            fn () => $this->service()->addUser($this->campaign->id, $extra->id, $this->mary->id),
        );
    }

    public function test_the_director_cannot_be_added(): void
    {
        $this->assertRefused(
            'is_director',
            'Quien dirige la campaña no puede entrar en la plantilla como jugador.',
            fn () => $this->service()->addUser($this->campaign->id, $this->mary->id, $this->mary->id),
        );
    }

    public function test_adding_to_a_closed_campaign_is_allowed(): void
    {
        $this->campaign->update(['status' => 'on_hold', 'accepts_new_players' => false]);

        $this->service()->addUser($this->campaign->id, $this->john->id, $this->mary->id);

        $this->assertTrue($this->roster()->contains($this->campaignId, $this->john->id));
    }

    public function test_adding_accepts_a_pending_request_of_that_user(): void
    {
        $this->service()->requestToJoin($this->campaign->id, $this->john->id, null);
        $request = $this->pendingOf($this->john);

        $this->service()->addUser($this->campaign->id, $this->john->id, $this->mary->id);

        $stored = $this->requests()->find($request->id);
        $this->assertNotNull($stored);
        $this->assertSame(JoinRequestStatus::Accepted, $stored->status);
        $this->assertSame($this->mary->id, $stored->decidedBy);
        $this->assertTrue($this->roster()->contains($this->campaignId, $this->john->id));
        Event::assertDispatched(CampaignRosterMemberAdded::class);
        Event::assertNotDispatched(CampaignJoinRequestAccepted::class);
    }

    public function test_an_anonymized_requester_is_hidden(): void
    {
        $this->service()->requestToJoin($this->campaign->id, $this->john->id, null);
        $this->service()->requestToJoin($this->campaign->id, $this->jane->id, null);

        $this->john->forceFill(['anonymized_at' => CarbonImmutable::now()])->save();

        $pending = $this->requests()->pendingFor($this->campaignId);
        $this->assertSame([$this->jane->id], array_map(fn (CampaignJoinRequest $r): string => $r->userId, $pending));
        $this->assertSame([$this->campaign->id => 1], $this->requests()->pendingCountsFor([$this->campaignId]));
    }

    public function test_an_unknown_campaign_is_not_found(): void
    {
        $this->expectException(CampaignNotFoundException::class);

        $this->service()->requestToJoin((string) Str::uuid(), $this->john->id, null);
    }
}
