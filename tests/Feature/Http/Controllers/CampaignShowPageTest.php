<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Feature\Http\Controllers;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\CampaignModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameSystemModel;
use Tests\Support\Modules\ModuleTestCase;

/**
 * The public campaign page only shows what the campaign actually stores.
 */
final class CampaignShowPageTest extends ModuleTestCase
{
    protected ?string $moduleName = 'game-tables';

    protected bool $autoEnableModule = true;

    private GameSystemModel $system;

    private string $creatorId;

    protected function setUp(): void
    {
        parent::setUp();

        app('router')->getRoutes()->refreshNameLookups();
        config()->set('modules.settings.game-tables', []);

        $mary = UserModel::factory()->create(['name' => 'Mary Major', 'email' => 'mary@example.com']);
        $this->system = GameSystemModel::create(['id' => (string) Str::uuid(), 'name' => 'Sistema', 'slug' => 'sistema', 'is_active' => true]);
        $this->creatorId = (string) $mary->id;

        $this->campaign('La cripta olvidada', 'la-cripta-olvidada');
    }

    public function test_a_campaign_without_language_or_experience_level_shows_none(): void
    {
        // Campaigns created before these fields were stored lost the answer: the page must not invent one
        $this->get('/campanas/la-cripta-olvidada')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Campaigns/Show', shouldExist: false)
                ->where('campaign.language', null)
                ->where('campaign.languageLabel', null)
                ->where('campaign.experienceLevel', null)
                ->where('campaign.experienceLevelLabel', null));
    }

    public function test_the_stored_language_and_experience_level_are_shown_with_their_labels(): void
    {
        $this->campaign('El faro del norte', 'el-faro-del-norte', [
            'language' => 'es',
            'experience_level' => 'beginner',
        ]);

        $this->get('/campanas/el-faro-del-norte')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Campaigns/Show', shouldExist: false)
                ->where('campaign.language', 'es')
                ->where('campaign.languageLabel', 'Español')
                ->where('campaign.experienceLevel', 'beginner')
                ->where('campaign.experienceLevelLabel', 'Principiante'));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function campaign(string $title, string $slug, array $attributes = []): CampaignModel
    {
        return CampaignModel::create([
            'id' => (string) Str::uuid(),
            'game_system_id' => $this->system->id,
            'created_by' => $this->creatorId,
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
    }
}
