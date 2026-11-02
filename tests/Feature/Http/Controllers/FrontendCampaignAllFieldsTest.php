<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Feature\Http\Controllers;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\GameTables\Domain\Enums\CharacterCreation;
use Modules\GameTables\Domain\Enums\TableFormat;
use Modules\GameTables\Domain\Enums\Tone;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\CampaignModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\ContentWarningModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameSystemModel;
use Tests\Support\Modules\ModuleTestCase;

/**
 * Every field the web campaign form asks for is stored when the campaign is created or its draft
 * edited, and the edit form gets them back. The maximum of players follows the limit of the
 * settings, as tables do.
 */
final class FrontendCampaignAllFieldsTest extends ModuleTestCase
{
    protected ?string $moduleName = 'game-tables';

    protected bool $autoEnableModule = true;

    private UserModel $author;

    private GameSystemModel $gameSystem;

    private ContentWarningModel $violence;

    private ContentWarningModel $spiders;

    protected function setUp(): void
    {
        parent::setUp();

        app('router')->getRoutes()->refreshNameLookups();
        $this->configure();

        $this->author = UserModel::factory()->create();
        $this->gameSystem = GameSystemModel::create([
            'id' => (string) Str::uuid(),
            'name' => 'Sistema de prueba',
            'slug' => 'sistema-de-prueba',
            'is_active' => true,
        ]);
        $this->violence = $this->contentWarning('Violencia');
        $this->spiders = $this->contentWarning('Arañas');
    }

    public function test_creating_a_campaign_from_the_web_stores_every_field(): void
    {
        $this->actingAs($this->author)
            ->post('/campanas/crear', $this->campaignData('Campaña completa'))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('campaigns.my-campaigns'));

        $campaign = CampaignModel::query()->where('title', 'Campaña completa')->sole();

