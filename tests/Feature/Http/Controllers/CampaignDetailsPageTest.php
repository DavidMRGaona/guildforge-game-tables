<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Feature\Http\Controllers;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\GameTables\Domain\Enums\GameMasterRole;
use Modules\GameTables\Domain\Repositories\CampaignRosterRepositoryInterface;
use Modules\GameTables\Domain\ValueObjects\CampaignId;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\CampaignModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\ContentWarningModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameMasterModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameSystemModel;
use Tests\Support\Modules\ModuleTestCase;

/**
 * The campaign page and the campaign card show what the campaign stores, the way the table page and
 * card show it. The link of the online session only reaches its directors and roster.
 */
final class CampaignDetailsPageTest extends ModuleTestCase
{
    protected ?string $moduleName = 'game-tables';

    protected bool $autoEnableModule = true;

    private GameSystemModel $system;

    private UserModel $creator;

    protected function setUp(): void
    {
        parent::setUp();

        app('router')->getRoutes()->refreshNameLookups();
        config()->set('modules.settings.game-tables', []);

        $this->creator = UserModel::factory()->create(['name' => 'Mary Major', 'email' => 'mary@example.com']);
        $this->system = GameSystemModel::create(['id' => (string) Str::uuid(), 'name' => 'Sistema', 'slug' => 'sistema', 'is_active' => true]);
    }

