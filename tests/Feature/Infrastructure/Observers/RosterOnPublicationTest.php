<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Feature\Infrastructure\Observers;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Modules\GameTables\Application\Services\CampaignRosterSessionSyncInterface;
use Modules\GameTables\Application\Services\FrontendCreationServiceInterface;
use Modules\GameTables\Application\Services\GameTableServiceInterface;
use Modules\GameTables\Application\Services\RegistrationServiceInterface;
use Modules\GameTables\Domain\Events\GameTablePublished;
use Modules\GameTables\Domain\Events\RosterMemberEnrolledInSession;
use Modules\GameTables\Domain\Exceptions\SessionBelowRosterException;
use Modules\GameTables\Domain\Repositories\CampaignRosterRepositoryInterface;
use Modules\GameTables\Domain\ValueObjects\CampaignId;
use Modules\GameTables\Filament\Resources\GameTableResource;
use Modules\GameTables\Filament\Resources\GameTableResource\Pages\CreateGameTable;
use Modules\GameTables\Filament\Resources\GameTableResource\Pages\EditGameTable;
use Modules\GameTables\Filament\Resources\GameTableResource\Pages\ListGameTables;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\CampaignModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameSystemModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameTableModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\ParticipantModel;
use RuntimeException;
use Tests\Support\Modules\InteractsWithModulePanel;
use Tests\Support\Modules\ModuleTestCase;

/**
 * Publishing a campaign session enrols the campaign roster, whatever the path: approval, the
 * automatic publication on submit, the panel action, the panel form (edit or create) and a change
 * of campaign. Publishing again adds nobody twice and never brings back an absence.
 */
final class RosterOnPublicationTest extends ModuleTestCase
{
    use InteractsWithModulePanel;

    protected ?string $moduleName = 'game-tables';

    protected bool $autoEnableModule = true;

    private const string BELOW_ROSTER = 'Esta sesión tiene menos plazas (1) que la plantilla de la campaña (2). Sube el máximo de jugadores antes de publicarla.';

    private GameSystemModel $system;

    private CampaignModel $campaign;

    private UserModel $author;

    private UserModel $john;

    private UserModel $jane;

    protected function setUp(): void
    {
        parent::setUp();

        app('router')->getRoutes()->refreshNameLookups();
        Notification::fake();
        Mail::fake();
        config()->set('modules.settings.game-tables', [
            'frontend_creation' => [
                'enabled' => true,
                'access_level' => 'registered',
                'allowed_content' => 'both',
                'publication' => ['mode' => 'approval'],
            ],
            'registration' => ['waiting_list_enabled' => true],
        ]);
        $this->travelTo(CarbonImmutable::parse('2026-11-02 10:00:00'));
        $this->registerOnAdminPanel([GameTableResource::class]);

        $this->author = UserModel::factory()->create(['name' => 'Mary Major', 'email' => 'mary@example.com']);
        $this->system = GameSystemModel::create(['id' => (string) Str::uuid(), 'name' => 'Sistema', 'slug' => 'sistema', 'is_active' => true]);
        $this->campaign = $this->campaign('La cripta olvidada');

        $this->john = UserModel::factory()->create(['name' => 'John Doe', 'email' => 'john@example.com']);
        $this->jane = UserModel::factory()->create(['name' => 'Jane Doe', 'email' => 'jane@example.com']);
        $this->addToRoster($this->campaign, $this->john, $this->jane);
    }

    public function test_approving_a_web_session_enrols_the_roster(): void
    {
        Event::fake([RosterMemberEnrolledInSession::class]);
        $session = $this->campaignSession(creationStatus: 'pending_review');

        app(FrontendCreationServiceInterface::class)->approveFrontendCreation($session->id);

        $this->assertTrue($session->refresh()->is_published);
        $this->assertRosterEnrolled($session);
        Event::assertDispatchedTimes(RosterMemberEnrolledInSession::class, 2);
        Event::assertDispatched(
            RosterMemberEnrolledInSession::class,
            fn (RosterMemberEnrolledInSession $event): bool => $event->gameTableId === $session->id
                && $event->userId === $this->john->id
                && $event->waiting === false
                && $event->participantId === $this->rowOf($session, $this->john)?->id,
        );
    }

