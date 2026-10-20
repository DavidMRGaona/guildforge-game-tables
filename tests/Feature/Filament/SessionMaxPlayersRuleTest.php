<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Feature\Filament;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Modules\GameTables\Domain\Repositories\CampaignRosterRepositoryInterface;
use Modules\GameTables\Domain\ValueObjects\CampaignId;
use Modules\GameTables\Filament\Resources\GameTableResource;
use Modules\GameTables\Filament\Resources\GameTableResource\Pages\EditGameTable;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\CampaignModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameSystemModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameTableModel;
use Tests\Support\Modules\InteractsWithModulePanel;
use Tests\Support\Modules\ModuleTestCase;

/**
 * In the panel too a session never has fewer seats than its campaign roster, and choosing a
 * campaign fills a maximum the roster fits in.
 */
final class SessionMaxPlayersRuleTest extends ModuleTestCase
{
    use InteractsWithModulePanel;

    protected ?string $moduleName = 'game-tables';

    protected bool $autoEnableModule = true;

    private GameSystemModel $system;

    private GameTableModel $table;

    private UserModel $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registerOnAdminPanel([GameTableResource::class]);
        $this->admin = $admin = $this->actingAsPanelAdmin();
        config()->set('modules.settings.game-tables', ['defaults' => ['max_players' => 2]]);

        $this->system = GameSystemModel::create([
            'id' => (string) Str::uuid(),
            'name' => 'Sistema de prueba',
            'slug' => 'sistema-de-prueba',
            'is_active' => true,
        ]);
        $this->table = GameTableModel::create([
            'id' => (string) Str::uuid(),
            'game_system_id' => $this->system->id,
            'created_by' => $admin->id,
            'title' => 'Sesión de prueba',
            'slug' => 'sesion-de-prueba',
            'starts_at' => '2030-01-10 18:00:00',
            'duration_minutes' => 240,
            'table_type' => 'campaign_session',
            'table_format' => 'in_person',
            'status' => 'draft',
            'min_players' => 1,
            'max_players' => 5,
            'language' => 'es',
            'experience_level' => 'none',
            'character_creation' => 'pre_generated',
        ]);
    }

    public function test_saving_a_session_below_the_roster_is_refused(): void
    {
        $campaign = $this->campaign(null, 3);

        $component = $this->edit()
            ->fillForm(['campaign_id' => $campaign->id])
            ->fillForm(['max_players' => 2])
            ->call('save')
            ->assertHasFormErrors(['max_players']);

        $this->assertSame(
            ['El máximo de jugadores no puede ser menor que la plantilla de la campaña (3).'],
            $component->errors()->get('data.max_players'),
        );
        $this->assertNull($this->table->refresh()->campaign_id);
    }

    public function test_saving_a_session_that_fits_the_roster_is_accepted(): void
    {
        $campaign = $this->campaign(null, 3);

        $this->edit()
            ->fillForm(['campaign_id' => $campaign->id])
            ->fillForm(['max_players' => 3])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->table->refresh();
        $this->assertSame($campaign->id, $this->table->campaign_id);
        $this->assertSame(3, $this->table->max_players);
    }

    /**
     * The roster may have grown after a session started or ended: editing it is not blocked.
     */
    public function test_a_session_that_started_or_ended_is_left_alone(): void
    {
        $campaign = $this->campaign(null, 3);

        foreach ([
            ['status' => 'completed'],
            ['status' => 'cancelled'],
            ['status' => 'in_progress'],
            ['status' => 'scheduled', 'starts_at' => CarbonImmutable::now()->subHour()],
        ] as $attributes) {
            $this->table->update(['campaign_id' => $campaign->id, 'max_players' => 2, ...$attributes]);

            $this->edit()
                ->fillForm(['max_players' => 2])
                ->call('save')
                ->assertHasNoFormErrors(['max_players']);
        }
    }

    public function test_choosing_a_campaign_sets_its_maximum(): void
    {
        $campaign = $this->campaign(6, 1);

        $this->edit()
            ->fillForm(['campaign_id' => $campaign->id])
            ->assertFormSet(['max_players' => 6]);
    }

    public function test_without_a_campaign_maximum_the_default_never_undercuts_the_roster(): void
    {
        $large = $this->campaign(null, 3);
        $small = $this->campaign(null, 1);

        $this->edit()
            ->fillForm(['campaign_id' => $large->id])
            ->assertFormSet(['max_players' => 3])
            ->fillForm(['campaign_id' => $small->id])
            ->assertFormSet(['max_players' => 2]);
    }

    /**
     * @return Testable<EditGameTable>
     */
    private function edit(): Testable
    {
        return Livewire::test(EditGameTable::class, ['record' => $this->table->getRouteKey()]);
    }

    private function campaign(?int $maxPlayers, int $rosterSize): CampaignModel
    {
        $campaign = CampaignModel::create([
            'id' => (string) Str::uuid(),
            'game_system_id' => $this->system->id,
            'created_by' => $this->admin->id,
            'title' => 'Campaña '.Str::random(5),
            'slug' => Str::random(10),
            'frequency' => 'weekly',
            'status' => 'active',
            'max_players' => $maxPlayers,
            'is_published' => true,
        ]);

        $names = ['John Doe', 'Richard Roe', 'Mary Major'];
        for ($i = 0; $i < $rosterSize; $i++) {
            $member = UserModel::factory()->create([
                'name' => $names[$i % count($names)],
                'email' => Str::random(8).'@example.com',
            ]);
            app(CampaignRosterRepositoryInterface::class)->add(new CampaignId($campaign->id), $member->id, CarbonImmutable::now());
        }

        return $campaign;
    }
}
