<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Feature\Http\Controllers;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\CampaignModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameSystemModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameTableModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\ParticipantModel;
use Tests\Support\Modules\ModuleTestCase;

/**
 * The public routes refuse outsiders (users and guests) in the sessions of a closed campaign.
 */
final class ClosedCampaignRegistrationTest extends ModuleTestCase
{
    protected ?string $moduleName = 'game-tables';

    protected bool $autoEnableModule = true;

    private GameTableModel $session;

    protected function setUp(): void
    {
        parent::setUp();

        app('router')->getRoutes()->refreshNameLookups();
        config()->set('modules.settings.game-tables', []);

        $system = GameSystemModel::create([
            'id' => (string) Str::uuid(),
            'name' => 'Sistema de prueba',
            'slug' => 'sistema-de-prueba',
            'is_active' => true,
        ]);
        $owner = UserModel::factory()->create();
        $campaign = CampaignModel::create([
            'id' => (string) Str::uuid(),
            'game_system_id' => $system->id,
            'created_by' => $owner->id,
            'title' => 'Campaña de prueba',
            'slug' => 'campana-de-prueba',
            'status' => 'on_hold',
            'frontend_creation_status' => 'approved',
            'is_published' => true,
            'accepts_new_players' => true,
            'roster_access' => 'anyone',
        ]);
        $this->session = GameTableModel::create([
            'id' => (string) Str::uuid(),
            'game_system_id' => $system->id,
            'campaign_id' => $campaign->id,
            'created_by' => $owner->id,
            'title' => 'Sesión de prueba',
            'slug' => 'sesion-de-prueba',
            'starts_at' => now()->addWeek(),
            'duration_minutes' => 240,
            'table_type' => 'campaign_session',
            'table_format' => 'in_person',
            'status' => 'scheduled',
            'min_players' => 1,
            'max_players' => 5,
            'max_spectators' => 0,
            'language' => 'es',
            'registration_type' => 'everyone',
            'auto_confirm' => true,
            'is_published' => true,
        ]);
    }

    public function test_a_user_post_to_a_closed_campaign_session_is_refused(): void
    {
        $this->actingAs(UserModel::factory()->create());

        $this->postJson(route('gametables.register', $this->session->slug), ['role' => 'player'])
            ->assertStatus(422)
            ->assertJsonPath('errors.registration.0', 'Solo para la plantilla de la campaña');

        $this->assertSame(0, $this->rows());
    }

    public function test_a_guest_post_to_a_closed_campaign_session_is_refused(): void
    {
        $this->postJson(route('gametables.register-guest', $this->session->slug), [
            'first_name' => 'John',
            'email' => 'john@example.com',
            'role' => 'player',
            'gdpr_consent' => true,
        ])->assertStatus(422);

        $this->assertSame(0, $this->rows());
    }

    public function test_the_page_offers_no_guest_form(): void
    {
        $this->get(route('gametables.show', $this->session->slug))
            ->assertInertia(fn (Assert $page) => $page
                ->where('guestEligibility.eligible', false)
                ->where('guestEligibility.reason', 'campaign_roster_only')
                ->where('guestEligibility.message', 'Solo para la plantilla de la campaña'));
    }

    private function rows(): int
    {
        return ParticipantModel::query()->where('game_table_id', $this->session->id)->count();
    }
}
