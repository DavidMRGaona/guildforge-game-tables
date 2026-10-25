<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Feature\Http\Controllers;

use App\Application\Services\SettingsServiceInterface;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Illuminate\Support\Testing\Fakes\EventFake;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\GameTables\Application\Services\CampaignInvitationServiceInterface;
use Modules\GameTables\Domain\Events\CampaignInvitationSent;
use Modules\GameTables\Domain\Repositories\CampaignRosterRepositoryInterface;
use Modules\GameTables\Domain\ValueObjects\CampaignId;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\CampaignInvitationModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\CampaignModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameSystemModel;
use Tests\Support\Modules\ModuleTestCase;

/**
 * The page behind the email link: viewing and rejecting need only the token, accepting needs an
 * account with the invited email (and a verified one when the site requires it). What the page
 * shows depends on the session, and never reveals the invited email to another account.
 */
final class CampaignInvitationControllerTest extends ModuleTestCase
{
    protected ?string $moduleName = 'game-tables';

    protected bool $autoEnableModule = true;

    private CampaignModel $campaign;

    private UserModel $mary;

    private UserModel $john;

    private UserModel $jane;

    protected function setUp(): void
    {
        parent::setUp();

        Event::fake([CampaignInvitationSent::class]);
        app('router')->getRoutes()->refreshNameLookups();
        config()->set('modules.settings.game-tables', []);
        $this->travelTo(CarbonImmutable::parse('2026-11-02 10:00:00'));

        $this->mary = UserModel::factory()->create(['name' => 'Mary Major', 'email' => 'mary@example.com']);
        $this->john = UserModel::factory()->create(['name' => 'John Doe', 'email' => 'john@example.com']);
        $this->jane = UserModel::factory()->create(['name' => 'Jane Doe', 'email' => 'jane@example.com']);

        $system = GameSystemModel::create(['id' => (string) Str::uuid(), 'name' => 'Sistema', 'slug' => 'sistema', 'is_active' => true]);
        $this->campaign = CampaignModel::create([
            'id' => (string) Str::uuid(),
            'game_system_id' => $system->id,
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
    }

    public function test_guest_sees_the_invitation_and_the_email_to_use(): void
    {
        $token = $this->tokenFor('john@example.com');

        $this->get('/campanas/invitaciones/'.$token)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Campaigns/Invitation', shouldExist: false)
                ->where('state', 'guest')
                ->where('invitation.campaign_title', 'La cripta olvidada')
                ->where('invitation.campaign_slug', 'la-cripta-olvidada')
                ->where('invitation.inviter_name', 'Mary Major')
                ->where('invitation.email', 'john@example.com')
                ->has('invitation.expires_at')
                ->missing('invitation.id')
                ->missing('invitation.send_count')
                ->where('seo.robots', 'noindex, nofollow'));
    }

    public function test_the_login_link_comes_back_to_the_invitation(): void
    {
        $token = $this->tokenFor('john@example.com');
        $show = route('campaigns.invitation.show', $token);

        $this->get('/campanas/invitaciones/'.$token.'/iniciar-sesion')
            ->assertRedirect(route('login'))
            ->assertSessionHas('url.intended', $show);

        $this->post(route('login'), ['email' => 'john@example.com', 'password' => 'password'])
            ->assertRedirect($show);
    }

    public function test_the_register_link_comes_back_to_the_invitation(): void
    {
        $token = $this->tokenFor('john@example.com');

        $this->get('/campanas/invitaciones/'.$token.'/crear-cuenta')
            ->assertRedirect(route('register'))
            ->assertSessionHas('url.intended', route('campaigns.invitation.show', $token));
    }

    public function test_signing_out_from_the_invitation_returns_to_it(): void
    {
        $token = $this->tokenFor('john@example.com');

        $this->actingAs($this->jane)
            ->post('/campanas/invitaciones/'.$token.'/cerrar-sesion')
            ->assertRedirect(route('campaigns.invitation.show', $token));

        $this->assertGuest();
    }

    public function test_signing_out_as_a_guest_is_harmless(): void
    {
        $token = $this->tokenFor('john@example.com');

        $this->post('/campanas/invitaciones/'.$token.'/cerrar-sesion')
            ->assertRedirect(route('campaigns.invitation.show', $token));

        $this->assertGuest();
    }

    public function test_signing_out_with_a_malformed_token_is_not_found(): void
    {
        $this->actingAs($this->jane)
            ->post('/campanas/invitaciones/not-a-token/cerrar-sesion')
            ->assertNotFound();

        $this->assertAuthenticatedAs($this->jane);
    }

    public function test_matching_account_accepts(): void
    {
        $token = $this->tokenFor('john@example.com');

        $this->actingAs($this->john)
            ->get('/campanas/invitaciones/'.$token)
            ->assertInertia(fn (Assert $page) => $page
                ->where('state', 'match')
                ->where('invitation.email', 'john@example.com'));

        $this->actingAs($this->john)
            ->post('/campanas/invitaciones/'.$token.'/aceptar')
            ->assertRedirect(route('campaigns.show', 'la-cripta-olvidada'))
            ->assertSessionHas('success', __('game-tables::messages.roster.success.invitation_accepted'));

        $this->assertTrue($this->isMember($this->john));
        $this->assertSame(0, CampaignInvitationModel::query()->count());
    }

    public function test_accepting_needs_an_account(): void
    {
        $token = $this->tokenFor('john@example.com');

        $this->post('/campanas/invitaciones/'.$token.'/aceptar')->assertRedirect(route('login'));

        $this->assertSame(1, CampaignInvitationModel::query()->count());
    }

    public function test_another_account_sees_mismatch_without_the_email(): void
    {
        $token = $this->tokenFor('john@example.com');

        $this->actingAs($this->jane)
            ->get('/campanas/invitaciones/'.$token)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('state', 'mismatch')
                ->where('invitation.campaign_title', 'La cripta olvidada')
                ->where('invitation.campaign_slug', 'la-cripta-olvidada')
                ->where('invitation.inviter_name', 'Mary Major')
                ->missing('invitation.email')
                ->missing('invitation.id')
                ->missing('invitation.send_count')
                ->missing('invitation.expires_at'));
    }

