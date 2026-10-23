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

    protected function setUp(): void
    {
        parent::setUp();

        app('router')->getRoutes()->refreshNameLookups();
        config()->set('modules.settings.game-tables', []);

        $mary = UserModel::factory()->create(['name' => 'Mary Major', 'email' => 'mary@example.com']);
        $system = GameSystemModel::create(['id' => (string) Str::uuid(), 'name' => 'Sistema', 'slug' => 'sistema', 'is_active' => true]);

        CampaignModel::create([
            'id' => (string) Str::uuid(),
            'game_system_id' => $system->id,
            'created_by' => $mary->id,
            'title' => 'La cripta olvidada',
            'slug' => 'la-cripta-olvidada',
            'status' => 'active',
            'frontend_creation_status' => 'approved',
            'is_published' => true,
            'accepts_new_players' => true,
            'max_players' => 4,
            'roster_access' => 'anyone',
        ]);
    }

    public function test_language_and_experience_level_are_not_made_up(): void
    {
        // Campaigns have no language or experience level columns: the page must not invent them
        $this->get('/campanas/la-cripta-olvidada')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Campaigns/Show', shouldExist: false)
                ->where('campaign.language', null)
                ->where('campaign.experienceLevel', null)
                ->where('campaign.experienceLevelLabel', null));
    }
}
