<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Feature\Http\Controllers;

use App\Application\Modules\DTOs\SlotRegistrationDTO;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\GameTables\Application\Services\CampaignInvitationServiceInterface;
use Modules\GameTables\Domain\Events\CampaignInvitationSent;
use Modules\GameTables\Domain\Repositories\CampaignRosterRepositoryInterface;
use Modules\GameTables\Domain\ValueObjects\CampaignId;
use Modules\GameTables\GameTablesServiceProvider;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\CampaignInvitationModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\CampaignModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameSystemModel;
use Tests\Support\Modules\ModuleTestCase;

/**
 * The «Invitaciones» tab of the profile: only for a verified email with pending invitations, and
 * its actions are checked on the server whatever the tab shows.
 */
final class ProfileCampaignInvitationsTest extends ModuleTestCase
{
    protected ?string $moduleName = 'game-tables';

    protected bool $autoEnableModule = true;

    private CampaignModel $campaign;

    private UserModel $mary;

    private UserModel $john;

    protected function setUp(): void
    {
        parent::setUp();

        Event::fake([CampaignInvitationSent::class]);
        app('router')->getRoutes()->refreshNameLookups();
        config()->set('modules.settings.game-tables', []);
        $this->travelTo(CarbonImmutable::parse('2026-11-02 10:00:00'));

        $this->mary = UserModel::factory()->create(['name' => 'Mary Major', 'email' => 'mary@example.com']);
        $this->john = UserModel::factory()->create(['name' => 'John Doe', 'email' => 'john@example.com']);

        $system = GameSystemModel::create(['id' => (string) Str::uuid(), 'name' => 'Sistema', 'slug' => 'sistema', 'is_active' => true]);
        $this->campaign = $this->makeCampaign($system, 'La cripta olvidada', 'la-cripta-olvidada');
    }