    public function test_another_account_cannot_accept(): void
    {
        $token = $this->tokenFor('john@example.com');

        $this->actingAs($this->jane)
            ->post('/campanas/invitaciones/'.$token.'/aceptar')
            ->assertSessionHas('error', __('game-tables::messages.roster.errors.invitation_other_account'));

        $this->assertFalse($this->isMember($this->jane));
        $this->assertSame(1, CampaignInvitationModel::query()->count());
    }

    public function test_unverified_account_must_verify_when_required(): void
    {
        $this->requireVerification();
        $unverified = UserModel::factory()->unverified()->create(['email' => 'ana@example.com']);
        $token = $this->tokenFor('ana@example.com');

        $this->actingAs($unverified)
            ->get('/campanas/invitaciones/'.$token)
            ->assertInertia(fn (Assert $page) => $page
                ->where('state', 'must_verify')
                ->where('invitation.email', 'ana@example.com'));

        $this->actingAs($unverified)
            ->post('/campanas/invitaciones/'.$token.'/aceptar')
            ->assertRedirect(route('verification.notice'));

        $this->assertFalse($this->isMember($unverified));
    }

    public function test_the_verification_link_comes_back_to_the_invitation(): void
    {
        $this->requireVerification();
        $unverified = UserModel::factory()->unverified()->create(['email' => 'ana@example.com']);
        $token = $this->tokenFor('ana@example.com');
        $show = route('campaigns.invitation.show', $token);

        $this->actingAs($unverified)
            ->get('/campanas/invitaciones/'.$token.'/verificar')
            ->assertRedirect(route('verification.notice'))
            ->assertSessionHas('url.intended', $show);

        // Once verified, the core sends the account back to the page it was asked for
        $unverified->markEmailAsVerified();
        $this->actingAs($unverified)
            ->get(route('verification.notice'))
            ->assertRedirect($show);
    }

