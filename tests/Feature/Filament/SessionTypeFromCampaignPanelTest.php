<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Feature\Filament;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Filament\Forms\Components\Select;
use Illuminate\Support\Str;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Modules\GameTables\Filament\Resources\GameTableResource;
use Modules\GameTables\Filament\Resources\GameTableResource\Pages\CreateGameTable;
use Modules\GameTables\Filament\Resources\GameTableResource\Pages\EditGameTable;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\CampaignModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameSystemModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameTableModel;
use Tests\Support\Modules\InteractsWithModulePanel;
use Tests\Support\Modules\ModuleTestCase;

/**
 * In the panel, as on the site, a table with a campaign is a campaign session and a campaign
 * session has a campaign. Choosing the campaign sets the type; removing it gives back the type the
 * form had before, or leaves it blank to be chosen.
 */
final class SessionTypeFromCampaignPanelTest extends ModuleTestCase
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

    public function test_choosing_a_campaign_makes_the_table_a_locked_campaign_session(): void
    {
        $campaign = $this->campaign();

        Livewire::test(CreateGameTable::class)
            ->assertFormFieldEnabled('table_type')
            ->fillForm(['table_type' => 'demo'])
            ->fillForm(['campaign_id' => $campaign->id])
            ->assertFormSet(['table_type' => 'campaign_session'])
            ->assertFormFieldDisabled('table_type');
    }

    public function test_removing_the_campaign_gives_back_the_type_the_form_had_before(): void
    {
        $first = $this->campaign();
        $second = $this->campaign();

        $this->edit($this->table(['table_type' => 'demo']))
            ->fillForm(['campaign_id' => $first->id])
            ->fillForm(['campaign_id' => $second->id])
            ->assertFormSet(['table_type' => 'campaign_session'])
            ->fillForm(['campaign_id' => null])
            ->assertFormSet(['table_type' => 'demo'])
            ->assertFormFieldEnabled('table_type');
    }

    public function test_removing_the_campaign_of_a_saved_session_leaves_the_type_to_be_chosen(): void
    {
        $campaign = $this->campaign();
        $table = $this->table(['table_type' => 'campaign_session', 'campaign_id' => $campaign->id]);

        $this->edit($table)
            ->assertFormFieldDisabled('table_type')
            ->fillForm(['campaign_id' => null])
            ->assertFormSet(['table_type' => null])
            ->call('save')
            ->assertHasFormErrors(['table_type' => 'required']);

        $table->refresh();
        $this->assertSame($campaign->id, $table->campaign_id);
        $this->assertSame('campaign_session', $table->table_type->value);
    }

    public function test_a_table_without_its_campaign_saves_with_the_type_chosen(): void
    {
        $campaign = $this->campaign();
        $table = $this->table(['table_type' => 'campaign_session', 'campaign_id' => $campaign->id]);

        $this->edit($table)
            ->fillForm(['campaign_id' => null])
            ->fillForm(['table_type' => 'adventure'])
            ->call('save')
            ->assertHasNoFormErrors();

        $table->refresh();
        $this->assertNull($table->campaign_id);
        $this->assertSame('adventure', $table->table_type->value);
    }

    public function test_a_campaign_session_needs_its_campaign(): void
    {
        $table = $this->table();

        $this->edit($table)
            ->fillForm(['table_type' => 'campaign_session'])
            ->call('save')
            ->assertHasFormErrors(['campaign_id' => 'required']);

        $this->assertSame('one_shot', $table->refresh()->table_type->value);
    }

    public function test_a_table_with_a_campaign_is_saved_as_a_session_whatever_type_is_sent(): void
    {
        $campaign = $this->campaign();
        $table = $this->table();

        $this->edit($table)
            ->fillForm(['campaign_id' => $campaign->id])
            // A forged payload: the disabled field still reaches the server
            ->set('data.table_type', 'demo')
            ->call('save')
            ->assertHasNoFormErrors();

        $table->refresh();
        $this->assertSame($campaign->id, $table->campaign_id);
        $this->assertSame('campaign_session', $table->table_type->value);
    }

    public function test_a_new_table_with_a_campaign_is_created_as_a_session_whatever_type_is_sent(): void
    {
        $campaign = $this->campaign();

        Livewire::test(CreateGameTable::class)
            ->fillForm($this->formData(['campaign_id' => $campaign->id]))
            ->set('data.table_type', 'tutorial')
            ->call('create')
            ->assertHasNoFormErrors();

        $table = GameTableModel::query()->where('title', 'Mesa nueva')->firstOrFail();
        $this->assertSame($campaign->id, $table->campaign_id);
        $this->assertSame('campaign_session', $table->table_type->value);
    }

    public function test_the_campaign_selector_leaves_out_web_campaigns_not_approved_yet(): void
    {
        $panel = $this->campaign(['title' => 'Creada en el panel']);
        $approved = $this->campaign(['title' => 'Aprobada', 'frontend_creation_status' => 'approved']);
        $this->campaign(['title' => 'Borrador del autor', 'frontend_creation_status' => 'draft', 'is_published' => false]);
        $this->campaign(['title' => 'En revisión', 'frontend_creation_status' => 'pending_review', 'is_published' => false]);
        $this->campaign(['title' => 'Rechazada antigua', 'frontend_creation_status' => 'rejected', 'is_published' => false]);

        $this->assertEqualsCanonicalizing(
            [$panel->id => 'Creada en el panel', $approved->id => 'Aprobada'],
            $this->campaignOptions(Livewire::test(CreateGameTable::class)),
        );
    }

    public function test_a_campaign_not_approved_yet_cannot_be_linked(): void
    {
        $pending = $this->campaign(['frontend_creation_status' => 'pending_review', 'is_published' => false]);
        $table = $this->table();

        $this->edit($table)
            ->fillForm(['campaign_id' => $pending->id])
            ->call('save')
            ->assertHasFormErrors(['campaign_id']);

        $this->assertNull($table->refresh()->campaign_id);
    }

    public function test_the_campaign_a_table_already_has_stays_offered_and_saves(): void
    {
        $pending = $this->campaign(['title' => 'En revisión', 'frontend_creation_status' => 'pending_review', 'is_published' => false]);
        $table = $this->table(['table_type' => 'campaign_session', 'campaign_id' => $pending->id]);

        $component = $this->edit($table);

        $this->assertArrayHasKey($pending->id, $this->campaignOptions($component));

        $component->call('save')->assertHasNoFormErrors();
        $this->assertSame($pending->id, $table->refresh()->campaign_id);
    }

    /**
     * @param  Testable<CreateGameTable>|Testable<EditGameTable>  $component
     * @return array<string, string>
     */
    private function campaignOptions(Testable $component): array
    {
        $field = $component->instance()->getSchema('form')?->getComponent('campaign_id');
        $this->assertInstanceOf(Select::class, $field);

        /** @var array<string, string> $options */
        $options = $field->getOptions();

        return $options;
    }

    /**
     * @return Testable<EditGameTable>
     */
    private function edit(GameTableModel $table): Testable
    {
        return Livewire::test(EditGameTable::class, ['record' => $table->getRouteKey()]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function table(array $attributes = []): GameTableModel
    {
        return GameTableModel::create([
            'id' => (string) Str::uuid(),
            'game_system_id' => $this->system->id,
            'created_by' => $this->admin->id,
            'title' => 'Mesa de prueba',
            'slug' => 'mesa-de-prueba-'.Str::random(5),
            'starts_at' => '2030-01-10 18:00:00',
            'duration_minutes' => 240,
            'table_type' => 'one_shot',
            'table_format' => 'in_person',
            'location' => 'Local de la asociación',
            'status' => 'draft',
            'min_players' => 1,
            'max_players' => 5,
            'language' => 'es',
            'experience_level' => 'none',
            'character_creation' => 'pre_generated',
            ...$attributes,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function formData(array $overrides = []): array
    {
        return [
            'title' => 'Mesa nueva',
            'game_system_id' => $this->system->id,
            'starts_at' => '2030-01-10 18:00:00',
            'duration_minutes' => 240,
            'table_type' => 'one_shot',
            'table_format' => 'in_person',
            'location' => 'Local de la asociación',
            'min_players' => 1,
            'max_players' => 5,
            'language' => 'es',
            'experience_level' => 'none',
            'character_creation' => 'pre_generated',
            ...$overrides,
        ];
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
