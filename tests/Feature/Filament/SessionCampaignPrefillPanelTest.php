<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Feature\Filament;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Support\Str;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Modules\GameTables\Filament\Resources\GameTableResource;
use Modules\GameTables\Filament\Resources\GameTableResource\Pages\CreateGameTable;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\CampaignModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameSystemModel;
use Tests\Support\Modules\InteractsWithModulePanel;
use Tests\Support\Modules\ModuleTestCase;

/**
 * Choosing the campaign of a session in the panel proposes the format, location and online link the
 * campaign has; what the campaign leaves blank keeps what was typed.
 */
final class SessionCampaignPrefillPanelTest extends ModuleTestCase
{
    use InteractsWithModulePanel;

    protected ?string $moduleName = 'game-tables';

    protected bool $autoEnableModule = true;

    private GameSystemModel $system;

    private UserModel $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registerOnAdminPanel([GameTableResource::class]);
        $this->admin = $this->actingAsPanelAdmin();
        config()->set('modules.settings.game-tables', []);

        $this->system = GameSystemModel::create([
            'id' => (string) Str::uuid(),
            'name' => 'Sistema de prueba',
            'slug' => 'sistema-de-prueba',
            'is_active' => true,
        ]);
    }

    public function test_choosing_a_campaign_proposes_its_format_location_and_link(): void
    {
        $campaign = $this->campaign([
            'table_format' => 'hybrid',
            'location' => 'Local de la asociación',
            'online_url' => 'https://example.com/mesa',
        ]);

        $this->create()
            ->fillForm(['campaign_id' => $campaign->id])
            ->assertFormSet([
                'table_format' => 'hybrid',
                'location' => 'Local de la asociación',
                'online_url' => 'https://example.com/mesa',
            ]);
    }

    public function test_the_proposed_values_can_be_changed(): void
    {
        $campaign = $this->campaign(['table_format' => 'online', 'online_url' => 'https://example.com/mesa']);

        $this->create()
            ->fillForm(['campaign_id' => $campaign->id])
            ->fillForm(['table_format' => 'in_person', 'location' => 'Otra sala'])
            ->assertFormSet(['table_format' => 'in_person', 'location' => 'Otra sala']);
    }

    public function test_what_the_campaign_leaves_blank_keeps_what_was_typed(): void
    {
        $campaign = $this->campaign();

        $this->create()
            ->fillForm([
                'table_format' => 'hybrid',
                'location' => 'Mi casa',
                'online_url' => 'https://example.com/otra',
            ])
            ->fillForm(['campaign_id' => $campaign->id])
            ->assertFormSet([
                'table_format' => 'hybrid',
                'location' => 'Mi casa',
                'online_url' => 'https://example.com/otra',
            ]);
    }

    /**
     * @return Testable<CreateGameTable>
     */
    private function create(): Testable
    {
        return Livewire::test(CreateGameTable::class);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function campaign(array $attributes = []): CampaignModel
    {
        return CampaignModel::create([
            'id' => (string) Str::uuid(),
            'game_system_id' => $this->system->id,
            'created_by' => $this->admin->id,
            'title' => 'Campaña '.Str::random(5),
            'slug' => Str::random(10),
            'frequency' => 'weekly',
            'status' => 'active',
            'max_players' => 5,
            'is_published' => true,
            ...$attributes,
        ]);
    }
}