    public function test_unverified_account_accepts_when_not_required(): void
    {
        app(SettingsServiceInterface::class)->set('auth_email_verification_required', '0');
        $unverified = UserModel::factory()->unverified()->create(['email' => 'ana@example.com']);
        $token = $this->tokenFor('ana@example.com');

        $this->actingAs($unverified)
            ->get('/campanas/invitaciones/'.$token)
            ->assertInertia(fn (Assert $page) => $page->where('state', 'match'));

        $this->actingAs($unverified)
            ->post('/campanas/invitaciones/'.$token.'/aceptar')
            ->assertRedirect(route('campaigns.show', 'la-cripta-olvidada'));

        $this->assertTrue($this->isMember($unverified));
    }

    public function test_a_guest_can_reject(): void
    {
        $token = $this->tokenFor('nobody@example.com');

        $this->post('/campanas/invitaciones/'.$token.'/rechazar')
            ->assertRedirect(route('campaigns.invitation.show', $token))
            ->assertSessionHas('success', __('game-tables::messages.roster.success.invitation_rejected'));

        $this->assertSame(0, CampaignInvitationModel::query()->count());
    }

    public function test_after_rejecting_the_page_shows_the_rejected_state_once(): void
    {
        $token = $this->tokenFor('nobody@example.com');

        $this->followingRedirects()
            ->post('/campanas/invitaciones/'.$token.'/rechazar')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('state', 'rejected')
                ->where('invitation', null));

        // Only right after rejecting: the link opened later is just no longer valid
        $this->get('/campanas/invitaciones/'.$token)
            ->assertInertia(fn (Assert $page) => $page->where('state', 'invalid')->where('invitation', null));
    }

    public function test_a_failed_rejection_does_not_show_the_rejected_state(): void
    {
        $this->followingRedirects()
            ->post('/campanas/invitaciones/'.Str::random(64).'/rechazar')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('state', 'invalid'));
    }

    public function test_an_expired_or_unknown_token_shows_the_generic_message(): void
    {
        $token = $this->tokenFor('john@example.com');

        $this->get('/campanas/invitaciones/'.Str::random(64))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('state', 'invalid')->where('invitation', null));

        CampaignInvitationModel::query()->update(['expires_at' => CarbonImmutable::parse('2026-11-01 10:00:00')]);

        $this->actingAs($this->john)
            ->get('/campanas/invitaciones/'.$token)
            ->assertInertia(fn (Assert $page) => $page->where('state', 'invalid')->where('invitation', null));
    }

    public function test_a_malformed_token_shows_the_generic_message(): void
    {
        $queries = 0;
        DB::listen(function () use (&$queries): void {
            $queries++;
        });

        $this->get('/campanas/invitaciones/'.Str::random(10))->assertNotFound();
        $this->get('/campanas/invitaciones/'.str_repeat('a', 63).'%')->assertNotFound();
        $this->post('/campanas/invitaciones/'.Str::random(10).'/rechazar')->assertNotFound();

        $this->assertSame(0, $queries);
    }

    public function test_the_page_is_limited_per_ip(): void
    {
        $token = $this->tokenFor('john@example.com');

        for ($i = 0; $i < 30; $i++) {
            $this->get('/campanas/invitaciones/'.$token)->assertOk();
        }

        $this->get('/campanas/invitaciones/'.$token)->assertStatus(429);
        $this->getJson('/campanas/invitaciones/'.$token)->assertStatus(429)->assertJsonStructure(['message']);
    }

    private function requireVerification(): void
    {
        app(SettingsServiceInterface::class)->set('email_verification_enforced_since', '2026-01-01T00:00:00+00:00');
        app(SettingsServiceInterface::class)->set('auth_email_verification_required', '1');
    }

    private function isMember(UserModel $user): bool
    {
        return $this->app->make(CampaignRosterRepositoryInterface::class)->contains(new CampaignId($this->campaign->id), $user->id);
    }

    /**
     * Invites the email and returns the plain token the email would carry.
     */
    private function tokenFor(string $email): string
    {
        $this->app->make(CampaignInvitationServiceInterface::class)->invite($this->campaign->id, $email, $this->mary->id);

        $events = Event::getFacadeRoot();
        $this->assertInstanceOf(EventFake::class, $events);
        $last = $events->dispatched(CampaignInvitationSent::class)
            ->map(fn (array $args): CampaignInvitationSent => $args[0])
            ->filter(fn (CampaignInvitationSent $event): bool => $event->email === mb_strtolower(trim($email)))
            ->last();
        $this->assertNotNull($last);

        return $last->token;
    }
}