    // A seat the panel gave an outsider before publication: one member waits, and is announced so
    public function test_a_member_without_a_seat_is_announced_as_waiting(): void
    {
        Event::fake([RosterMemberEnrolledInSession::class]);
        $session = $this->campaignSession(maxPlayers: 2);
        $richard = UserModel::factory()->create(['name' => 'Richard Roe', 'email' => 'richard@example.com']);
        ParticipantModel::create([
            'id' => (string) Str::uuid(),
            'game_table_id' => $session->id,
            'user_id' => $richard->id,
            'role' => 'player',
            'status' => 'confirmed',
            'confirmed_at' => now(),
        ]);

        app(GameTableServiceInterface::class)->publish($session->id);

        $statuses = [$this->rowOf($session, $this->john)?->status->value, $this->rowOf($session, $this->jane)?->status->value];
        $this->assertEqualsCanonicalizing(['confirmed', 'waiting_list'], $statuses);
        Event::assertDispatchedTimes(RosterMemberEnrolledInSession::class, 2);
        foreach ([$this->john, $this->jane] as $member) {
            $waiting = $this->rowOf($session, $member)?->status->value === 'waiting_list';
            Event::assertDispatched(
                RosterMemberEnrolledInSession::class,
                fn (RosterMemberEnrolledInSession $event): bool => $event->userId === $member->id && $event->waiting === $waiting,
            );
        }
    }

    public function test_auto_publication_on_submit_enrols_the_roster(): void
    {
        Event::fake([RosterMemberEnrolledInSession::class]);
        config()->set('modules.settings.game-tables.frontend_creation.publication.mode', 'auto');
        $session = $this->campaignSession(creationStatus: 'draft');

        app(FrontendCreationServiceInterface::class)->submitForReview($session->id, $this->author->id);

        $this->assertTrue($session->refresh()->is_published);
        $this->assertRosterEnrolled($session);
        Event::assertDispatchedTimes(RosterMemberEnrolledInSession::class, 2);
    }

    public function test_the_panel_publish_action_enrols_the_roster(): void
    {
        Event::fake([RosterMemberEnrolledInSession::class, GameTablePublished::class]);
        $this->actingAsPanelAdmin();
        $session = $this->campaignSession(status: 'scheduled');

        Livewire::test(EditGameTable::class, ['record' => $session->getRouteKey()])
            ->callAction('publish')
            ->assertFormSet(['is_published' => true, 'status' => 'scheduled'])
            ->assertActionHidden('publish');

        $this->assertTrue($session->refresh()->is_published);
        $this->assertRosterEnrolled($session);
        Event::assertDispatchedTimes(RosterMemberEnrolledInSession::class, 2);
        Event::assertDispatched(GameTablePublished::class, fn (GameTablePublished $event): bool => $event->gameTableId === $session->id);
    }

    public function test_saving_the_panel_form_published_enrols_the_roster(): void
    {
        Event::fake([RosterMemberEnrolledInSession::class]);
        $this->actingAsPanelAdmin();
        $session = $this->campaignSession();

        Livewire::test(EditGameTable::class, ['record' => $session->getRouteKey()])
            ->set('data.is_published', true)
            ->set('data.status', 'scheduled')
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertTrue($session->refresh()->is_published);
        $this->assertRosterEnrolled($session);
        Event::assertDispatchedTimes(RosterMemberEnrolledInSession::class, 2);
    }

