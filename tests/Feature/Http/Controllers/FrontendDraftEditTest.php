<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Feature\Http\Controllers;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\CampaignModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameSystemModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameTableModel;
use Tests\Support\Modules\ModuleTestCase;

/**
 * The web edit pages save drafts to the URL written in their Vue source, and the profile's
 * draft cards link to the edit pages with the URL written in theirs: each URL, filled in the way
 * the page fills it, must reach the route that edits that draft.
 */
final class FrontendDraftEditTest extends ModuleTestCase
{
    protected ?string $moduleName = 'game-tables';

    protected bool $autoEnableModule = true;

    private UserModel $author;

    private GameSystemModel $gameSystem;

    protected function setUp(): void
    {
        parent::setUp();

        app('router')->getRoutes()->refreshNameLookups();
        config()->set('modules.settings.game-tables', [
            'frontend_creation' => [
                'enabled' => true,
                'access_level' => 'registered',
                'allowed_content' => 'both',
            ],
        ]);

        $this->author = UserModel::factory()->create();
        $this->gameSystem = GameSystemModel::create([
            'id' => (string) Str::uuid(),
            'name' => 'Sistema de prueba',
            'slug' => 'sistema-de-prueba',
            'is_active' => true,
        ]);
    }

    public function test_the_campaign_edit_page_saves_to_the_route_that_updates_the_draft(): void
    {
        $campaign = $this->campaignDraft();

        $this->actingAs($this->author)
            ->put($this->pageUrl('pages/Campaigns/Edit.vue', 'form.put', $this->campaignPlaceholders($campaign)), [
                'game_system_id' => $this->gameSystem->id,
                'title' => 'Campaña corregida',
                'frequency' => 'weekly',
                'min_players' => 2,
                'max_players' => 5,
                'table_format' => 'in_person',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('campaigns.my-campaigns'));

        $this->assertSame('Campaña corregida', CampaignModel::query()->findOrFail($campaign->id)->title);
    }

    public function test_the_campaign_draft_card_links_to_the_campaign_edit_page(): void
    {
        $campaign = $this->campaignDraft();

        $this->actingAs($this->author)
            ->get($this->pageUrl('components/profile/ProfileCreatedCampaignCard.vue', 'const editUrl = computed(() =>', $this->campaignPlaceholders($campaign)))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Campaigns/Edit', false)
                ->where('campaign.id', $campaign->id));
    }

    public function test_the_table_edit_page_saves_to_the_route_that_updates_the_draft(): void
    {
        $table = $this->tableDraft();

        $this->actingAs($this->author)
            ->put($this->pageUrl('pages/GameTables/Edit.vue', 'form.put', $this->tablePlaceholders($table)), [
                'game_system_id' => $this->gameSystem->id,
                'title' => 'Mesa corregida',
                'starts_at' => now()->addWeeks(2)->toIso8601String(),
                'duration_minutes' => 240,
                'table_type' => 'one_shot',
                'table_format' => 'in_person',
                'min_players' => 2,
                'max_players' => 5,
                'language' => 'es',
                'experience_level' => 'none',
                'character_creation' => 'pre_generated',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('gametables.my-tables'));

        $this->assertSame('Mesa corregida', GameTableModel::query()->findOrFail($table->id)->title);
    }

    public function test_the_table_draft_card_links_to_the_table_edit_page(): void
    {
        $table = $this->tableDraft();

        $this->actingAs($this->author)
            ->get($this->pageUrl('components/profile/ProfileCreatedTableCard.vue', 'const editUrl = computed(() =>', $this->tablePlaceholders($table)))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('GameTables/Edit', false)
                ->where('table.id', $table->id));
    }

