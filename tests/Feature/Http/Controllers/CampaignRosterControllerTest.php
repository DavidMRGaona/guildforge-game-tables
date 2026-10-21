<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Feature\Http\Controllers;

use App\Application\Services\SettingsServiceInterface;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\GameTables\Application\Services\CampaignInvitationServiceInterface;
use Modules\GameTables\Domain\Repositories\CampaignRosterRepositoryInterface;
use Modules\GameTables\Domain\ValueObjects\CampaignId;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\CampaignJoinRequestModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\CampaignModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameSystemModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameTableModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\ParticipantModel;
use Tests\Support\Modules\ModuleTestCase;

/**
 * The roster page of a campaign and its actions: only the direction of an approved or published
 * campaign reaches them, every action goes through the roster service and answers with a flash.
 */
final class CampaignRosterControllerTest extends ModuleTestCase
{
    protected ?string $moduleName = 'game-tables';

    protected bool $autoEnableModule = true;

    private GameSystemModel $system;

    private UserModel $mary;

    private UserModel $john;

    private UserModel $jane;

    private UserModel $richard;

    private CampaignModel $campaign;

    private GameTableModel $first;

    private GameTableModel $second;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        app('router')->getRoutes()->refreshNameLookups();
        config()->set('modules.settings.game-tables', []);
        $this->travelTo(CarbonImmutable::parse('2026-11-02 10:00:00'));

        $this->mary = UserModel::factory()->create(['name' => 'Mary Major', 'email' => 'mary@example.com']);
        $this->john = UserModel::factory()->create(['name' => 'John Doe', 'email' => 'john@example.com']);
        $this->jane = UserModel::factory()->create(['name' => 'Jane Doe', 'email' => 'jane@example.com']);
        $this->richard = UserModel::factory()->create(['name' => 'Richard Roe', 'email' => 'richard@example.com']);

        $this->system = GameSystemModel::create(['id' => (string) Str::uuid(), 'name' => 'Sistema', 'slug' => 'sistema', 'is_active' => true]);
        $this->campaign = $this->campaign('La cripta olvidada');
        $this->first = $this->campaignSession($this->campaign, 'Sesión 1', CarbonImmutable::parse('2026-11-09 18:00:00'));
        $this->second = $this->campaignSession($this->campaign, 'Sesión 2', CarbonImmutable::parse('2026-11-16 18:00:00'));

