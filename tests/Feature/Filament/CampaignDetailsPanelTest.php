<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Feature\Filament;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Modules\GameTables\Domain\Enums\CharacterCreation;
use Modules\GameTables\Domain\Enums\TableFormat;
use Modules\GameTables\Domain\Enums\Tone;
use Modules\GameTables\Domain\Repositories\CampaignRosterRepositoryInterface;
use Modules\GameTables\Domain\ValueObjects\CampaignId;
use Modules\GameTables\Filament\Resources\CampaignResource;
use Modules\GameTables\Filament\Resources\CampaignResource\Pages\CreateCampaign;
use Modules\GameTables\Filament\Resources\CampaignResource\Pages\EditCampaign;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\CampaignModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\ContentWarningModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameSystemModel;
use Tests\Support\Modules\InteractsWithModulePanel;
use Tests\Support\Modules\ModuleTestCase;

/**
 * Every field of the web campaign form can be read and changed in the panel campaign form, whose
 * maximum of players follows the same limit of the settings as tables.
 */
final class CampaignDetailsPanelTest extends ModuleTestCase
{
    use InteractsWithModulePanel;

    protected ?string $moduleName = 'game-tables';

    protected bool $autoEnableModule = true;

    private UserModel $admin;

    private GameSystemModel $system;

    private ContentWarningModel $violence;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registerOnAdminPanel([CampaignResource::class]);
        $this->admin = $this->actingAsPanelAdmin();
        config()->set('modules.settings.game-tables', []);

        $this->system = GameSystemModel::create([
            'id' => (string) Str::uuid(),
            'name' => 'Sistema de prueba',
            'slug' => 'sistema-de-prueba',
            'is_active' => true,
        ]);
        $this->violence = ContentWarningModel::create([
            'id' => (string) Str::uuid(),
            'name' => 'Violencia',
            'slug' => 'violencia',
            'label' => 'Violencia',
            'severity' => 'mild',
            'is_active' => true,
        ]);
    }

    public function test_the_form_shows_the_stored_fields(): void
    {
        $campaign = $this->campaign([
            'min_players' => 2,
            'table_format' => 'in_person',
            'location' => 'Local de la asociación',
            'schedule_notes' => 'Viernes noche',
            'genres' => ['mystery'],
            'tone' => 'serious',
            'character_creation' => 'pre_generated',
            'safety_tools' => ['open_door'],
            'custom_warnings' => ['Gore'],
        ]);
        $campaign->contentWarnings()->sync([$this->violence->id]);

        $this->edit($campaign)->assertFormSet([
            'min_players' => 2,
            'table_format' => 'in_person',
            'location' => 'Local de la asociación',
            'schedule_notes' => 'Viernes noche',
            'genres' => ['mystery'],
            'tone' => 'serious',
            'character_creation' => 'pre_generated',
            'safety_tools' => ['open_door'],
            'contentWarnings' => [$this->violence->id],
            'custom_warnings' => ['Gore'],
        ]);
    }

    public function test_every_field_saves(): void
    {
        $campaign = $this->campaign();

        $this->edit($campaign)
            ->fillForm($this->fields())
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertStored($campaign->refresh());
    }

    public function test_a_campaign_created_in_the_panel_stores_every_field(): void
    {
        Livewire::test(CreateCampaign::class)
            ->fillForm([
                'title' => 'Campaña del panel',
                'game_system_id' => $this->system->id,
                'status' => 'recruiting',
                'roster_access' => 'anyone',
                'max_players' => 5,
                'gameMasters' => [],
                ...$this->fields(),
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertStored(CampaignModel::query()->where('title', 'Campaña del panel')->sole());
    }

    public function test_the_maximum_follows_the_limit_of_the_settings(): void
    {
        config()->set('modules.settings.game-tables', ['limits' => ['max_players_limit' => 15]]);
        $campaign = $this->campaign();

        $this->edit($campaign)
            ->fillForm(['max_players' => 16])
            ->call('save')
            ->assertHasFormErrors(['max_players']);

        $this->edit($campaign)
            ->fillForm(['max_players' => 15])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(15, $campaign->refresh()->max_players);
    }

    public function test_the_minimum_never_exceeds_the_maximum(): void
    {
        $campaign = $this->campaign();

        $this->edit($campaign)
            ->fillForm(['min_players' => 6, 'max_players' => 4])
            ->call('save')
            ->assertHasFormErrors(['min_players']);

        $this->assertNull($campaign->refresh()->min_players);
    }

    public function test_the_maximum_never_goes_below_the_roster(): void
    {
        $campaign = $this->campaign();
        $member = UserModel::factory()->create(['name' => 'John Doe', 'email' => 'john@example.com']);
        $other = UserModel::factory()->create(['name' => 'Richard Roe', 'email' => 'richard@example.com']);
        $roster = app(CampaignRosterRepositoryInterface::class);
        $roster->add(new CampaignId($campaign->id), (string) $member->id, CarbonImmutable::now());
        $roster->add(new CampaignId($campaign->id), (string) $other->id, CarbonImmutable::now());

        $this->edit($campaign)
            ->fillForm(['max_players' => 1])
            ->call('save')
            ->assertHasFormErrors(['max_players']);
    }

    /**
     * @return array<string, mixed>
     */
    private function fields(): array
    {
        return [
            'min_players' => 3,
            'schedule_notes' => 'Sábados por la tarde',
            'table_format' => 'hybrid',
            'location' => 'Calle Mayor 1',
            'online_url' => 'https://example.com/mesa',
            'genres' => ['fantasy', 'horror'],
            'tone' => 'dark',
            'character_creation' => 'bring_own',
            'safety_tools' => ['x_card'],
            'contentWarnings' => [$this->violence->id],
            'custom_warnings' => ['Muertes de personajes'],
        ];
    }

    private function assertStored(CampaignModel $campaign): void
    {
        $this->assertSame(3, $campaign->min_players);
        $this->assertSame('Sábados por la tarde', $campaign->schedule_notes);
        $this->assertSame(TableFormat::Hybrid, $campaign->table_format);
        $this->assertSame('Calle Mayor 1', $campaign->location);
        $this->assertSame('https://example.com/mesa', $campaign->online_url);
        $this->assertSame(['fantasy', 'horror'], $campaign->genres);
        $this->assertSame(Tone::Dark, $campaign->tone);
        $this->assertSame(CharacterCreation::BringOwn, $campaign->character_creation);
        $this->assertSame(['x_card'], $campaign->safety_tools);
        $this->assertSame([$this->violence->id], $campaign->contentWarnings()->pluck('id')->all());
        $this->assertSame(['Muertes de personajes'], $campaign->custom_warnings);
    }

    /**
     * @return Testable<EditCampaign>
     */
    private function edit(CampaignModel $campaign): Testable
    {
        return Livewire::test(EditCampaign::class, ['record' => $campaign->getRouteKey()]);
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
            'title' => 'Campaña de prueba',
            'slug' => Str::random(10),
            'frequency' => 'weekly',
            'status' => 'recruiting',
            'max_players' => 5,
            'accepts_new_players' => true,
            'is_published' => true,
            ...$attributes,
        ]);
    }
}
