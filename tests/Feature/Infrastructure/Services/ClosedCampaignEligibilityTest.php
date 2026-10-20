<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Feature\Infrastructure\Services;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Modules\GameTables\Application\Services\EligibilityServiceInterface;
use Modules\GameTables\Domain\Repositories\CampaignRosterRepositoryInterface;
use Modules\GameTables\Domain\ValueObjects\CampaignId;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\CampaignModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameSystemModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameTableModel;
use Tests\Support\Modules\ModuleTestCase;

/**
 * The sessions of a campaign that takes no new players are for its roster: outsiders (users and
 * guests) are refused with their own reason.
 */
final class ClosedCampaignEligibilityTest extends ModuleTestCase
{
    protected ?string $moduleName = 'game-tables';

    protected bool $autoEnableModule = true;

    private GameSystemModel $system;

    private UserModel $john;

    private UserModel $jane;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('modules.settings.game-tables', []);
        $this->travelTo(CarbonImmutable::parse('2026-11-02 10:00:00'));
        $this->system = GameSystemModel::create([
            'id' => (string) Str::uuid(),
            'name' => 'Sistema de prueba',
            'slug' => 'sistema-de-prueba',
            'is_active' => true,
        ]);
        $this->john = UserModel::factory()->create();
        $this->jane = UserModel::factory()->create();
    }

    public function test_an_outsider_is_refused_as_player_and_spectator_when_the_campaign_is_on_hold(): void
    {
        $session = $this->sessionOf($this->campaign(['status' => 'on_hold']));

        $result = $this->eligibility()->canRegisterById($session->id, $this->john->id);

        $this->assertFalse($result['eligible']);
        $this->assertSame('campaign_roster_only', $result['reason']);
        $this->assertSame('Solo para la plantilla de la campaña', $result['message']);
        $this->assertFalse($result['spectator']['eligible']);
        $this->assertSame('campaign_roster_only', $result['spectator']['reason']);
        $this->assertSame('Solo para la plantilla de la campaña', $result['spectator']['message']);
    }

    public function test_an_outsider_is_refused_when_the_campaign_does_not_accept_new_players(): void
    {
        $session = $this->sessionOf($this->campaign(['status' => 'active', 'accepts_new_players' => false]));

        $result = $this->eligibility()->canRegisterById($session->id, $this->john->id);

        $this->assertSame('campaign_roster_only', $result['reason']);
    }

    public function test_a_roster_member_is_not_refused_by_the_closed_campaign(): void
    {
        $campaign = $this->campaign(['status' => 'on_hold']);
        $session = $this->sessionOf($campaign);
        $this->app->make(CampaignRosterRepositoryInterface::class)
            ->add(new CampaignId($campaign->id), $this->jane->id, CarbonImmutable::now()->toDateTimeImmutable());

        $result = $this->eligibility()->canRegisterById($session->id, $this->jane->id);

        $this->assertNotSame('campaign_roster_only', $result['reason']);
        $this->assertNotSame('campaign_roster_only', $result['spectator']['reason']);
        $this->assertTrue($result['spectator']['eligible']);
    }

    public function test_an_open_campaign_lets_the_outsider_through_the_normal_rules(): void
    {
        $session = $this->sessionOf($this->campaign(['status' => 'active', 'accepts_new_players' => true]));

        $result = $this->eligibility()->canRegisterById($session->id, $this->john->id);

        $this->assertTrue($result['eligible']);
        $this->assertNull($result['reason']);
    }

    public function test_guest_eligibility_reports_the_closed_campaign(): void
    {
        $session = $this->sessionOf($this->campaign(['status' => 'on_hold']));

        $result = $this->eligibility()->guestEligibility($session->id);

        $this->assertFalse($result['eligible']);
        $this->assertSame('campaign_roster_only', $result['reason']);
        $this->assertSame('Solo para la plantilla de la campaña', $result['message']);
        $this->assertSame('campaign_roster_only', $result['spectator']['reason']);
    }

    public function test_a_table_without_campaign_is_unaffected(): void
    {
        $session = $this->sessionOf(null);

        $this->assertTrue($this->eligibility()->canRegisterById($session->id, $this->john->id)['eligible']);
        $this->assertTrue($this->eligibility()->guestEligibility($session->id)['eligible']);
    }

    private function eligibility(): EligibilityServiceInterface
    {
        return $this->app->make(EligibilityServiceInterface::class);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function campaign(array $attributes = []): CampaignModel
    {
        return CampaignModel::create([
            'id' => (string) Str::uuid(),
            'game_system_id' => $this->system->id,
            'created_by' => UserModel::factory()->create()->id,
            'title' => 'Campaña de prueba',
            'slug' => 'campana-de-prueba-'.Str::lower(Str::random(6)),
            'status' => 'active',
            'frontend_creation_status' => 'approved',
            'is_published' => true,
            'accepts_new_players' => true,
            'roster_access' => 'anyone',
            ...$attributes,
        ]);
    }

    private function sessionOf(?CampaignModel $campaign): GameTableModel
    {
        return GameTableModel::create([
            'id' => (string) Str::uuid(),
            'game_system_id' => $this->system->id,
            'campaign_id' => $campaign?->id,
            'created_by' => UserModel::factory()->create()->id,
            'title' => 'Sesión de prueba',
            'slug' => 'sesion-de-prueba-'.Str::lower(Str::random(6)),
            'starts_at' => CarbonImmutable::now()->addWeek(),
            'duration_minutes' => 240,
            'table_type' => $campaign === null ? 'one_shot' : 'campaign_session',
            'table_format' => 'in_person',
            'status' => 'scheduled',
            'min_players' => 1,
            'max_players' => 5,
            'max_spectators' => 2,
            'language' => 'es',
            'registration_type' => 'everyone',
            'auto_confirm' => true,
            'is_published' => true,
        ]);
    }
}