    public function test_the_page_shows_every_stored_field_with_its_labels(): void
    {
        $this->campaign('El faro del norte', 'el-faro-del-norte', $this->details());

        $this->get('/campanas/el-faro-del-norte')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Campaigns/Show', shouldExist: false)
                ->where('campaign.minPlayers', 2)
                ->where('campaign.maxPlayers', 5)
                ->where('campaign.tableFormat', 'hybrid')
                ->where('campaign.tableFormatLabel', 'Híbrido')
                ->where('campaign.tableFormatColor', 'warning')
                ->where('campaign.location', 'Calle Mayor 1')
                ->where('campaign.scheduleNotes', 'Sábados por la tarde')
                ->where('campaign.genres', ['Fantasía', 'Terror'])
                ->where('campaign.tone', 'dark')
                ->where('campaign.toneLabel', 'Oscuro')
                ->where('campaign.characterCreation', 'bring_own')
                ->where('campaign.characterCreationLabel', 'Trae tu personaje')
                ->where('campaign.safetyTools', ['Carta X', 'Líneas y velos'])
                ->where('campaign.contentWarnings', ['Violencia'])
                ->where('campaign.customWarnings', ['Muertes de personajes']));
    }

    public function test_a_campaign_without_the_new_fields_shows_none(): void
    {
        $this->campaign('La cripta olvidada', 'la-cripta-olvidada');

        $this->get('/campanas/la-cripta-olvidada')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Campaigns/Show', shouldExist: false)
                ->where('campaign.minPlayers', null)
                ->where('campaign.tableFormat', null)
                ->where('campaign.tableFormatLabel', null)
                ->where('campaign.tableFormatColor', null)
                ->where('campaign.location', null)
                ->where('campaign.onlineUrl', null)
                ->where('campaign.scheduleNotes', null)
                ->where('campaign.genres', [])
                ->where('campaign.toneLabel', null)
                ->where('campaign.characterCreationLabel', null)
                ->where('campaign.safetyTools', [])
                ->where('campaign.contentWarnings', [])
                ->where('campaign.customWarnings', []));
    }

    public function test_visitors_and_outsiders_never_get_the_online_link(): void
    {
        $this->campaign('El faro del norte', 'el-faro-del-norte', $this->details());
        $outsider = UserModel::factory()->create(['name' => 'Richard Roe', 'email' => 'richard@example.com']);

        $this->get('/campanas/el-faro-del-norte')
            ->assertInertia(fn (Assert $page) => $page->where('campaign.onlineUrl', null));

        $this->actingAs($outsider)
            ->get('/campanas/el-faro-del-norte')
            ->assertInertia(fn (Assert $page) => $page->where('campaign.onlineUrl', null));
    }

    public function test_roster_members_and_directors_get_the_online_link(): void
    {
        $campaign = $this->campaign('El faro del norte', 'el-faro-del-norte', $this->details());

        $member = UserModel::factory()->create(['name' => 'John Doe', 'email' => 'john@example.com']);
        app(CampaignRosterRepositoryInterface::class)->add(new CampaignId($campaign->id), (string) $member->id, CarbonImmutable::now());

        $director = UserModel::factory()->create(['name' => 'Jane Doe', 'email' => 'jane@example.com']);
        $gm = GameMasterModel::create([
            'id' => (string) Str::uuid(),
            'user_id' => $director->id,
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'role' => GameMasterRole::Main,
            'notify_by_email' => true,
            'is_name_public' => true,
        ]);
        $campaign->gameMasters()->attach($gm->id, ['sort_order' => 1]);

        foreach ([$member, $director] as $viewer) {
            $this->actingAs($viewer)
                ->get('/campanas/el-faro-del-norte')
                ->assertInertia(fn (Assert $page) => $page->where('campaign.onlineUrl', 'https://example.com/mesa'));
        }
    }

    public function test_the_card_of_the_list_gets_the_format_and_never_the_online_link(): void
    {
        $this->campaign('El faro del norte', 'el-faro-del-norte', $this->details());
        $this->campaign('La cripta olvidada', 'la-cripta-olvidada');

        $this->get('/campanas')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Campaigns/Index', shouldExist: false)
                ->has('campaigns.data', 2)
                ->where('campaigns.data', fn (iterable $items): bool => $this->cardsMatch($items)));
    }

    /**
     * @param  iterable<array<string, mixed>>  $items
     */
    private function cardsMatch(iterable $items): bool
    {
        $cards = [];
        foreach ($items as $item) {
            $cards[(string) $item['slug']] = $item;
        }

        $withDetails = $cards['el-faro-del-norte'];
        $without = $cards['la-cripta-olvidada'];

        $this->assertSame('hybrid', $withDetails['tableFormat']);
        $this->assertSame('Híbrido', $withDetails['tableFormatLabel']);
        $this->assertSame('warning', $withDetails['tableFormatColor']);
        $this->assertNull($withDetails['onlineUrl']);
        $this->assertNull($without['tableFormat']);
        $this->assertNull($without['tableFormatLabel']);
        $this->assertNull($without['tableFormatColor']);

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    private function details(): array
    {
        return [
            'min_players' => 2,
            'max_players' => 5,
            'table_format' => 'hybrid',
            'location' => 'Calle Mayor 1',
            'online_url' => 'https://example.com/mesa',
            'schedule_notes' => 'Sábados por la tarde',
            'genres' => ['fantasy', 'horror'],
            'tone' => 'dark',
            'character_creation' => 'bring_own',
            'safety_tools' => ['x_card', 'lines_and_veils'],
            'custom_warnings' => ['Muertes de personajes'],
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function campaign(string $title, string $slug, array $attributes = []): CampaignModel
    {
        $campaign = CampaignModel::create([
            'id' => (string) Str::uuid(),
            'game_system_id' => $this->system->id,
            'created_by' => $this->creator->id,
            'title' => $title,
            'slug' => $slug,
            'status' => 'active',
            'frontend_creation_status' => 'approved',
            'is_published' => true,
            'accepts_new_players' => true,
            'max_players' => 4,
            'roster_access' => 'anyone',
            ...$attributes,
        ]);

        if ($attributes !== []) {
            $warning = ContentWarningModel::create([
                'id' => (string) Str::uuid(),
                'name' => 'Violencia',
                'slug' => 'violencia',
                'label' => 'Violencia',
                'severity' => 'mild',
                'is_active' => true,
            ]);
            $campaign->contentWarnings()->sync([$warning->id]);
        }

        return $campaign;
    }
}