        $this->addMember($this->john, CarbonImmutable::parse('2026-10-01 12:00:00'));
        $this->addMember($this->jane, CarbonImmutable::parse('2026-10-05 12:00:00'));
        foreach ([$this->first, $this->second] as $session) {
            $this->rosterRow($session, $this->john);
        }
        $this->rosterRow($this->first, $this->jane, ['status' => 'cancelled', 'cancellation_reason' => 'session_absence']);
        $this->rosterRow($this->second, $this->jane);
    }

    public function test_the_director_sees_the_roster_page(): void
    {
        $request = $this->pendingRequest($this->campaign, $this->richard, 'Me encantaría jugar.');

        $this->actingAs($this->mary)
            ->get('/campanas/mis-campanas/'.$this->campaign->id.'/plantilla')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Campaigns/Roster', shouldExist: false)
                ->where('campaign.id', $this->campaign->id)
                ->where('campaign.title', 'La cripta olvidada')
                ->where('campaign.slug', 'la-cripta-olvidada')
                ->where('campaign.status_label', 'Activa')
                ->where('campaign.is_finished', false)
                ->where('campaign.accepts_new_players', true)
                ->where('campaign.roster_access', 'anyone')
                ->where('campaign.max_players', 5)
                ->where('campaign.roster_count', 2)
                ->where('roster_access_options.anyone', 'Cualquier cuenta')
                ->has('max_players_limit')
                ->where('can_add', true)
                ->where('removal_notice', true)
                ->has('invitations', 0)
                ->where('more_invitations_than_seats', false)
                ->has('requests', 1)
                ->where('requests.0.id', $request->id)
                ->where('requests.0.user_name', 'Richard Roe')
                ->where('requests.0.message', 'Me encantaría jugar.')
                ->where('requests.0.created_at', '02/11/2026')
                ->has('sessions', 2)
                ->where('sessions.0.game_table_id', $this->first->id)
                ->where('sessions.0.title', 'Sesión 1')
                ->where('sessions.0.date_short', '09/11')
                ->where('sessions.0.date', '09/11/2026 18:00')
                ->where('sessions.1.game_table_id', $this->second->id)
                ->has('players', 2)
                ->where('players.0.name', 'John Doe')
                ->where('players.0.user_id', $this->john->id)
                ->where('players.0.joined_at', '01/10/2026')
                ->where('players.0.sessions.0.state', 'going')
                ->where('players.0.sessions.0.can_toggle', true)
                ->where('players.1.name', 'Jane Doe')
                ->where('players.1.sessions.0.game_table_id', $this->first->id)
                ->where('players.1.sessions.0.state', 'absent')
                ->where('players.1.sessions.0.can_toggle', true)
                ->where('players.1.sessions.1.state', 'going')
                ->where('seo.title', 'Plantilla · La cripta olvidada')
                ->where('seo.robots', 'noindex, nofollow'));
    }

    public function test_the_page_says_whether_removing_a_player_sends_them_an_email(): void
    {
        config()->set('modules.settings.game-tables.campaign_notifications.roster_removed', false);

        // The remove dialog only promises the email the server will send
        $this->actingAs($this->mary)
            ->get('/campanas/mis-campanas/'.$this->campaign->id.'/plantilla')
            ->assertInertia(fn (Assert $page) => $page->where('removal_notice', false));
    }

    public function test_each_session_says_when_it_starts_and_how_many_seats_are_taken(): void
    {
        $this->second->forceFill(['max_players' => 2])->save();

        $this->actingAs($this->mary)
            ->get($this->rosterUrl())
            ->assertInertia(fn (Assert $page) => $page
                ->where('sessions.0.starts_at', '2026-11-09T18:00:00+01:00')
                ->where('sessions.0.seats_taken', 1)
                ->where('sessions.0.max_players', 5)
                ->where('sessions.0.has_room', true)
                // John and Jane hold both seats of the second session
                ->where('sessions.1.seats_taken', 2)
                ->where('sessions.1.max_players', 2)
                ->where('sessions.1.has_room', false));
    }

    public function test_the_page_reads_the_sessions_rows_at_once_however_many_sessions_there_are(): void
    {
        $queries = fn (): int => $this->countQueries(fn () => $this->actingAs($this->mary)->get($this->rosterUrl())->assertOk());
        // The first request also warms caches (settings, permissions)
        $queries();
        $withTwo = $queries();

        // A director may publish a whole season at once
        for ($week = 3; $week <= 20; $week++) {
            $session = $this->campaignSession($this->campaign, "Sesión {$week}", CarbonImmutable::parse('2026-11-02 18:00:00')->addWeeks($week));
            $this->rosterRow($session, $this->john);
            $this->rosterRow($session, $this->jane);
        }

        $this->actingAs($this->mary)
            ->get($this->rosterUrl())
            ->assertInertia(fn (Assert $page) => $page->has('sessions', 20)->has('players.0.sessions', 20));
        $this->assertSame($withTwo, $queries());
    }

    public function test_the_page_marks_waiting_rows_and_sessions_without_a_row(): void
    {
        ParticipantModel::query()->where('game_table_id', $this->second->id)->where('user_id', $this->jane->id)
            ->update(['status' => 'waiting_list', 'waiting_list_position' => 1]);
        ParticipantModel::query()->where('game_table_id', $this->second->id)->where('user_id', $this->john->id)->delete();

        $this->actingAs($this->mary)
            ->get('/campanas/mis-campanas/'.$this->campaign->id.'/plantilla')
            ->assertInertia(fn (Assert $page) => $page
                ->where('players.0.sessions.1.state', 'none')
                ->where('players.0.sessions.1.can_toggle', false)
                ->where('players.1.sessions.1.state', 'waiting')
                ->where('players.1.sessions.1.can_toggle', true));
    }

    public function test_started_unpublished_and_other_campaign_sessions_are_not_listed(): void
    {
        $this->campaignSession($this->campaign, 'Sesión pasada', CarbonImmutable::parse('2026-11-01 18:00:00'));
        $this->campaignSession($this->campaign, 'Sesión oculta', CarbonImmutable::parse('2026-11-20 18:00:00'), ['is_published' => false]);
        $this->campaignSession($this->campaign, 'Sesión cancelada', CarbonImmutable::parse('2026-11-21 18:00:00'), ['status' => 'cancelled']);
        $this->campaignSession($this->campaign('La torre del mago'), 'Sesión ajena', CarbonImmutable::parse('2026-11-10 18:00:00'));

        $this->actingAs($this->mary)
            ->get('/campanas/mis-campanas/'.$this->campaign->id.'/plantilla')
            ->assertInertia(fn (Assert $page) => $page
                ->has('sessions', 2)
                ->where('sessions.0.title', 'Sesión 1')
                ->where('sessions.1.title', 'Sesión 2')
                ->has('players.0.sessions', 2));
    }

    public function test_the_route_is_the_one_the_emails_link_to(): void
    {
        $this->assertSame(
            url('/campanas/mis-campanas/'.$this->campaign->id.'/plantilla'),
            route('campaigns.roster', $this->campaign->id),
        );
    }

    public function test_someone_who_does_not_direct_the_campaign_is_forbidden(): void
    {
        foreach ([$this->richard, $this->john] as $user) {
            $this->actingAs($user)
                ->get('/campanas/mis-campanas/'.$this->campaign->id.'/plantilla')
                ->assertForbidden();
        }
    }

    public function test_an_unknown_campaign_is_not_found(): void
    {
        foreach ([(string) Str::uuid(), 'not-a-uuid'] as $id) {
            $this->actingAs($this->mary)
                ->get('/campanas/mis-campanas/'.$id.'/plantilla')
                ->assertNotFound();
        }
    }

    public function test_a_draft_campaign_is_forbidden(): void
    {
        $draft = $this->campaign('Borrador', ['frontend_creation_status' => 'draft', 'is_published' => false]);

        $this->actingAs($this->mary)
            ->get('/campanas/mis-campanas/'.$draft->id.'/plantilla')
            ->assertForbidden();
    }

    public function test_a_guest_is_sent_to_log_in(): void
    {
        $this->get('/campanas/mis-campanas/'.$this->campaign->id.'/plantilla')
            ->assertRedirect(route('login'));
    }

    public function test_an_account_that_must_verify_its_email_is_sent_to_the_notice(): void
    {
        app(SettingsServiceInterface::class)->set('email_verification_enforced_since', '2026-01-01T00:00:00+00:00');
        app(SettingsServiceInterface::class)->set('auth_email_verification_required', '1');
        $this->mary->forceFill(['email_verified_at' => null])->save();

        $this->actingAs($this->mary)
            ->get('/campanas/mis-campanas/'.$this->campaign->id.'/plantilla')
            ->assertRedirect(route('verification.notice'));

        $this->actingAs($this->mary)
            ->from($this->rosterUrl())
            ->post($this->rosterUrl().'/invitaciones', ['email' => 'richard@example.com', 'consent' => true])
            ->assertRedirect(route('verification.notice'));

        $this->assertFalse($this->roster()->contains(new CampaignId($this->campaign->id), $this->richard->id));
    }

    // Actions

    public function test_saving_the_settings(): void
    {
        $this->asDirector()
            ->put($this->rosterUrl().'/ajustes', [
                'accepts_new_players' => false,
                'roster_access' => 'members_only',
                'max_players' => 4,
            ])
            ->assertRedirect($this->rosterUrl())
            ->assertSessionHas('success', 'Ajustes guardados.');

        $this->campaign->refresh();
        $this->assertFalse((bool) $this->campaign->accepts_new_players);
        $this->assertSame('members_only', $this->campaign->roster_access->value);
        $this->assertSame(4, (int) $this->campaign->max_players);
    }

    public function test_settings_without_a_maximum_remove_the_limit(): void
    {
        $this->asDirector()
            ->put($this->rosterUrl().'/ajustes', [
                'accepts_new_players' => true,
                'roster_access' => 'anyone',
                'max_players' => null,
            ])
            ->assertSessionHas('success', 'Ajustes guardados.');

        $this->assertNull($this->campaign->refresh()->max_players);
    }

    public function test_settings_with_a_maximum_below_the_roster_are_refused(): void
    {
        $this->asDirector()
            ->put($this->rosterUrl().'/ajustes', [
                'accepts_new_players' => true,
                'roster_access' => 'anyone',
                'max_players' => 1,
            ])
            ->assertRedirect($this->rosterUrl())
            ->assertSessionHas('error', 'El máximo de jugadores no puede ser menor que la plantilla actual (2).');

        $this->assertSame(5, (int) $this->campaign->refresh()->max_players);
    }

    public function test_settings_are_validated(): void
    {
        $this->asDirector()
            ->put($this->rosterUrl().'/ajustes', [
                'roster_access' => 'everybody',
                'max_players' => 0,
            ])
            ->assertSessionHasErrors(['accepts_new_players', 'roster_access', 'max_players']);
    }

    public function test_inviting_answers_the_same_for_unknown_and_known_emails(): void
    {
        $this->asDirector()
            ->post($this->rosterUrl().'/invitaciones', ['email' => 'Nobody@Example.com', 'consent' => true])
            ->assertRedirect($this->rosterUrl())
            ->assertSessionHas('success', 'Invitación enviada a nobody@example.com.');

        $this->asDirector()
            ->post($this->rosterUrl().'/invitaciones', ['email' => 'richard@example.com', 'consent' => true])
            ->assertRedirect($this->rosterUrl())
            ->assertSessionHas('success', 'Invitación enviada a richard@example.com.');

        $this->assertFalse($this->roster()->contains(new CampaignId($this->campaign->id), $this->richard->id));
        $this->assertSame(2, DB::table('gametables_campaign_invitations')->where('campaign_id', $this->campaign->id)->count());
    }

    public function test_inviting_requires_the_consent(): void
    {
        $this->asDirector()
            ->post($this->rosterUrl().'/invitaciones', ['email' => 'richard@example.com'])
            ->assertSessionHasErrors(['consent' => 'Confirma que esta persona te ha dado permiso para invitarla.']);

        $this->asDirector()
            ->post($this->rosterUrl().'/invitaciones', ['email' => 'richard@example.com', 'consent' => false])
            ->assertSessionHasErrors('consent');

        $this->assertSame(0, DB::table('gametables_campaign_invitations')->count());
    }

    public function test_inviting_requires_a_valid_email(): void
    {
        $this->asDirector()
            ->post($this->rosterUrl().'/invitaciones', ['email' => 'not-an-email', 'consent' => true])
            ->assertSessionHasErrors('email');
    }

    public function test_inviting_a_member_flashes_the_refusal(): void
    {
        $this->asDirector()
            ->post($this->rosterUrl().'/invitaciones', ['email' => 'john@example.com', 'consent' => true])
            ->assertRedirect($this->rosterUrl())
            ->assertSessionHas('error', 'Esa cuenta ya forma parte de la plantilla.');

        $this->assertSame(0, DB::table('gametables_campaign_invitations')->count());
    }

    public function test_the_twenty_first_invitation_in_a_minute_is_rate_limited(): void
    {
        for ($i = 0; $i < 20; $i++) {
            $this->asDirector()
                ->post($this->rosterUrl().'/invitaciones', ['email' => "nobody{$i}@example.com", 'consent' => true])
                ->assertSessionHas('success');
        }

        $this->asDirector()
            ->post($this->rosterUrl().'/invitaciones', ['email' => 'richard@example.com', 'consent' => true])
            ->assertRedirect($this->rosterUrl())
            ->assertSessionHas('error', 'Has enviado demasiadas invitaciones seguidas. Espera un minuto y vuelve a intentarlo.');
        $this->actingAs($this->mary)
            ->postJson($this->rosterUrl().'/invitaciones', ['email' => 'richard@example.com', 'consent' => true])
            ->assertStatus(429)
            ->assertJson(['message' => 'Has enviado demasiadas invitaciones seguidas. Espera un minuto y vuelve a intentarlo.']);

        $this->assertSame(20, DB::table('gametables_campaign_invitations')->count());
    }

    public function test_resending_and_cancelling_an_invitation(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-02 10:00:00'));
        $this->invite($this->campaign, 'ana@example.com');
        $id = $this->invitationId($this->campaign, 'ana@example.com');

        // Less than 24 hours after the first send
        $this->asDirector()
            ->post($this->rosterUrl().'/invitaciones/'.$id.'/reenviar')
            ->assertSessionHas('error', 'Han de pasar 24 horas desde el último envío para volver a enviarla.');

        $this->travelTo(CarbonImmutable::parse('2026-11-03 11:00:00'));
        $this->asDirector()
            ->post($this->rosterUrl().'/invitaciones/'.$id.'/reenviar')
            ->assertRedirect($this->rosterUrl())
            ->assertSessionHas('success', 'Invitación reenviada.');

        $this->asDirector()
            ->delete($this->rosterUrl().'/invitaciones/'.$id)
            ->assertRedirect($this->rosterUrl())
            ->assertSessionHas('success', 'Invitación cancelada.');

        $this->assertSame(0, DB::table('gametables_campaign_invitations')->count());
    }

    public function test_resend_and_cancel_belong_to_the_campaign(): void
    {
        $other = $this->campaign('La torre del mago');
        $this->invite($other, 'ana@example.com');
        $id = $this->invitationId($other, 'ana@example.com');

        $this->asDirector()->post($this->rosterUrl().'/invitaciones/'.$id.'/reenviar')->assertNotFound();
        $this->asDirector()->delete($this->rosterUrl().'/invitaciones/'.$id)->assertNotFound();
        $this->asDirector()->delete($this->rosterUrl().'/invitaciones/not-a-uuid')->assertNotFound();

        $this->assertSame(1, DB::table('gametables_campaign_invitations')->where('campaign_id', $other->id)->count());
    }

    public function test_the_roster_page_lists_pending_invitations(): void
    {
        $this->invite($this->campaign, 'ana@example.com');
        $id = $this->invitationId($this->campaign, 'ana@example.com');
        $this->invite($this->campaign, 'luis@example.com');
        $this->invite($this->campaign('La torre del mago'), 'other@example.com');

        // 5 seats, 2 players, 2 pending: no warning
        $this->asDirector()
            ->get($this->rosterUrl())
            ->assertInertia(fn (Assert $page) => $page
                ->component('Campaigns/Roster', shouldExist: false)
                ->has('invitations', 2)
                ->where('invitations.0.id', $id)
                ->where('invitations.0.email', 'ana@example.com')
                ->where('invitations.0.inviter_name', 'Mary Major')
                ->where('invitations.0.send_count', 1)
                ->where('invitations.0.expires_at', CarbonImmutable::now()->addDays(14)->toIso8601String())
                ->where('invitations.0.can_resend', false)
                ->missing('invitations.0.campaign_id')
                ->where('more_invitations_than_seats', false));

        // 3 seats left (5 - 2) and now 4 pending
        $this->invite($this->campaign, 'eva@example.com');
        $this->invite($this->campaign, 'pau@example.com');
        $this->asDirector()
            ->get($this->rosterUrl())
            ->assertInertia(fn (Assert $page) => $page->has('invitations', 4)->where('more_invitations_than_seats', true));

        // Without a maximum there is no seat to run out of
        $this->campaign->update(['max_players' => null]);
        $this->asDirector()
            ->get($this->rosterUrl())
            ->assertInertia(fn (Assert $page) => $page->where('more_invitations_than_seats', false));
    }

    public function test_only_the_direction_invites(): void
    {
        $this->invite($this->campaign, 'ana@example.com');
        $id = $this->invitationId($this->campaign, 'ana@example.com');
        $url = $this->rosterUrl();

        $this->actingAs($this->john)->post($url.'/invitaciones', ['email' => 'richard@example.com', 'consent' => true])->assertForbidden();
        $this->actingAs($this->john)->post($url.'/invitaciones/'.$id.'/reenviar')->assertForbidden();
        $this->actingAs($this->john)->delete($url.'/invitaciones/'.$id)->assertForbidden();

        $this->assertSame(1, DB::table('gametables_campaign_invitations')->count());
    }

    public function test_removing_a_player(): void
    {
        $this->asDirector()
            ->delete($this->rosterUrl().'/jugadores/'.$this->john->id)
            ->assertRedirect($this->rosterUrl())
            ->assertSessionHas('success', 'Jugador quitado de la plantilla.');

        $this->assertFalse($this->roster()->contains(new CampaignId($this->campaign->id), $this->john->id));
    }

    public function test_removing_someone_outside_the_roster_is_refused(): void
    {
        $this->asDirector()
            ->delete($this->rosterUrl().'/jugadores/'.$this->richard->id)
            ->assertSessionHas('error', 'Esa persona no forma parte de la plantilla.');
    }

    public function test_accepting_a_request(): void
    {
        $request = $this->pendingRequest($this->campaign, $this->richard);

        $this->asDirector()
            ->post($this->rosterUrl().'/solicitudes/'.$request->id.'/aceptar')
            ->assertRedirect($this->rosterUrl())
            ->assertSessionHas('success', 'Solicitud aceptada.');

        $this->assertTrue($this->roster()->contains(new CampaignId($this->campaign->id), $this->richard->id));
        $this->assertSame('accepted', $this->requestStatus($request));
    }

    public function test_rejecting_a_request_with_a_reason(): void
    {
        $request = $this->pendingRequest($this->campaign, $this->richard);

        $this->asDirector()
            ->post($this->rosterUrl().'/solicitudes/'.$request->id.'/rechazar', ['reason' => 'La mesa está completa este trimestre.'])
            ->assertRedirect($this->rosterUrl())
            ->assertSessionHas('success', 'Solicitud rechazada.');

        $request->refresh();
        $this->assertSame('rejected', $this->requestStatus($request));
        $this->assertSame('La mesa está completa este trimestre.', $request->decision_reason);
    }

    public function test_a_rejection_reason_longer_than_500_characters_is_refused(): void
    {
        $request = $this->pendingRequest($this->campaign, $this->richard);

        $this->asDirector()
            ->post($this->rosterUrl().'/solicitudes/'.$request->id.'/rechazar', ['reason' => str_repeat('a', 501)])
            ->assertSessionHasErrors('reason');

        $this->assertSame('pending', $this->requestStatus($request));
    }

    public function test_a_request_that_is_no_longer_pending_is_refused(): void
    {
        $request = $this->pendingRequest($this->campaign, $this->richard);
        $request->forceFill(['status' => 'withdrawn'])->save();

        $this->asDirector()
            ->post($this->rosterUrl().'/solicitudes/'.$request->id.'/aceptar')
            ->assertSessionHas('error', 'Esta solicitud ya no está pendiente.');
    }

    public function test_a_request_of_another_campaign_is_not_found(): void
    {
        $other = $this->campaign('La torre del mago');
        $request = $this->pendingRequest($other, $this->richard);

        $this->asDirector()
            ->post($this->rosterUrl().'/solicitudes/'.$request->id.'/aceptar')
            ->assertNotFound();
        $this->asDirector()
            ->post($this->rosterUrl().'/solicitudes/'.$request->id.'/rechazar')
            ->assertNotFound();
        $this->asDirector()
            ->post($this->rosterUrl().'/solicitudes/not-a-uuid/aceptar')
            ->assertNotFound();

        $this->assertSame('pending', $this->requestStatus($request));
    }

    public function test_marking_an_absence(): void
    {
        $this->asDirector()
            ->post($this->rosterUrl().'/ausencias', [
                'game_table_id' => $this->first->id,
                'user_id' => $this->john->id,
                'absent' => true,
            ])
            ->assertRedirect($this->rosterUrl())
            ->assertSessionHas('success', 'Ausencia marcada.');

        $this->assertSame('cancelled', $this->rowOf($this->first, $this->john)->status->value);
    }

    public function test_clearing_an_absence(): void
    {
        $this->asDirector()
            ->post($this->rosterUrl().'/ausencias', [
                'game_table_id' => $this->first->id,
                'user_id' => $this->jane->id,
                'absent' => false,
            ])
            ->assertRedirect($this->rosterUrl())
            ->assertSessionHas('success', 'Ausencia quitada.');

        $this->assertSame('confirmed', $this->rowOf($this->first, $this->jane)->status->value);
    }

    public function test_clearing_an_absence_in_a_full_session_is_refused(): void
    {
        $this->first->forceFill(['max_players' => 1])->save();

        $this->asDirector()
            ->post($this->rosterUrl().'/ausencias', [
                'game_table_id' => $this->first->id,
                'user_id' => $this->jane->id,
                'absent' => false,
            ])
            ->assertSessionHas('error', 'La mesa está completa');

        $this->assertSame('cancelled', $this->rowOf($this->first, $this->jane)->status->value);
    }

    public function test_an_absence_in_a_session_of_another_campaign_is_refused(): void
    {
        $other = $this->campaignSession($this->campaign('La torre del mago'), 'Sesión ajena', CarbonImmutable::parse('2026-11-10 18:00:00'));

        $this->asDirector()
            ->post($this->rosterUrl().'/ausencias', [
                'game_table_id' => $other->id,
                'user_id' => $this->john->id,
                'absent' => true,
            ])
            ->assertSessionHas('error', 'Esa sesión no es de esta campaña.');
    }

    public function test_an_absence_toggle_is_validated(): void
    {
        $this->asDirector()
            ->post($this->rosterUrl().'/ausencias', ['game_table_id' => 'x', 'user_id' => 'y'])
            ->assertSessionHasErrors(['game_table_id', 'user_id', 'absent']);
    }

    public function test_someone_who_does_not_direct_the_campaign_cannot_act(): void
    {
        $request = $this->pendingRequest($this->campaign, $this->richard);
        $url = $this->rosterUrl();

        $responses = [
            fn (): TestResponse => $this->actingAs($this->john)->put($url.'/ajustes', ['accepts_new_players' => false, 'roster_access' => 'anyone', 'max_players' => null]),
            fn (): TestResponse => $this->actingAs($this->john)->delete($url.'/jugadores/'.$this->jane->id),
            fn (): TestResponse => $this->actingAs($this->john)->post($url.'/solicitudes/'.$request->id.'/aceptar'),
            fn (): TestResponse => $this->actingAs($this->john)->post($url.'/solicitudes/'.$request->id.'/rechazar'),
            fn (): TestResponse => $this->actingAs($this->john)->post($url.'/ausencias', ['game_table_id' => $this->first->id, 'user_id' => $this->jane->id, 'absent' => false]),
        ];

        foreach ($responses as $response) {
            $response()->assertForbidden();
        }

        $this->assertTrue((bool) $this->campaign->refresh()->accepts_new_players);
        $this->assertTrue($this->roster()->contains(new CampaignId($this->campaign->id), $this->jane->id));
        $this->assertFalse($this->roster()->contains(new CampaignId($this->campaign->id), $this->richard->id));
        $this->assertSame('pending', $this->requestStatus($request));
        $this->assertSame('cancelled', $this->rowOf($this->first, $this->jane)->status->value);
    }

    private function countQueries(callable $callback): int
    {
        $count = 0;
        DB::listen(function () use (&$count): void {
            $count++;
        });
        $before = $count;
        $callback();

        return $count - $before;
    }

    private function invite(CampaignModel $campaign, string $email): void
    {
        $this->app->make(CampaignInvitationServiceInterface::class)->invite($campaign->id, $email, $this->mary->id);
    }

    private function invitationId(CampaignModel $campaign, string $email): string
    {
        return (string) DB::table('gametables_campaign_invitations')->where('campaign_id', $campaign->id)->where('email', $email)->value('id');
    }

    private function asDirector(): self
    {
        return $this->actingAs($this->mary)->from($this->rosterUrl());
    }

    private function rosterUrl(): string
    {
        return '/campanas/mis-campanas/'.$this->campaign->id.'/plantilla';
    }

    private function roster(): CampaignRosterRepositoryInterface
    {
        return $this->app->make(CampaignRosterRepositoryInterface::class);
    }

    private function addMember(UserModel $user, CarbonImmutable $joinedAt): void
    {
        $this->roster()->add(new CampaignId($this->campaign->id), $user->id, $joinedAt);
    }

    private function requestStatus(CampaignJoinRequestModel $request): string
    {
        return $request->refresh()->status->value;
    }

    private function pendingRequest(CampaignModel $campaign, UserModel $user, ?string $message = null): CampaignJoinRequestModel
    {
        return CampaignJoinRequestModel::create([
            'id' => (string) Str::uuid(),
            'campaign_id' => $campaign->id,
            'user_id' => $user->id,
            'status' => 'pending',
            'message' => $message,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function campaign(string $title, array $overrides = []): CampaignModel
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
            'max_players' => 5,
            'roster_access' => 'anyone',
            ...$overrides,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function campaignSession(CampaignModel $campaign, string $title, CarbonImmutable $startsAt, array $overrides = []): GameTableModel
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
            'auto_confirm' => true,
            ...$overrides,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function rosterRow(GameTableModel $session, UserModel $user, array $overrides = []): ParticipantModel
    {
        return ParticipantModel::create([
            'id' => (string) Str::uuid(),
            'game_table_id' => $session->id,
            'user_id' => $user->id,
            'role' => 'player',
            'status' => 'confirmed',
            'from_roster' => true,
            'confirmed_at' => now(),
            ...$overrides,
        ]);
    }

    private function rowOf(GameTableModel $session, UserModel $user): ParticipantModel
    {
        return ParticipantModel::query()
            ->where('game_table_id', $session->id)
            ->where('user_id', $user->id)
            ->sole();
    }
}