    public function test_an_edit_link_built_with_the_slug_or_the_id_opens_the_edit_page_for_the_author(): void
    {
        $table = $this->tableDraft();

        // The slug is the canonical form; rejection emails sent before 2.8.0 carry the id
        foreach (["/mesas/mis-mesas/{$table->slug}/editar", "/mesas/mis-mesas/{$table->id}/editar"] as $url) {
            $this->actingAs($this->author)
                ->get($url)
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->component('GameTables/Edit', false)
                    ->where('table.id', $table->id));
        }
    }

    public function test_the_named_edit_route_builds_the_link_with_the_slug(): void
    {
        $table = $this->tableDraft();

        $this->assertSame(url('/mesas/mis-mesas/mesa-en-borrador/editar'), route('gametables.edit', $table));
    }

    public function test_a_draft_saved_with_the_id_is_updated_as_with_the_slug(): void
    {
        $table = $this->tableDraft();

        $this->actingAs($this->author)
            ->put("/mesas/mis-mesas/{$table->id}", $this->tableData('Mesa corregida por id'))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('gametables.my-tables'));

        $this->assertSame('Mesa corregida por id', GameTableModel::query()->findOrFail($table->id)->title);
    }

    public function test_a_draft_keeps_the_genres_chosen_on_the_edit_page(): void
    {
        $table = $this->tableDraft();
        $table->update(['genres' => ['fantasy', 'horror']]);

        // The edit page sends the genre values; it used to send back the {value, label} pairs it received
        $this->actingAs($this->author)
            ->put("/mesas/mis-mesas/{$table->id}", [...$this->tableData('Mesa con géneros'), 'genres' => ['horror', 'mystery']])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('gametables.my-tables'));

        $this->assertSame(['horror', 'mystery'], GameTableModel::query()->findOrFail($table->id)->genres);

        $this->actingAs($this->author)
            ->put("/mesas/mis-mesas/{$table->id}", [...$this->tableData('Mesa con géneros'), 'genres' => [['value' => 'horror', 'label' => 'Terror']]])
            ->assertSessionHasErrors('genres.0');
    }

    public function test_another_account_is_refused_with_either_link_as_before(): void
    {
        $table = $this->tableDraft();
        $other = UserModel::factory()->create();

        foreach ([$table->slug, $table->id] as $key) {
            $this->actingAs($other)->get("/mesas/mis-mesas/{$key}/editar")->assertForbidden();
            $this->actingAs($other)->put("/mesas/mis-mesas/{$key}", $this->tableData('Ajena'))->assertForbidden();
            $this->actingAs($other)->post("/mesas/mis-mesas/{$key}/enviar-revision")->assertForbidden();
            $this->actingAs($other)->delete("/mesas/mis-mesas/{$key}")->assertForbidden();
        }

        $this->assertSame('Mesa en borrador', GameTableModel::query()->findOrFail($table->id)->title);
    }

    public function test_a_link_to_no_table_is_not_found(): void
    {
        foreach (['no-existe', (string) Str::uuid()] as $key) {
            $this->actingAs($this->author)->get("/mesas/mis-mesas/{$key}/editar")->assertNotFound();
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function tableData(string $title): array
    {
        return [
            'game_system_id' => $this->gameSystem->id,
            'title' => $title,
            'starts_at' => now()->addWeeks(2)->toIso8601String(),
            'duration_minutes' => 240,
            'table_type' => 'one_shot',
            'table_format' => 'in_person',
            'min_players' => 2,
            'max_players' => 5,
            'language' => 'es',
            'experience_level' => 'none',
            'character_creation' => 'pre_generated',
        ];
    }

    /**
     * The first URL template after `$call` in the Vue file, with each `${expression}` replaced
     * by its value; fails on an expression the test does not know.
     *
     * @param  array<string, string>  $values  expression => value
     */
    private function pageUrl(string $vueFile, string $call, array $values): string
    {
        $source = (string) file_get_contents(dirname(__DIR__, 4).'/resources/js/'.$vueFile);
        $found = preg_match('/'.preg_quote($call, '/').'[^`]*`([^`]+)`/', $source, $match);
        $this->assertSame(1, $found, "No URL after {$call} in {$vueFile}");

        return (string) preg_replace_callback('/\$\{([^}]+)\}/', function (array $placeholder) use ($values, $vueFile): string {
            $expression = trim($placeholder[1]);
            $this->assertArrayHasKey($expression, $values, "Unknown expression {$expression} in {$vueFile}");

            return $values[$expression];
        }, $match[1]);
    }

    /**
     * @return array<string, string>
     */
    private function campaignPlaceholders(CampaignModel $campaign): array
    {
        return [
            'props.campaign.id' => $campaign->id,
            'props.campaign.slug' => (string) $campaign->slug,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function tablePlaceholders(GameTableModel $table): array
    {
        return [
            'props.table.id' => $table->id,
            'props.table.slug' => (string) $table->slug,
        ];
    }

    private function campaignDraft(): CampaignModel
    {
        return CampaignModel::create([
            'id' => (string) Str::uuid(),
            'game_system_id' => $this->gameSystem->id,
            'created_by' => $this->author->id,
            'title' => 'Campaña en borrador',
            'slug' => 'campana-en-borrador',
            'frequency' => 'weekly',
            'status' => 'recruiting',
            'max_players' => 5,
            'is_published' => false,
            'frontend_creation_status' => 'draft',
        ]);
    }

    private function tableDraft(): GameTableModel
    {
        return GameTableModel::create([
            'id' => (string) Str::uuid(),
            'game_system_id' => $this->gameSystem->id,
            'created_by' => $this->author->id,
            'title' => 'Mesa en borrador',
            'slug' => 'mesa-en-borrador',
            'starts_at' => now()->addWeek(),
            'duration_minutes' => 240,
            'table_type' => 'one_shot',
            'table_format' => 'in_person',
            'status' => 'draft',
            'min_players' => 1,
            'max_players' => 5,
            'language' => 'es',
            'registration_type' => 'everyone',
            'auto_confirm' => true,
            'is_published' => false,
            'frontend_creation_status' => 'draft',
        ]);
    }
}
