<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Feature\Filament;

use Illuminate\Support\Str;
use Livewire\Livewire;
use Modules\GameTables\Filament\Resources\CampaignResource;
use Modules\GameTables\Filament\Resources\CampaignResource\Pages\EditCampaign;
use Modules\GameTables\Filament\Resources\GameTableResource;
use Modules\GameTables\Filament\Resources\GameTableResource\Pages\EditGameTable;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\CampaignModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameSystemModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameTableModel;
use Tests\Support\Modules\InteractsWithModulePanel;
use Tests\Support\Modules\ModuleTestCase;

/**
 * The panel asks for where a table or campaign is played as the web does: the location in person,
 * the link online and at least one of the two when hybrid (or, for a campaign, without a format).
 */
final class PanelPlaceRequirementTest extends ModuleTestCase
{
    use InteractsWithModulePanel;

    protected ?string $moduleName = 'game-tables';

    protected bool $autoEnableModule = true;

    private GameSystemModel $gameSystem;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registerOnAdminPanel([GameTableResource::class, CampaignResource::class], []);
        $this->actingAsPanelAdmin();
        $this->gameSystem = GameSystemModel::create([
            'id' => (string) Str::uuid(),
            'name' => 'Sistema de prueba',
            'slug' => 'sistema-de-prueba',
            'is_active' => true,
        ]);
    }

    public function test_an_in_person_table_needs_its_location(): void
    {
        $table = $this->table('in_person');

        Livewire::test(EditGameTable::class, ['record' => $table->getRouteKey()])
            ->fillForm(['location' => ''])
            ->call('save')
            ->assertHasFormErrors(['location' => 'required']);
    }

    public function test_an_online_table_needs_its_link(): void
    {
        $table = $this->table('online');

        Livewire::test(EditGameTable::class, ['record' => $table->getRouteKey()])
            ->fillForm(['online_url' => ''])
            ->call('save')
            ->assertHasFormErrors(['online_url' => 'required']);
    }

    public function test_a_hybrid_table_needs_one_of_the_two(): void
    {
        $table = $this->table('hybrid');

        Livewire::test(EditGameTable::class, ['record' => $table->getRouteKey()])
            ->fillForm(['location' => '', 'online_url' => ''])
            ->call('save')
            ->assertHasFormErrors(['location' => 'required'])
            ->fillForm(['online_url' => 'https://example.com/mesa'])
            ->call('save')
            ->assertHasNoFormErrors();
    }

    public function test_a_campaign_without_format_needs_one_of_the_two(): void
    {
        $campaign = CampaignModel::create([
            'id' => (string) Str::uuid(),
            'game_system_id' => $this->gameSystem->id,
            'created_by' => auth()->id(),
            'title' => 'Campaña antigua',
            'slug' => 'campana-antigua',
            'status' => 'recruiting',
        ]);

        Livewire::test(EditCampaign::class, ['record' => $campaign->getRouteKey()])
            ->call('save')
            ->assertHasFormErrors(['location' => 'required'])
            ->fillForm(['location' => 'Local de la asociación'])
            ->call('save')
            ->assertHasNoFormErrors();
    }

    private function table(string $format): GameTableModel
    {
        return GameTableModel::create([
            'id' => (string) Str::uuid(),
            'game_system_id' => $this->gameSystem->id,
            'created_by' => auth()->id(),
            'title' => 'Mesa de prueba',
            'slug' => 'mesa-de-prueba',
            'starts_at' => now()->addWeek(),
            'duration_minutes' => 240,
            'table_type' => 'one_shot',
            'table_format' => $format,
            'location' => 'Local de la asociación',
            'online_url' => 'https://example.com/mesa',
            'status' => 'scheduled',
            'min_players' => 2,
            'max_players' => 5,
            'language' => 'es',
            'experience_level' => 'none',
            'character_creation' => 'pre_generated',
        ]);
    }
}