    public function test_the_tab_is_hidden_without_invitations(): void
    {
        $this->actingAs($this->john)
            ->get('/perfil')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('profileCampaignInvitationsVisible', false)
                ->where('profileCampaignInvitationsTotal', 0));
    }

    public function test_the_tab_is_hidden_for_an_unverified_email(): void
    {
        $ana = UserModel::factory()->unverified()->create(['email' => 'ana@example.com']);
        $this->invite('ana@example.com');

        $this->actingAs($ana)
            ->get('/perfil')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('profileCampaignInvitationsVisible', false)
                ->where('profileCampaignInvitationsTotal', null)
                ->where('profileCampaignInvitations', null));
    }

    public function test_verified_account_sees_its_invitations_including_those_sent_before_it_registered(): void
    {
        $this->invite('Late@Example.com');
        $this->invite('john@example.com');
        $late = UserModel::factory()->create(['name' => 'Late Comer', 'email' => 'late@example.com']);

        $this->actingAs($late)
            ->get('/perfil')
            ->assertInertia(fn (Assert $page) => $page
                ->where('profileCampaignInvitationsVisible', true)
                ->where('profileCampaignInvitationsTotal', 1)
                ->has('profileCampaignInvitations.invitations', 1)
                ->where('profileCampaignInvitations.total', 1)
                ->where('profileCampaignInvitations.invitations.0.campaign_title', 'La cripta olvidada')
                ->where('profileCampaignInvitations.invitations.0.campaign_slug', 'la-cripta-olvidada')
                ->where('profileCampaignInvitations.invitations.0.inviter_name', 'Mary Major')
                ->has('profileCampaignInvitations.invitations.0.id')
                ->has('profileCampaignInvitations.invitations.0.expires_at')
                ->missing('profileCampaignInvitations.invitations.0.email')
                ->missing('profileCampaignInvitations.invitations.0.send_count')
                ->missing('profileCampaignInvitations.invitations.0.can_resend'));
    }

    public function test_the_slot_registers_the_tab_with_its_keys(): void
    {
        $provider = app()->getProvider(GameTablesServiceProvider::class);
        $this->assertInstanceOf(GameTablesServiceProvider::class, $provider);
        $slot = collect($provider->registerSlots())
            ->first(fn (SlotRegistrationDTO $s): bool => ($s->profileTab['tabId'] ?? null) === 'gametables-invitations');

        $this->assertNotNull($slot);
        $this->assertSame('profile-sections', $slot->slot);
        $this->assertSame(
            ['profileCampaignInvitations', 'profileCampaignInvitationsTotal', 'profileCampaignInvitationsVisible'],
            $slot->dataKeys,
        );
        $this->assertNotNull($slot->profileTab);
        $this->assertSame('profileCampaignInvitationsVisible', $slot->profileTab['visibleKey']);
        $this->assertSame('profileCampaignInvitationsTotal', $slot->profileTab['badgeKey']);
    }

    public function test_the_tab_sits_with_the_game_tables_tabs(): void
    {
        $provider = app()->getProvider(GameTablesServiceProvider::class);
        $this->assertInstanceOf(GameTablesServiceProvider::class, $provider);

        // The profile lists every module's tabs by order alone; parentId only indents them
        $orders = collect($provider->registerSlots())
            ->filter(fn (SlotRegistrationDTO $s): bool => $s->slot === 'profile-sections')
            ->mapWithKeys(fn (SlotRegistrationDTO $s): array => [(string) ($s->profileTab['tabId'] ?? '') => $s->order]);

        $this->assertSame(
            ['gametables', 'gametables-created', 'gametables-campaigns', 'gametables-invitations'],
            $orders->sort()->keys()->all(),
        );
        // Other modules' profile tabs (tournaments, venue bookings) start at 20
        $this->assertLessThan(20, $orders->max());
    }

    public function test_accept_and_reject_from_the_profile(): void
    {
        $system = GameSystemModel::query()->firstOrFail();
        $second = $this->makeCampaign($system, 'Las minas', 'las-minas');
        $this->invite('john@example.com');
        $this->invite('john@example.com', $second);

        $accept = CampaignInvitationModel::query()->where('campaign_id', $this->campaign->id)->firstOrFail();
        $reject = CampaignInvitationModel::query()->where('campaign_id', $second->id)->firstOrFail();

        $this->actingAs($this->john)
            ->post('/campanas/invitaciones/perfil/'.$accept->id.'/aceptar')
            ->assertRedirect('/perfil?tab=gametables-invitations')
            ->assertSessionHas('success', __('game-tables::messages.roster.success.invitation_accepted'));

        $this->assertTrue($this->isMember($this->john, $this->campaign));

        $this->actingAs($this->john)
            ->post('/campanas/invitaciones/perfil/'.$reject->id.'/rechazar')
            ->assertRedirect('/perfil?tab=gametables-invitations')
            ->assertSessionHas('success', __('game-tables::messages.roster.success.invitation_rejected'));

        $this->assertFalse($this->isMember($this->john, $second));
        $this->assertSame(0, CampaignInvitationModel::query()->count());
    }

    public function test_the_tab_is_hidden_after_accepting_the_last_invitation(): void
    {
        $this->invite('john@example.com');
        $invitation = CampaignInvitationModel::query()->firstOrFail();

        $this->actingAs($this->john)
            ->get('/perfil')
            ->assertInertia(fn (Assert $page) => $page->where('profileCampaignInvitationsVisible', true));

        $this->actingAs($this->john)->post('/campanas/invitaciones/perfil/'.$invitation->id.'/aceptar');

        $this->actingAs($this->john)
            ->get('/perfil?tab=gametables-invitations')
            ->assertInertia(fn (Assert $page) => $page
                ->where('profileCampaignInvitationsVisible', false)
                ->where('profileCampaignInvitationsTotal', 0));
    }

    public function test_the_server_refuses_an_unverified_account_even_when_forced(): void
    {
        $ana = UserModel::factory()->unverified()->create(['email' => 'ana@example.com']);
        $this->invite('ana@example.com');
        $invitation = CampaignInvitationModel::query()->firstOrFail();

        $this->actingAs($ana)
            ->post('/campanas/invitaciones/perfil/'.$invitation->id.'/aceptar')
            ->assertSessionHas('error', __('game-tables::messages.roster.errors.email_not_verified'));

        $this->actingAs($ana)
            ->post('/campanas/invitaciones/perfil/'.$invitation->id.'/rechazar')
            ->assertSessionHas('error', __('game-tables::messages.roster.errors.email_not_verified'));

        $this->assertFalse($this->isMember($ana, $this->campaign));
        $this->assertSame(1, CampaignInvitationModel::query()->count());
    }

    public function test_an_invitation_of_another_email_is_not_found(): void
    {
        $this->invite('jane@example.com');
        $invitation = CampaignInvitationModel::query()->firstOrFail();

        $this->actingAs($this->john)
            ->post('/campanas/invitaciones/perfil/'.$invitation->id.'/aceptar')
            ->assertSessionHas('error', __('game-tables::messages.roster.errors.invitation_not_found'));

        $this->actingAs($this->john)
            ->post('/campanas/invitaciones/perfil/'.$invitation->id.'/rechazar')
            ->assertSessionHas('error', __('game-tables::messages.roster.errors.invitation_not_found'));

        $this->assertFalse($this->isMember($this->john, $this->campaign));
        $this->assertSame(1, CampaignInvitationModel::query()->count());
    }

    public function test_the_profile_actions_need_an_account(): void
    {
        $this->invite('john@example.com');
        $invitation = CampaignInvitationModel::query()->firstOrFail();

        $this->post('/campanas/invitaciones/perfil/'.$invitation->id.'/aceptar')->assertRedirect(route('login'));
        $this->post('/campanas/invitaciones/perfil/'.$invitation->id.'/rechazar')->assertRedirect(route('login'));

        $this->assertSame(1, CampaignInvitationModel::query()->count());
    }

    public function test_pending_for_email_skips_expired_and_lists_oldest_first(): void
    {
        $system = GameSystemModel::query()->firstOrFail();
        $second = $this->makeCampaign($system, 'Las minas', 'las-minas');
        $third = $this->makeCampaign($system, 'El faro', 'el-faro');
        $this->invite('john@example.com');
        $this->travel(1)->hours();
        $this->invite('john@example.com', $second);
        $this->invite('john@example.com', $third);
        CampaignInvitationModel::query()->where('campaign_id', $third->id)
            ->update(['expires_at' => CarbonImmutable::parse('2026-11-01 10:00:00')]);

        $views = app(CampaignInvitationServiceInterface::class)
            ->pendingForEmail(' John@Example.com ', CarbonImmutable::now()->toDateTimeImmutable());

        $this->assertSame(['La cripta olvidada', 'Las minas'], array_map(fn ($v) => $v->campaignTitle, $views));
    }

    private function makeCampaign(GameSystemModel $system, string $title, string $slug): CampaignModel
    {
        return CampaignModel::create([
            'id' => (string) Str::uuid(),
            'game_system_id' => $system->id,
            'created_by' => $this->mary->id,
            'title' => $title,
            'slug' => $slug,
            'status' => 'active',
            'frontend_creation_status' => 'approved',
            'is_published' => true,
            'accepts_new_players' => true,
            'max_players' => 5,
            'roster_access' => 'anyone',
        ]);
    }

    private function invite(string $email, ?CampaignModel $campaign = null): void
    {
        $this->app->make(CampaignInvitationServiceInterface::class)
            ->invite(($campaign ?? $this->campaign)->id, $email, $this->mary->id);
    }

    private function isMember(UserModel $user, CampaignModel $campaign): bool
    {
        return $this->app->make(CampaignRosterRepositoryInterface::class)->contains(new CampaignId($campaign->id), $user->id);
    }
}