    public function test_creating_a_published_session_in_the_panel_enrols_the_roster(): void
    {
        Event::fake([RosterMemberEnrolledInSession::class]);
        $this->actingAsPanelAdmin();

        Livewire::test(CreateGameTable::class)
            ->fillForm([
                'title' => 'Sesión 4',
                'campaign_id' => $this->campaign->id,
                'game_system_id' => $this->system->id,
                'starts_at' => '2026-11-09 18:00:00',
                'duration_minutes' => 240,
                'table_format' => 'in_person',
                'table_type' => 'campaign_session',
                'min_players' => 1,
                'max_players' => 5,
                'language' => 'es',
                'experience_level' => 'none',
                'character_creation' => 'pre_generated',
                'registration_type' => 'everyone',
                'members_early_access_days' => 0,
                'is_published' => true,
                'status' => 'scheduled',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $session = GameTableModel::query()->where('title', 'Sesión 4')->firstOrFail();
        $this->assertRosterEnrolled($session);
        Event::assertDispatchedTimes(RosterMemberEnrolledInSession::class, 2);
    }

    public function test_changing_the_campaign_of_a_published_session_enrols_the_new_roster_and_keeps_the_old_rows(): void
    {
        Event::fake([RosterMemberEnrolledInSession::class]);
        $session = $this->campaignSession();
        app(GameTableServiceInterface::class)->publish($session->id);
        $other = $this->campaign('El faro del norte');
        $richard = UserModel::factory()->create(['name' => 'Richard Roe', 'email' => 'richard@example.com']);
        $this->addToRoster($other, $richard);

        $session->refresh()->update(['campaign_id' => $other->id]);

        $this->assertSame('confirmed', $this->rowOf($session, $richard)?->status->value);
        $this->assertSame('confirmed', $this->rowOf($session, $this->john)?->status->value);
        $this->assertSame('confirmed', $this->rowOf($session, $this->jane)?->status->value);
        $this->assertSame(3, ParticipantModel::query()->where('game_table_id', $session->id)->count());
        Event::assertDispatchedTimes(RosterMemberEnrolledInSession::class, 3);
    }

    public function test_a_draft_session_enrols_nobody(): void
    {
        Event::fake([RosterMemberEnrolledInSession::class]);
        $session = $this->campaignSession();

        $session->update(['title' => 'Sesión 3: el regreso']);

        $this->assertSame(0, ParticipantModel::query()->where('game_table_id', $session->id)->count());
        Event::assertNotDispatched(RosterMemberEnrolledInSession::class);
    }

    // The instance keeps wasRecentlyCreated: a later edit of it is not a publication
    public function test_editing_a_session_created_published_enrols_nobody_else(): void
    {
        Event::fake([RosterMemberEnrolledInSession::class]);
        $session = $this->campaignSession(status: 'scheduled', published: true);
        $richard = UserModel::factory()->create(['name' => 'Richard Roe', 'email' => 'richard@example.com']);
        $this->addToRoster($this->campaign, $richard);

        $session->update(['title' => 'Sesión 3: el regreso']);

        $this->assertRosterEnrolled($session);
        $this->assertNull($this->rowOf($session, $richard));
        Event::assertDispatchedTimes(RosterMemberEnrolledInSession::class, 2);
    }

    public function test_unpublishing_keeps_the_rows_and_sends_nothing(): void
    {
        Event::fake([RosterMemberEnrolledInSession::class]);
        $session = $this->campaignSession();
        $service = app(GameTableServiceInterface::class);
        $service->publish($session->id);

        $service->unpublish($session->id);

        $this->assertFalse($session->refresh()->is_published);
        $this->assertRosterEnrolled($session);
        Event::assertDispatchedTimes(RosterMemberEnrolledInSession::class, 2);
    }

    public function test_republishing_adds_no_duplicates_and_no_email(): void
    {
        Event::fake([RosterMemberEnrolledInSession::class]);
        $session = $this->campaignSession();
        $service = app(GameTableServiceInterface::class);

        $service->publish($session->id);
        $service->unpublish($session->id);
        $service->publish($session->id);
        // A later status change Full ↔ Scheduled sends nothing either
        $session->refresh()->update(['status' => 'full']);
        $session->refresh()->update(['status' => 'scheduled']);

        $this->assertRosterEnrolled($session);
        Event::assertDispatchedTimes(RosterMemberEnrolledInSession::class, 2);
    }

    public function test_republishing_respects_absences(): void
    {
        Event::fake([RosterMemberEnrolledInSession::class]);
        $session = $this->campaignSession();
        $service = app(GameTableServiceInterface::class);
        $service->publish($session->id);
        app(RegistrationServiceInterface::class)->cancelByUser($session->id, $this->jane->id);

        $service->unpublish($session->id);
        $service->publish($session->id);

        $jane = $this->rowOf($session, $this->jane);
        $this->assertSame('cancelled', $jane?->status->value);
        $this->assertSame('session_absence', $jane->getRawOriginal('cancellation_reason'));
        $this->assertSame('confirmed', $this->rowOf($session, $this->john)?->status->value);
        $this->assertSame(2, ParticipantModel::query()->where('game_table_id', $session->id)->count());
        Event::assertDispatchedTimes(RosterMemberEnrolledInSession::class, 2);
    }

    public function test_the_roster_is_enrolled_once_the_save_is_committed(): void
    {
        $session = $this->campaignSession();

        DB::transaction(function () use ($session): void {
            $session->update(['is_published' => true, 'status' => 'scheduled']);

            $this->assertSame(0, ParticipantModel::query()->where('game_table_id', $session->id)->count());
        });

        $this->assertRosterEnrolled($session);
    }

    // The save has already been committed when the roster is enrolled: a failure there is reported, never thrown
    public function test_an_enrolment_failure_is_reported_without_breaking_the_save(): void
    {
        Exceptions::fake();
        $sync = $this->createStub(CampaignRosterSessionSyncInterface::class);
        $sync->method('onSessionPublished')->willThrowException(new RuntimeException('Enrolment failed'));
        $this->app->instance(CampaignRosterSessionSyncInterface::class, $sync);
        $session = $this->campaignSession();

        $session->update(['is_published' => true, 'status' => 'scheduled']);

        $this->assertTrue($session->refresh()->is_published);
        Exceptions::assertReported(fn (RuntimeException $e): bool => $e->getMessage() === 'Enrolment failed');
    }

    public function test_publishing_a_session_below_the_roster_is_refused(): void
    {
        Event::fake([RosterMemberEnrolledInSession::class]);
        $session = $this->campaignSession(maxPlayers: 1);

        try {
            app(GameTableServiceInterface::class)->publish($session->id);
            $this->fail('A session below the roster was published');
        } catch (SessionBelowRosterException $e) {
            $this->assertSame('session_below_roster', $e->reason);
            $this->assertSame(self::BELOW_ROSTER, $e->getMessage());
        }

        $this->assertFalse($session->refresh()->is_published);
        $this->assertSame('draft', $session->status->value);
        $this->assertSame(0, ParticipantModel::query()->where('game_table_id', $session->id)->count());
        Event::assertNotDispatched(RosterMemberEnrolledInSession::class);
    }

    public function test_approving_a_session_below_the_roster_is_refused(): void
    {
        $session = $this->campaignSession(creationStatus: 'pending_review', maxPlayers: 1);

        try {
            app(FrontendCreationServiceInterface::class)->approveFrontendCreation($session->id);
            $this->fail('A session below the roster was approved');
        } catch (SessionBelowRosterException $e) {
            $this->assertSame(self::BELOW_ROSTER, $e->getMessage());
        }

        $this->assertStillInReview($session);
    }

    public function test_the_panel_publish_action_shows_the_refusal(): void
    {
        $this->actingAsPanelAdmin();
        $session = $this->campaignSession(status: 'scheduled', maxPlayers: 1);

        Livewire::test(EditGameTable::class, ['record' => $session->getRouteKey()])
            ->callAction('publish')
            ->assertNotified(self::BELOW_ROSTER)
            ->assertFormSet(['is_published' => false]);

        $this->assertFalse($session->refresh()->is_published);
    }

    public function test_the_panel_approve_action_shows_the_refusal(): void
    {
        $this->actingAsPanelAdmin();
        $session = $this->campaignSession(creationStatus: 'pending_review', maxPlayers: 1);

        Livewire::test(EditGameTable::class, ['record' => $session->getRouteKey()])
            ->callAction('approveModeration')
            ->assertNotified(self::BELOW_ROSTER);

        $this->assertStillInReview($session);
    }

    public function test_the_row_approve_action_shows_the_refusal(): void
    {
        $this->actingAsPanelAdmin();
        $session = $this->campaignSession(creationStatus: 'pending_review', maxPlayers: 1);

        Livewire::test(ListGameTables::class)
            ->callTableAction('approve', $session)
            ->assertNotified(self::BELOW_ROSTER);

        $this->assertStillInReview($session);
    }

    public function test_the_bulk_approve_action_shows_the_refusal(): void
    {
        $this->actingAsPanelAdmin();
        $session = $this->campaignSession(creationStatus: 'pending_review', maxPlayers: 1);

        Livewire::test(ListGameTables::class)
            ->callTableBulkAction('approve', [$session]);

        // Read once (the notification assertions consume them): nothing was approved, so the
        // refusal comes alone, without «0 mesa(s) aprobada(s)» next to it
        $titles = array_column(session('filament.claimed_notifications') ?? session('filament.notifications', []), 'title');
        $this->assertContains(self::BELOW_ROSTER, $titles);
        $this->assertNotContains('0 mesa(s) aprobada(s)', $titles);

        $this->assertStillInReview($session);
    }

    public function test_an_automatic_publication_below_the_roster_flashes_the_refusal(): void
    {
        config()->set('modules.settings.game-tables.frontend_creation.publication.mode', 'auto');
        $session = $this->campaignSession(creationStatus: 'draft', maxPlayers: 1);

        $this->actingAs($this->author)
            ->from('/mesas/mis-mesas')
            ->post("/mesas/mis-mesas/{$session->slug}/enviar-revision")
            ->assertRedirect('/mesas/mis-mesas')
            ->assertSessionHas('error', self::BELOW_ROSTER);

        $session->refresh();
        $this->assertFalse($session->is_published);
        $this->assertSame('draft', $session->frontend_creation_status?->value);
    }

    public function test_the_refusal_is_translated_in_both_languages(): void
    {
        $this->assertSame(self::BELOW_ROSTER, trans('game-tables::messages.errors.session_below_roster', ['max' => 1, 'count' => 2], 'es'));
        $this->assertSame(
            'This session has fewer seats (1) than the campaign roster (2). Raise the maximum number of players before publishing it.',
            trans('game-tables::messages.errors.session_below_roster', ['max' => 1, 'count' => 2], 'en'),
        );
    }

    private function campaign(string $title): CampaignModel
    {
        return CampaignModel::create([
            'id' => (string) Str::uuid(),
            'game_system_id' => $this->system->id,
            'created_by' => $this->author->id,
            'title' => $title,
            'slug' => Str::slug($title),
            'status' => 'active',
            'is_published' => true,
        ]);
    }

    private function addToRoster(CampaignModel $campaign, UserModel ...$members): void
    {
        $roster = app(CampaignRosterRepositoryInterface::class);
        foreach ($members as $member) {
            $roster->add(new CampaignId($campaign->id), $member->id, CarbonImmutable::now()->toDateTimeImmutable());
        }
    }

    /**
     * A session of the campaign a week away (unpublished unless said), created in the panel (no
     * creation status) or from the web.
     */
    private function campaignSession(
        ?string $creationStatus = null,
        string $status = 'draft',
        int $maxPlayers = 5,
        bool $published = false,
    ): GameTableModel {
        return GameTableModel::create([
            'id' => (string) Str::uuid(),
            'game_system_id' => $this->system->id,
            'campaign_id' => $this->campaign->id,
            'created_by' => $this->author->id,
            'title' => 'Sesión 3',
            'slug' => 'sesion-3',
            'starts_at' => now()->addWeek(),
            'duration_minutes' => 240,
            'table_type' => 'campaign_session',
            'table_format' => 'in_person',
            'status' => $status,
            'min_players' => 1,
            'max_players' => $maxPlayers,
            'language' => 'es',
            'experience_level' => 'none',
            'character_creation' => 'pre_generated',
            'registration_type' => 'everyone',
            'auto_confirm' => false,
            'is_published' => $published,
            'frontend_creation_status' => $creationStatus,
        ]);
    }

    private function rowOf(GameTableModel $session, UserModel $user): ?ParticipantModel
    {
        return ParticipantModel::query()
            ->where('game_table_id', $session->id)
            ->where('user_id', $user->id)
            ->first();
    }

    private function assertStillInReview(GameTableModel $session): void
    {
        $session->refresh();
        $this->assertFalse($session->is_published);
        $this->assertSame('pending_review', $session->frontend_creation_status?->value);
        $this->assertSame(0, ParticipantModel::query()->where('game_table_id', $session->id)->count());
    }

    private function assertRosterEnrolled(GameTableModel $session): void
    {
        foreach ([$this->john, $this->jane] as $member) {
            $row = $this->rowOf($session, $member);
            $this->assertNotNull($row, "{$member->name} is not enrolled");
            $this->assertSame('confirmed', $row->status->value);
            $this->assertTrue($row->from_roster);
        }
        $this->assertSame(2, ParticipantModel::query()->where('game_table_id', $session->id)->count());
    }
}
