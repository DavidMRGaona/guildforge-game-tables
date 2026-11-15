<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Feature\Http\Controllers;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\GameTables\Domain\Enums\GameMasterRole;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\CampaignModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameMasterModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameSystemModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameTableModel;
use Tests\Support\Modules\ModuleTestCase;

/**
 * A director linked to an account is shown by the account's public name, or by its account name when it has
 * none, and only when they chose to show their name; otherwise by their title or role. The creator's account
 * name and the directors' account ids never reach the pages.
 */
final class GameMasterPublicNameTest extends ModuleTestCase
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

        $this->system = GameSystemModel::create(['id' => (string) Str::uuid(), 'name' => 'Sistema', 'slug' => 'sistema', 'is_active' => true]);
        $this->creator = UserModel::factory()->create(['name' => 'Quentin Creator', 'display_name' => null]);
    }

    public function test_a_table_shows_its_director_by_the_public_name_of_the_account(): void
    {
        $table = $this->table('la-cripta');
        $director = $this->account('Ana Account', 'Ana Reader');
        $this->attachToTable($table, $this->director($director, public: true));

        $response = $this->get('/mesas/la-cripta');

        $response->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('table.gameMasters.0.displayName', 'Ana Reader')
                ->missing('table.gameMasters.0.userId')
                ->missing('table.creatorName')
                ->missing('table.createdBy')
                ->where('table.mainGameMasterName', 'Ana Reader'));
        $this->assertStringNotContainsString('Ana Account', (string) $response->getContent());
        $this->assertStringNotContainsString((string) $director->id, (string) $response->getContent());
        $this->assertStringNotContainsString((string) $this->creator->id, (string) $response->getContent());
    }

    public function test_an_account_without_a_public_name_is_shown_by_its_account_name(): void
    {
        $table = $this->table('la-cripta');
        $this->attachToTable($table, $this->director($this->account('Ana Account', null), public: true));

        $this->get('/mesas/la-cripta')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('table.gameMasters.0.displayName', 'Ana Account')
                ->where('table.mainGameMasterName', 'Ana Account'));
    }

    public function test_a_director_who_hides_the_name_is_shown_by_the_title_on_the_page_and_the_cards(): void
    {
        $this->system->update(['game_master_title' => 'Narrador']);
        $table = $this->table('la-cripta');
        $this->attachToTable($table, $this->director($this->account('Ana Account', 'Ana Reader'), public: false));

        $page = $this->get('/mesas/la-cripta');
        $page->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('table.gameMasters.0.displayName', '')
                ->where('table.gameMasters.0.customTitle', 'Narrador')
                ->where('table.mainGameMasterName', 'Narrador'));

        $list = $this->get('/mesas');
        $list->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('tables.data.0.mainGameMasterName', 'Narrador')
                ->missing('tables.data.0.creatorName'));

        foreach ([$page, $list] as $response) {
            $this->assertStringNotContainsString('Ana Account', (string) $response->getContent());
            $this->assertStringNotContainsString('Ana Reader', (string) $response->getContent());
            $this->assertStringNotContainsString('Quentin Creator', (string) $response->getContent());
        }
    }

    public function test_a_table_card_shows_the_public_name_or_else_the_title(): void
    {
        $this->attachToTable($this->table('la-cripta'), $this->director($this->account('Ana Account', 'Ana Reader'), public: true));
        $this->attachToTable($this->table('el-faro', '+2 weeks'), $this->director($this->account('Bea Account', 'Bea Reader'), public: false));

        $this->get('/mesas')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('tables.data.0.mainGameMasterName', 'Ana Reader')
                ->where('tables.data.1.mainGameMasterName', $this->systemTitle()));
    }

    public function test_a_table_without_directors_never_falls_back_to_its_creator(): void
    {
        $this->table('la-cripta');

        $page = $this->get('/mesas/la-cripta');
        $page->assertOk()->assertInertia(fn (Assert $page) => $page->where('table.mainGameMasterName', ''));
        $list = $this->get('/mesas');
        $list->assertOk()->assertInertia(fn (Assert $page) => $page->where('tables.data.0.mainGameMasterName', ''));

        $this->assertStringNotContainsString('Quentin Creator', (string) $page->getContent());
        $this->assertStringNotContainsString('Quentin Creator', (string) $list->getContent());
    }

    public function test_a_campaign_and_its_sessions_follow_the_same_rule(): void
    {
        $campaign = $this->campaign('los-ecos');
        $hidden = $this->director($this->account('Bea Account', 'Bea Reader'), public: false);
        $campaign->gameMasters()->attach($this->director($this->account('Ana Account', 'Ana Reader'), public: true)->id, ['sort_order' => 1]);
        $campaign->gameMasters()->attach($hidden->id, ['sort_order' => 2]);
        $session = $this->table('sesion-1', campaign: $campaign);
        $this->attachToTable($session, $hidden);

        $page = $this->get('/campanas/los-ecos');
        $page->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('campaign.gameMasters.0.displayName', 'Ana Reader')
                ->where('campaign.gameMasters.1.displayName', '')
                ->missing('campaign.gameMasters.0.userId')
                ->missing('campaign.creatorName')
                ->missing('campaign.createdBy')
                ->where('campaign.mainGameMasterName', 'Ana Reader')
                ->where('campaign.gameTables.0.mainGameMasterName', $this->systemTitle())
                ->missing('campaign.gameTables.0.creatorName'));

        $list = $this->get('/campanas');
        $list->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('campaigns.data.0.mainGameMasterName', 'Ana Reader')
                ->missing('campaigns.data.0.creatorName'));

        foreach ([$page, $list] as $response) {
            $this->assertStringNotContainsString('Ana Account', (string) $response->getContent());
            $this->assertStringNotContainsString('Bea Account', (string) $response->getContent());
            $this->assertStringNotContainsString('Bea Reader', (string) $response->getContent());
            $this->assertStringNotContainsString('Quentin Creator', (string) $response->getContent());
            $this->assertStringNotContainsString((string) $this->creator->id, (string) $response->getContent());
        }
    }

    public function test_a_campaign_session_without_directors_of_its_own_takes_the_campaigns_main_director(): void
    {
        $campaign = $this->campaign('los-ecos');
        $campaign->gameMasters()->attach($this->director($this->account('Ana Account', 'Ana Reader'), public: true)->id, ['sort_order' => 1]);
        $this->table('sesion-1', campaign: $campaign);

        $this->get('/campanas/los-ecos')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('campaign.gameTables.0.mainGameMasterName', 'Ana Reader'));
    }

    public function test_a_card_falls_back_to_the_role_when_the_system_has_no_title(): void
    {
        $this->system->update(['game_master_title' => '']);
        $this->attachToTable($this->table('la-cripta'), $this->director($this->account('Ana Account', 'Ana Reader'), public: false));

        $this->get('/mesas')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('tables.data.0.mainGameMasterName', GameMasterRole::Main->label()));
    }

    /**
     * The game system's title for its directors, which the main director without a name of their own goes by.
     */
    private function systemTitle(): string
    {
        return (string) $this->system->fresh()?->game_master_title;
    }

    private function account(string $name, ?string $publicName): UserModel
    {
        return UserModel::factory()->create(['name' => $name, 'display_name' => $publicName]);
    }

    private function director(UserModel $account, bool $public): GameMasterModel
    {
        return GameMasterModel::create([
            'id' => (string) Str::uuid(),
            'user_id' => $account->id,
            'role' => GameMasterRole::Main,
            'notify_by_email' => true,
            'is_name_public' => $public,
        ]);
    }

    private function attachToTable(GameTableModel $table, GameMasterModel $director): void
    {
        $table->gameMasters()->attach($director->id, ['source' => 'local', 'excluded' => false, 'sort_order' => 1]);
    }

    private function table(string $slug, string $startsIn = '+1 week', ?CampaignModel $campaign = null): GameTableModel
    {
        return GameTableModel::create([
            'id' => (string) Str::uuid(),
            'game_system_id' => $this->system->id,
            'campaign_id' => $campaign?->id,
            'created_by' => $this->creator->id,
            'title' => 'Mesa '.$slug,
            'slug' => $slug,
            'starts_at' => now()->modify($startsIn),
            'duration_minutes' => 240,
            'table_type' => $campaign instanceof CampaignModel ? 'campaign_session' : 'one_shot',
            'table_format' => 'in_person',
            'status' => 'scheduled',
            'min_players' => 1,
            'max_players' => 3,
            'language' => 'es',
            'registration_type' => 'everyone',
            'auto_confirm' => false,
            'is_published' => true,
        ]);
    }

    private function campaign(string $slug): CampaignModel
    {
        return CampaignModel::create([
            'id' => (string) Str::uuid(),
            'game_system_id' => $this->system->id,
            'created_by' => $this->creator->id,
            'title' => 'Campaña '.$slug,
            'slug' => $slug,
            'status' => 'active',
            'frontend_creation_status' => 'approved',
            'is_published' => true,
            'accepts_new_players' => true,
            'max_players' => 4,
            'roster_access' => 'anyone',
        ]);
    }
}
