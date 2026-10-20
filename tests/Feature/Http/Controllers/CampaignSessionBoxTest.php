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
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\CampaignModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameSystemModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameTableModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\ParticipantModel;
use Tests\Support\Modules\ModuleTestCase;

/**
 * The session page tells a roster member where they stand in the session (enrolled, waiting,
 * absent) and whether they can take their seat back; an outsider learns whether the campaign is
 * closed to them.
 */
final class CampaignSessionBoxTest extends ModuleTestCase
{
    protected ?string $moduleName = 'game-tables';

    protected bool $autoEnableModule = true;

    private GameSystemModel $system;

    private UserModel $mary;

    private UserModel $john;

    private UserModel $jane;

    private UserModel $richard;

    private CampaignModel $campaign;

    private GameTableModel $session;

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
            'roster_access' => 'anyone',
        ]);
        $this->session = $this->table('La cripta olvidada: sesión 1', $this->campaign->id);

        foreach ([$this->john, $this->jane] as $member) {
            app(CampaignRosterRepositoryInterface::class)->add(new CampaignId($this->campaign->id), $member->id, CarbonImmutable::now());
        }
        $this->rosterRow($this->john);
        $this->rosterRow($this->jane, ['status' => 'cancelled', 'cancellation_reason' => 'session_absence']);
    }

    public function test_a_table_without_campaign_has_no_campaign_session(): void
    {
        $table = $this->table('Mesa suelta', null);

        $this->actingAs($this->john)
            ->get('/mesas/'.$table->slug)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('campaignSession', null));
    }

    public function test_an_enrolled_member(): void
    {
        $this->actingAs($this->john)
            ->get('/mesas/'.$this->session->slug)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('campaignSession.campaign_title', 'La cripta olvidada')
                ->where('campaignSession.campaign_slug', 'la-cripta-olvidada')
                ->where('campaignSession.closed', false)
                ->where('campaignSession.is_roster_member', true)
                ->where('campaignSession.roster_state', 'enrolled')
                ->where('campaignSession.waiting_list_position', null)
                ->where('campaignSession.can_rejoin', false)
                ->where('campaignSession.rejoin_refusal', null)
                ->where('userRegistration.fromRoster', true));
    }

    public function test_a_waiting_member_sees_their_place(): void
    {
        ParticipantModel::query()->where('user_id', $this->john->id)->update(['status' => 'waiting_list', 'waiting_list_position' => 4]);

        $this->actingAs($this->john)
            ->get('/mesas/'.$this->session->slug)
            ->assertInertia(fn (Assert $page) => $page
                ->where('campaignSession.roster_state', 'waiting')
                ->where('campaignSession.waiting_list_position', 1));
    }

    public function test_an_absent_member_may_rejoin(): void
    {
        $this->actingAs($this->jane)
            ->get('/mesas/'.$this->session->slug)
            ->assertInertia(fn (Assert $page) => $page
                ->where('campaignSession.is_roster_member', true)
                ->where('campaignSession.roster_state', 'absent')
                ->where('campaignSession.can_rejoin', true)
                ->where('campaignSession.rejoin_refusal', null));
    }

    public function test_a_member_without_a_row_may_join_the_session(): void
    {
        ParticipantModel::query()->where('user_id', $this->jane->id)->delete();

        $this->actingAs($this->jane)
            ->get('/mesas/'.$this->session->slug)
            ->assertInertia(fn (Assert $page) => $page
                ->where('campaignSession.roster_state', 'none')
                ->where('campaignSession.can_rejoin', true));
    }

    public function test_an_absent_member_finds_the_session_full(): void
    {
        $this->session->forceFill(['max_players' => 1])->save();

        $this->actingAs($this->jane)
            ->get('/mesas/'.$this->session->slug)
            ->assertInertia(fn (Assert $page) => $page
                ->where('campaignSession.roster_state', 'absent')
                ->where('campaignSession.can_rejoin', false)
                ->where('campaignSession.rejoin_refusal', 'La mesa está completa'));
    }

    public function test_an_absent_member_finds_registrations_closed(): void
    {
        $this->session->forceFill(['registration_closes_at' => CarbonImmutable::now()->subHour()])->save();

        $this->actingAs($this->jane)
            ->get('/mesas/'.$this->session->slug)
            ->assertInertia(fn (Assert $page) => $page
                ->where('campaignSession.can_rejoin', false)
                ->where('campaignSession.rejoin_refusal', 'Las inscripciones están cerradas'));
    }

    public function test_an_outsider_in_a_closed_campaign(): void
    {
        $this->campaign->forceFill(['accepts_new_players' => false])->save();

        $this->actingAs($this->richard)
            ->get('/mesas/'.$this->session->slug)
            ->assertInertia(fn (Assert $page) => $page
                ->where('campaignSession.closed', true)
                ->where('campaignSession.is_roster_member', false)
                ->where('campaignSession.roster_state', null)
                ->where('campaignSession.can_rejoin', false));
    }

    public function test_a_visitor_sees_the_campaign_but_no_roster_state(): void
    {
        $this->get('/mesas/'.$this->session->slug)
            ->assertInertia(fn (Assert $page) => $page
                ->where('campaignSession.campaign_title', 'La cripta olvidada')
                ->where('campaignSession.is_roster_member', false)
                ->where('campaignSession.roster_state', null));
    }

    public function test_an_unpublished_campaign_has_no_link(): void
    {
        $this->campaign->forceFill(['is_published' => false])->save();

        $this->actingAs($this->john)
            ->get('/mesas/'.$this->session->slug)
            ->assertInertia(fn (Assert $page) => $page
                ->where('campaignSession.campaign_title', 'La cripta olvidada')
                ->where('campaignSession.campaign_slug', null));
    }

    // The registration flashes of a roster member

    public function test_rejoining_flashes_the_roster_message(): void
    {
        $this->actingAs($this->jane)
            ->from('/mesas/'.$this->session->slug)
            ->post('/mesas/'.$this->session->slug.'/inscripcion', ['role' => 'player'])
            ->assertRedirect('/mesas/'.$this->session->slug)
            ->assertSessionHas('success', 'Vuelves a estar inscrito en esta sesión.');
    }

    public function test_cancelling_a_roster_seat_flashes_the_absence_message(): void
    {
        $this->actingAs($this->john)
            ->from('/mesas/'.$this->session->slug)
            ->delete('/mesas/'.$this->session->slug.'/inscripcion')
            ->assertSessionHas('success', 'Te has dado de baja de esta sesión. Sigues en la campaña.');
    }

    public function test_an_outsider_keeps_the_usual_messages(): void
    {
        $this->actingAs($this->richard)
            ->from('/mesas/'.$this->session->slug)
            ->post('/mesas/'.$this->session->slug.'/inscripcion', ['role' => 'player'])
            ->assertSessionHas('success', 'Te has inscrito correctamente');

        $this->actingAs($this->richard)
            ->from('/mesas/'.$this->session->slug)
            ->delete('/mesas/'.$this->session->slug.'/inscripcion')
            ->assertSessionHas('success', 'Inscripción cancelada correctamente');
    }

    public function test_an_ex_member_registering_loosely_keeps_the_usual_message(): void
    {
        // Richard left the roster: his row in the session was cancelled as left_roster
        $this->rosterRow($this->richard, ['status' => 'cancelled', 'cancellation_reason' => 'left_roster']);

        $this->actingAs($this->richard)
            ->from('/mesas/'.$this->session->slug)
            ->post('/mesas/'.$this->session->slug.'/inscripcion', ['role' => 'player'])
            ->assertSessionHas('success', 'Te has inscrito correctamente');

        $row = ParticipantModel::query()->where('user_id', $this->richard->id)->sole();
        $this->assertSame('confirmed', $row->status->value);
        $this->assertFalse($row->from_roster);
    }

    private function table(string $title, ?string $campaignId): GameTableModel
    {
        return GameTableModel::create([
            'id' => (string) Str::uuid(),
            'game_system_id' => $this->system->id,
            'campaign_id' => $campaignId,
            'created_by' => $this->mary->id,
            'title' => $title,
            'slug' => Str::slug($title),
            'starts_at' => CarbonImmutable::parse('2026-11-09 18:00:00'),
            'duration_minutes' => 240,
            'table_type' => $campaignId !== null ? 'campaign_session' : 'one_shot',
            'table_format' => 'in_person',
            'status' => 'scheduled',
            'min_players' => 1,
            'max_players' => 5,
            'max_spectators' => 0,
            'language' => 'es',
            'registration_type' => 'everyone',
            'is_published' => true,
            'auto_confirm' => true,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function rosterRow(UserModel $user, array $overrides = []): ParticipantModel
    {
        return ParticipantModel::create([
            'id' => (string) Str::uuid(),
            'game_table_id' => $this->session->id,
            'user_id' => $user->id,
            'role' => 'player',
            'status' => 'confirmed',
            'from_roster' => true,
            'confirmed_at' => now(),
            ...$overrides,
        ]);
    }
}
