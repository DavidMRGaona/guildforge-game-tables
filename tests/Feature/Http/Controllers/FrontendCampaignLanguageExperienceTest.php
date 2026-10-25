<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Feature\Http\Controllers;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\GameTables\Domain\Enums\ExperienceLevel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\CampaignModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameSystemModel;
use Tests\Support\Modules\ModuleTestCase;

/**
 * The language and experience level asked by the web campaign form are stored when the campaign
 * is created or its draft edited, and the edit form gets them back.
 */
final class FrontendCampaignLanguageExperienceTest extends ModuleTestCase
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

    public function test_creating_a_campaign_from_the_web_stores_its_language_and_experience_level(): void
    {
        $this->actingAs($this->author)
            ->post('/campanas/crear', $this->campaignData('Campaña en catalán', language: 'ca', experienceLevel: 'intermediate'))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('campaigns.my-campaigns'));

        $campaign = CampaignModel::query()->where('title', 'Campaña en catalán')->sole();

        $this->assertSame('ca', $campaign->language);
        $this->assertSame(ExperienceLevel::Intermediate, $campaign->experience_level);
    }

    public function test_a_campaign_created_without_an_experience_level_stores_none(): void
    {
        $this->actingAs($this->author)
            ->post('/campanas/crear', $this->campaignData('Campaña sin nivel', language: 'en'))
            ->assertSessionHasNoErrors();

        $campaign = CampaignModel::query()->where('title', 'Campaña sin nivel')->sole();

        $this->assertSame('en', $campaign->language);
        $this->assertNull($campaign->experience_level);
    }

    public function test_editing_a_campaign_draft_stores_the_new_language_and_experience_level(): void
    {
        $campaign = $this->campaignDraft(['language' => 'es', 'experience_level' => 'none']);

        $this->actingAs($this->author)
            ->put("/campanas/mis-campanas/{$campaign->id}", $this->campaignData('Campaña en borrador', language: 'gl', experienceLevel: 'advanced'))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('campaigns.my-campaigns'));

        $campaign->refresh();
        $this->assertSame('gl', $campaign->language);
        $this->assertSame(ExperienceLevel::Advanced, $campaign->experience_level);
    }

    public function test_the_edit_form_gets_the_stored_language_and_experience_level(): void
    {
        $campaign = $this->campaignDraft(['language' => 'eu', 'experience_level' => 'beginner']);

        $this->actingAs($this->author)
            ->get("/campanas/mis-campanas/{$campaign->id}/editar")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Campaigns/Edit', shouldExist: false)
                ->where('campaign.language', 'eu')
                ->where('campaign.experience_level', 'beginner'));
    }

    /**
     * @return array<string, mixed>
     */
    private function campaignData(string $title, string $language, ?string $experienceLevel = null): array
    {
        return array_filter([
            'game_system_id' => $this->gameSystem->id,
            'title' => $title,
            'frequency' => 'weekly',
            'min_players' => 2,
            'max_players' => 5,
            'table_format' => 'in_person',
            'language' => $language,
            'experience_level' => $experienceLevel,
        ], static fn (mixed $value): bool => $value !== null);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function campaignDraft(array $attributes): CampaignModel
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
            ...$attributes,
        ]);
    }
}
