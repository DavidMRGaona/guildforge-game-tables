<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Feature\Http\Controllers;

use App\Application\Services\SlugRedirectServiceInterface;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Support\Str;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\CampaignModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameSystemModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameTableModel;
use Tests\Support\Modules\ModuleTestCase;

/**
 * Tables and campaigns created from the web get the same unique slug as those created from
 * the panel (title plus the start of the id), so a second one with the same title is saved too.
 * Editing a draft changes its slug only when the title changes, as in the panel, and the old
 * slug keeps redirecting.
 */
final class FrontendCreationSlugTest extends ModuleTestCase
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

    public function test_two_tables_created_from_the_web_with_the_same_title_are_both_saved_with_unique_slugs(): void
    {
        foreach ([1, 2] as $attempt) {
            $this->actingAs($this->author)
                ->post('/mesas/crear', $this->tableData('Sesión semanal'))
                ->assertSessionHasNoErrors()
                ->assertRedirect(route('gametables.my-tables'));
        }

        $tables = GameTableModel::query()->where('title', 'Sesión semanal')->get();

        $this->assertCount(2, $tables);
        [$first, $second] = $tables->all();
        $this->assertNotSame($first->slug, $second->slug);
        foreach ($tables as $table) {
            $this->assertSame('sesion-semanal-'.substr($table->id, 0, 8), $table->slug);
        }
    }

    public function test_two_campaigns_created_from_the_web_with_the_same_title_are_both_saved_with_unique_slugs(): void
    {
        foreach ([1, 2] as $attempt) {
            $this->actingAs($this->author)
                ->post('/campanas/crear', $this->campaignData('Campaña semanal'))
                ->assertSessionHasNoErrors()
                ->assertRedirect(route('campaigns.my-campaigns'));
        }

        $campaigns = CampaignModel::query()->where('title', 'Campaña semanal')->get();

        $this->assertCount(2, $campaigns);
        [$first, $second] = $campaigns->all();
        $this->assertNotSame($first->slug, $second->slug);
        foreach ($campaigns as $campaign) {
            $this->assertSame('campana-semanal-'.substr($campaign->id, 0, 8), $campaign->slug);
        }
    }

    public function test_editing_a_table_draft_without_changing_the_title_keeps_its_slug(): void
    {
        $table = $this->tableDraft('Mesa en borrador', 'mesa-en-borrador-1a2b3c4d');

        $this->actingAs($this->author)
            ->put("/mesas/mis-mesas/{$table->slug}", $this->tableData('Mesa en borrador'))
            ->assertSessionHasNoErrors();

        $this->assertSame('mesa-en-borrador-1a2b3c4d', GameTableModel::query()->findOrFail($table->id)->slug);
    }

    public function test_editing_a_campaign_draft_without_changing_the_title_keeps_its_slug(): void
    {
        $campaign = $this->campaignDraft('Campaña en borrador', 'campana-en-borrador-1a2b3c4d');

        $this->actingAs($this->author)
            ->put("/campanas/mis-campanas/{$campaign->id}", $this->campaignData('Campaña en borrador'))
            ->assertSessionHasNoErrors();

        $this->assertSame('campana-en-borrador-1a2b3c4d', CampaignModel::query()->findOrFail($campaign->id)->slug);
    }

    public function test_a_draft_created_before_with_a_plain_slug_keeps_it_when_edited_without_changing_the_title(): void
    {
        $table = $this->tableDraft('Un cuento triste', 'un-cuento-triste');
        $campaign = $this->campaignDraft('Una campaña triste', 'una-campana-triste');

        $this->actingAs($this->author)
            ->put("/mesas/mis-mesas/{$table->slug}", $this->tableData('Un cuento triste'))
            ->assertSessionHasNoErrors();
        $this->actingAs($this->author)
            ->put("/campanas/mis-campanas/{$campaign->id}", $this->campaignData('Una campaña triste'))
            ->assertSessionHasNoErrors();

        $this->assertSame('un-cuento-triste', GameTableModel::query()->findOrFail($table->id)->slug);
        $this->assertSame('una-campana-triste', CampaignModel::query()->findOrFail($campaign->id)->slug);
    }

    public function test_renaming_a_table_draft_gives_it_a_unique_slug_and_the_old_one_redirects(): void
    {
        $table = $this->tableDraft('Mesa en borrador', 'mesa-en-borrador');

        $this->actingAs($this->author)
            ->put("/mesas/mis-mesas/{$table->slug}", $this->tableData('Mesa renombrada'))
            ->assertSessionHasNoErrors();

        $newSlug = 'mesa-renombrada-'.substr($table->id, 0, 8);
        $this->assertSame($newSlug, GameTableModel::query()->findOrFail($table->id)->slug);
        $this->assertSame($newSlug, app(SlugRedirectServiceInterface::class)->resolveCurrentSlug('mesa-en-borrador', 'game_table'));
    }

    public function test_renaming_a_campaign_draft_gives_it_a_unique_slug_and_the_old_one_redirects(): void
    {
        $campaign = $this->campaignDraft('Campaña en borrador', 'campana-en-borrador');

        $this->actingAs($this->author)
            ->put("/campanas/mis-campanas/{$campaign->id}", $this->campaignData('Campaña renombrada'))
            ->assertSessionHasNoErrors();

        $newSlug = 'campana-renombrada-'.substr($campaign->id, 0, 8);
        $this->assertSame($newSlug, CampaignModel::query()->findOrFail($campaign->id)->slug);
        $this->assertSame($newSlug, app(SlugRedirectServiceInterface::class)->resolveCurrentSlug('campana-en-borrador', 'campaign'));
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
     * @return array<string, mixed>
     */
    private function campaignData(string $title): array
    {
        return [
            'game_system_id' => $this->gameSystem->id,
            'title' => $title,
            'frequency' => 'weekly',
            'min_players' => 2,
            'max_players' => 5,
            'table_format' => 'in_person',
        ];
    }

    private function campaignDraft(string $title, string $slug): CampaignModel
    {
        return CampaignModel::create([
            'id' => (string) Str::uuid(),
            'game_system_id' => $this->gameSystem->id,
            'created_by' => $this->author->id,
            'title' => $title,
            'slug' => $slug,
            'frequency' => 'weekly',
            'status' => 'recruiting',
            'max_players' => 5,
            'is_published' => false,
            'frontend_creation_status' => 'draft',
        ]);
    }

    private function tableDraft(string $title, string $slug): GameTableModel
    {
        return GameTableModel::create([
            'id' => (string) Str::uuid(),
            'game_system_id' => $this->gameSystem->id,
            'created_by' => $this->author->id,
            'title' => $title,
            'slug' => $slug,
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