        $this->assertSame($this->gameSystem->id, $campaign->game_system_id);
        $this->assertSame('Una sinopsis', $campaign->description);
        $this->assertSame('weekly', $campaign->frequency?->value);
        $this->assertSame('Sábados por la tarde', $campaign->schedule_notes);
        $this->assertSame(2, $campaign->min_players);
        $this->assertSame(5, $campaign->max_players);
        $this->assertSame(TableFormat::Hybrid, $campaign->table_format);
        $this->assertSame('Calle Mayor 1', $campaign->location);
        $this->assertSame('https://example.com/mesa', $campaign->online_url);
        $this->assertSame('es', $campaign->language);
        $this->assertSame(['fantasy', 'horror'], $campaign->genres);
        $this->assertSame(Tone::Dark, $campaign->tone);
        $this->assertSame('beginner', $campaign->experience_level?->value);
        $this->assertSame(CharacterCreation::BringOwn, $campaign->character_creation);
        $this->assertSame(['x_card', 'lines_and_veils'], $campaign->safety_tools);
        $this->assertEqualsCanonicalizing(
            [$this->violence->id, $this->spiders->id],
            $campaign->contentWarnings()->pluck('id')->all(),
        );
        $this->assertSame(['Muertes de personajes'], $campaign->custom_warnings);
    }

    public function test_a_campaign_created_with_only_the_required_fields_stores_no_extras(): void
    {
        $this->actingAs($this->author)
            ->post('/campanas/crear', [
                'game_system_id' => $this->gameSystem->id,
                'title' => 'Campaña mínima',
                'frequency' => 'monthly',
                'min_players' => 1,
                'max_players' => 4,
                'table_format' => 'online',
                // Online, the link is required
                'online_url' => 'https://example.com/mesa',
            ])
            ->assertSessionHasNoErrors();

        $campaign = CampaignModel::query()->where('title', 'Campaña mínima')->sole();

        $this->assertSame(TableFormat::Online, $campaign->table_format);
        $this->assertNull($campaign->location);
        $this->assertNull($campaign->schedule_notes);
        $this->assertNull($campaign->genres);
        $this->assertNull($campaign->tone);
        $this->assertNull($campaign->character_creation);
        $this->assertNull($campaign->safety_tools);
        $this->assertNull($campaign->custom_warnings);
        $this->assertSame(0, $campaign->contentWarnings()->count());
    }

    public function test_the_edit_form_gets_every_stored_field(): void
    {
        $campaign = $this->campaignDraft();

        $this->actingAs($this->author)
            ->get("/campanas/mis-campanas/{$campaign->id}/editar")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Campaigns/Edit', shouldExist: false)
                ->where('campaign.schedule_notes', 'Viernes noche')
                ->where('campaign.min_players', 3)
                ->where('campaign.max_players', 6)
                ->where('campaign.table_format', 'in_person')
                ->where('campaign.location', 'Local de la asociación')
                ->where('campaign.online_url', null)
                ->where('campaign.genres', ['mystery'])
                ->where('campaign.tone', 'serious')
                ->where('campaign.character_creation', 'pre_generated')
                ->where('campaign.safety_tools', ['open_door'])
                ->where('campaign.content_warning_ids', [$this->violence->id])
                ->where('campaign.custom_warnings', ['Gore'])
                ->where('formData.limits.max_players', 20));
    }

    public function test_editing_a_campaign_draft_updates_every_field(): void
    {
        $campaign = $this->campaignDraft();

        $this->actingAs($this->author)
            ->put("/campanas/mis-campanas/{$campaign->id}", $this->campaignData('Campaña en borrador'))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('campaigns.my-campaigns'));

        $campaign->refresh();
        $this->assertSame('Sábados por la tarde', $campaign->schedule_notes);
        $this->assertSame(2, $campaign->min_players);
        $this->assertSame(TableFormat::Hybrid, $campaign->table_format);
        $this->assertSame('Calle Mayor 1', $campaign->location);
        $this->assertSame('https://example.com/mesa', $campaign->online_url);
        $this->assertSame(['fantasy', 'horror'], $campaign->genres);
        $this->assertSame(Tone::Dark, $campaign->tone);
        $this->assertSame(CharacterCreation::BringOwn, $campaign->character_creation);
        $this->assertSame(['x_card', 'lines_and_veils'], $campaign->safety_tools);
        $this->assertEqualsCanonicalizing(
            [$this->violence->id, $this->spiders->id],
            $campaign->contentWarnings()->pluck('id')->all(),
        );
        $this->assertSame(['Muertes de personajes'], $campaign->custom_warnings);
    }

    public function test_editing_a_draft_without_content_warnings_removes_them(): void
    {
        $campaign = $this->campaignDraft();

        $this->actingAs($this->author)
            ->put("/campanas/mis-campanas/{$campaign->id}", [
                ...$this->campaignData('Campaña en borrador'),
                'content_warning_ids' => [],
                'custom_warnings' => [],
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(0, $campaign->contentWarnings()->count());
    }

    public function test_the_maximum_of_players_follows_the_limit_of_the_settings(): void
    {
        $this->configure(maxPlayersLimit: 15);

        $this->actingAs($this->author)
            ->post('/campanas/crear', [...$this->campaignData('Campaña grande'), 'max_players' => 15])
            ->assertSessionHasNoErrors();
        $this->assertSame(15, CampaignModel::query()->where('title', 'Campaña grande')->sole()->max_players);

        $this->actingAs($this->author)
            ->post('/campanas/crear', [...$this->campaignData('Campaña enorme'), 'max_players' => 16])
            ->assertSessionHasErrors(['max_players']);
        $this->assertFalse(CampaignModel::query()->where('title', 'Campaña enorme')->exists());

        $this->actingAs($this->author)
            ->get('/campanas/crear')
            ->assertInertia(fn (Assert $page) => $page
                ->component('Campaigns/Create', shouldExist: false)
                ->where('formData.limits.max_players', 15));
    }

    public function test_the_minimum_never_exceeds_the_maximum(): void
    {
        $this->actingAs($this->author)
            ->post('/campanas/crear', [...$this->campaignData('Campaña al revés'), 'min_players' => 6, 'max_players' => 4])
            ->assertSessionHasErrors(['max_players']);

        $this->assertFalse(CampaignModel::query()->where('title', 'Campaña al revés')->exists());
    }

    private function configure(?int $maxPlayersLimit = null): void
    {
        config()->set('modules.settings.game-tables', array_filter([
            'frontend_creation' => [
                'enabled' => true,
                'access_level' => 'registered',
                'allowed_content' => 'both',
            ],
            'limits' => $maxPlayersLimit !== null ? ['max_players_limit' => $maxPlayersLimit] : null,
        ]));
    }

    /**
     * @return array<string, mixed>
     */
    private function campaignData(string $title): array
    {
        return [
            'game_system_id' => $this->gameSystem->id,
            'title' => $title,
            'synopsis' => 'Una sinopsis',
            'frequency' => 'weekly',
            'schedule_notes' => 'Sábados por la tarde',
            'min_players' => 2,
            'max_players' => 5,
            'table_format' => 'hybrid',
            'location' => 'Calle Mayor 1',
            'online_url' => 'https://example.com/mesa',
            'language' => 'es',
            'genres' => ['fantasy', 'horror'],
            'tone' => 'dark',
            'experience_level' => 'beginner',
            'character_creation' => 'bring_own',
            'safety_tools' => ['x_card', 'lines_and_veils'],
            'content_warning_ids' => [$this->violence->id, $this->spiders->id],
            'custom_warnings' => ['Muertes de personajes'],
        ];
    }

    private function campaignDraft(): CampaignModel
    {
        $campaign = CampaignModel::create([
            'id' => (string) Str::uuid(),
            'game_system_id' => $this->gameSystem->id,
            'created_by' => $this->author->id,
            'title' => 'Campaña en borrador',
            'slug' => 'campana-en-borrador',
            'frequency' => 'weekly',
            'status' => 'recruiting',
            'max_players' => 6,
            'is_published' => false,
            'frontend_creation_status' => 'draft',
            'schedule_notes' => 'Viernes noche',
            'min_players' => 3,
            'table_format' => 'in_person',
            'location' => 'Local de la asociación',
            'genres' => ['mystery'],
            'tone' => 'serious',
            'character_creation' => 'pre_generated',
            'safety_tools' => ['open_door'],
            'custom_warnings' => ['Gore'],
        ]);
        $campaign->contentWarnings()->sync([$this->violence->id]);

        return $campaign;
    }

    private function contentWarning(string $label): ContentWarningModel
    {
        $slug = Str::slug($label);

        return ContentWarningModel::create([
            'id' => (string) Str::uuid(),
            'name' => $label,
            'slug' => $slug,
            'label' => $label,
            'severity' => 'mild',
            'is_active' => true,
        ]);
    }
}
