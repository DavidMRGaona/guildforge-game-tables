<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Feature\Http\Controllers;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\GameTables\Domain\Repositories\CampaignRosterRepositoryInterface;
use Modules\GameTables\Domain\ValueObjects\CampaignId;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\CampaignJoinRequestModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\CampaignModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameSystemModel;
use Tests\Support\Modules\ModuleTestCase;

/**
 * A player asks to join a campaign, withdraws the request or leaves the roster from the campaign
 * page, whose join box says what they can do.
 */
final class CampaignJoinControllerTest extends ModuleTestCase
{
    protected ?string $moduleName = 'game-tables';

    protected bool $autoEnableModule = true;

    private const string TOO_MANY = 'Has enviado demasiadas solicitudes seguidas. Espera unos minutos y vuelve a intentarlo.';

    private GameSystemModel $system;

    private UserModel $mary;

    private UserModel $john;

    private UserModel $richard;

    private CampaignModel $campaign;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        app('router')->getRoutes()->refreshNameLookups();
        config()->set('modules.settings.game-tables', []);
        $this->travelTo(CarbonImmutable::parse('2026-11-02 10:00:00'));

        $this->mary = UserModel::factory()->create(['name' => 'Mary Major', 'email' => 'mary@example.com']);
        $this->john = UserModel::factory()->create(['name' => 'John Doe', 'email' => 'john@example.com']);
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
            'max_players' => 3,
            'roster_access' => 'anyone',
        ]);
        $this->roster()->add(new CampaignId($this->campaign->id), $this->john->id, CarbonImmutable::now());
    }

    public function test_requesting_to_join(): void
    {
        $this->actingAs($this->richard)
            ->from($this->pageUrl())
            ->post($this->pageUrl().'/solicitud', ['message' => 'Me encantaría jugar.'])
            ->assertRedirect($this->pageUrl())
            ->assertSessionHas('success', 'Solicitud enviada. La dirección te avisará por correo.');

        $request = CampaignJoinRequestModel::query()->where('user_id', $this->richard->id)->sole();
        $this->assertSame('pending', $request->status->value);
        $this->assertSame('Me encantaría jugar.', $request->message);
    }

    public function test_a_refused_request_flashes_the_reason(): void
    {
        $this->campaign->forceFill(['accepts_new_players' => false])->save();

        $this->actingAs($this->richard)
            ->from($this->pageUrl())
            ->post($this->pageUrl().'/solicitud')
            ->assertRedirect($this->pageUrl())
            ->assertSessionHas('error', 'Esta campaña no acepta jugadores nuevos.');

        $this->assertSame(0, CampaignJoinRequestModel::query()->count());
    }

    public function test_a_message_longer_than_500_characters_is_refused(): void
    {
        $this->actingAs($this->richard)
            ->from($this->pageUrl())
            ->post($this->pageUrl().'/solicitud', ['message' => str_repeat('a', 501)])
            ->assertSessionHasErrors(['message' => 'El mensaje no puede superar los 500 caracteres.']);

        $this->assertSame(0, CampaignJoinRequestModel::query()->count());
    }

    public function test_withdrawing_a_request(): void
    {
        $request = $this->pendingRequest($this->richard);

        $this->actingAs($this->richard)
            ->from($this->pageUrl())
            ->delete($this->pageUrl().'/solicitud')
            ->assertRedirect($this->pageUrl())
            ->assertSessionHas('success', 'Solicitud retirada.');

        $this->assertSame('withdrawn', $request->refresh()->status->value);
    }

    public function test_withdrawing_without_a_pending_request_is_refused(): void
    {
        $this->actingAs($this->richard)
            ->from($this->pageUrl())
            ->delete($this->pageUrl().'/solicitud')
            ->assertSessionHas('error', 'Esta solicitud ya no está pendiente.');
    }

    public function test_leaving_the_campaign(): void
    {
        $this->actingAs($this->john)
            ->from($this->pageUrl())
            ->delete($this->pageUrl().'/plantilla')
            ->assertRedirect($this->pageUrl())
            ->assertSessionHas('success', 'Has salido de la campaña.');

        $this->assertFalse($this->roster()->contains(new CampaignId($this->campaign->id), $this->john->id));
    }

    public function test_leaving_without_being_in_the_roster_speaks_to_the_player(): void
    {
        $this->actingAs($this->richard)
            ->from($this->pageUrl())
            ->delete($this->pageUrl().'/plantilla')
            ->assertSessionHas('error', 'No formas parte de la plantilla de esta campaña.');
    }

    public function test_the_first_person_copy_is_translated_in_both_languages(): void
    {
        $this->assertSame('No formas parte de la plantilla de esta campaña.', trans('game-tables::messages.roster.errors.not_member_self', [], 'es'));
        $this->assertSame('You are not part of the roster of this campaign.', trans('game-tables::messages.roster.errors.not_member_self', [], 'en'));
    }

    public function test_an_unknown_campaign_is_not_found(): void
    {
        $this->actingAs($this->richard)
            ->post('/campanas/no-existe/solicitud')
            ->assertNotFound();
    }

    public function test_a_guest_is_sent_to_log_in(): void
    {
        $this->post($this->pageUrl().'/solicitud')->assertRedirect(route('login'));
        $this->delete($this->pageUrl().'/solicitud')->assertRedirect(route('login'));
        $this->delete($this->pageUrl().'/plantilla')->assertRedirect(route('login'));
    }

    public function test_the_sixth_request_in_a_minute_is_rate_limited(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->actingAs($this->richard)
                ->from($this->pageUrl())
                ->post($this->pageUrl().'/solicitud')
                ->assertRedirect($this->pageUrl());
        }

        $this->actingAs($this->richard)
            ->from($this->pageUrl())
            ->post($this->pageUrl().'/solicitud')
            ->assertRedirect($this->pageUrl())
            ->assertSessionHas('error', self::TOO_MANY);

        $this->actingAs($this->richard)
            ->postJson($this->pageUrl().'/solicitud')
            ->assertStatus(429)
            ->assertJson(['message' => self::TOO_MANY]);

        // Per user: someone else still gets through
        $this->actingAs($this->mary)
            ->from($this->pageUrl())
            ->post($this->pageUrl().'/solicitud')
            ->assertSessionHas('error', 'Quien dirige la campaña no puede entrar en la plantilla como jugador.');
    }

    // The join box of the campaign page

    public function test_a_visitor_is_invited_to_log_in(): void
    {
        $this->assertJoinBox(null, 'guest');
    }

    public function test_an_account_may_request(): void
    {
        $this->assertJoinBox($this->richard, 'can_request');
    }

    public function test_a_pending_request_shows_its_date(): void
    {
        $this->pendingRequest($this->richard);

        $this->actingAs($this->richard)
            ->get($this->pageUrl())
            ->assertInertia(fn (Assert $page) => $page
                ->where('joinBox.state', 'pending')
                ->where('joinBox.requested_at', '02/11/2026')
                ->where('joinBox.roster_url', null));
    }

    public function test_a_member_may_leave(): void
    {
        $this->assertJoinBox($this->john, 'member');
    }

    public function test_a_full_roster_admits_no_requests(): void
    {
        $this->campaign->forceFill(['max_players' => 1])->save();

        $this->assertJoinBox($this->richard, 'full');
    }

    public function test_a_campaign_closed_to_new_players(): void
    {
        $this->campaign->forceFill(['accepts_new_players' => false])->save();

        $this->assertJoinBox($this->richard, 'not_accepting');
    }

    public function test_a_finished_campaign(): void
    {
        $this->campaign->forceFill(['status' => 'completed'])->save();

        $this->assertJoinBox($this->richard, 'finished');
    }

    public function test_a_members_only_campaign_for_someone_who_is_not_a_member(): void
    {
        $this->campaign->forceFill(['roster_access' => 'members_only'])->save();

        $this->assertJoinBox($this->richard, 'members_only');
    }

    public function test_the_director_is_sent_to_the_roster(): void
    {
        $this->actingAs($this->mary)
            ->get($this->pageUrl())
            ->assertInertia(fn (Assert $page) => $page
                ->where('joinBox.state', 'director')
                ->where('joinBox.roster_url', route('campaigns.roster', $this->campaign->id))
                ->where('joinBox.pending_requests', 0));
    }

    public function test_the_director_sees_how_many_requests_await_an_answer(): void
    {
        $this->pendingRequest($this->richard);
        $this->pendingRequest(UserModel::factory()->create());
        $this->pendingRequest(UserModel::factory()->create(['anonymized_at' => now()]));

        $this->actingAs($this->mary)
            ->get($this->pageUrl())
            ->assertInertia(fn (Assert $page) => $page
                ->where('joinBox.state', 'director')
                ->where('joinBox.pending_requests', 2));

        // Nobody else learns about the requests: neither a player, nor someone who asked, nor a visitor
        foreach ([$this->john, $this->richard] as $user) {
            $this->actingAs($user)
                ->get($this->pageUrl())
                ->assertInertia(fn (Assert $page) => $page
                    ->where('joinBox.roster_url', null)
                    ->where('joinBox.pending_requests', 0));
        }
        auth()->logout();
        $this->get($this->pageUrl())
            ->assertInertia(fn (Assert $page) => $page
                ->where('joinBox.state', 'guest')
                ->where('joinBox.roster_url', null)
                ->where('joinBox.pending_requests', 0));
    }

    private function assertJoinBox(?UserModel $user, string $state): void
    {
        if ($user instanceof UserModel) {
            $this->actingAs($user);
        }

        $this->get($this->pageUrl())
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Campaigns/Show', shouldExist: false)
                ->where('joinBox.state', $state)
                ->where('joinBox.requested_at', null)
                ->where('joinBox.roster_url', null)
                ->where('joinBox.pending_requests', 0));
    }

    private function pageUrl(): string
    {
        return '/campanas/la-cripta-olvidada';
    }

    private function roster(): CampaignRosterRepositoryInterface
    {
        return $this->app->make(CampaignRosterRepositoryInterface::class);
    }

    private function pendingRequest(UserModel $user): CampaignJoinRequestModel
    {
        return CampaignJoinRequestModel::create([
            'id' => (string) Str::uuid(),
            'campaign_id' => $this->campaign->id,
            'user_id' => $user->id,
            'status' => 'pending',
            'message' => null,
        ]);
    }
}
