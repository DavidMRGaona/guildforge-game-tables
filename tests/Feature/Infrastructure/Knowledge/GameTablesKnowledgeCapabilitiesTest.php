<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Feature\Infrastructure\Knowledge;

use App\Application\Knowledge\Services\KnowledgeCapabilityRegistryInterface;
use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Support\Str;
use Modules\GameTables\Domain\Entities\EventGameTableConfig;
use Modules\GameTables\Domain\Enums\CreationAccessLevel;
use Modules\GameTables\Domain\Enums\FrontendCreationStatus;
use Modules\GameTables\Domain\Enums\GameMasterRole;
use Modules\GameTables\Domain\Enums\LocationMode;
use Modules\GameTables\Domain\Enums\SchedulingMode;
use Modules\GameTables\Domain\Repositories\EventGameTableConfigRepositoryInterface;
use Modules\GameTables\Domain\ValueObjects\EligibilityOverride;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\CampaignModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameMasterModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameSystemModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameTableModel;
use stdClass;
use Tests\Support\Modules\ModuleTestCase;

/**
 * The capabilities the module's guides use answer as the pages do: the create pages, the
 * registrations page of a table and the roster page of a campaign.
 */
final class GameTablesKnowledgeCapabilitiesTest extends ModuleTestCase
{
    protected ?string $moduleName = 'game-tables';

    protected bool $autoEnableModule = true;

    private KnowledgeCapabilityRegistryInterface $capabilities;

    private UserModel $jane;

    private GameSystemModel $gameSystem;

    protected function setUp(): void
    {
        parent::setUp();

        $this->configure('registered');
        $this->capabilities = $this->app->make(KnowledgeCapabilityRegistryInterface::class);
        $this->jane = UserModel::factory()->create(['name' => 'Jane Doe']);
        $this->gameSystem = GameSystemModel::create([
            'id' => (string) Str::uuid(),
            'name' => 'Sistema de prueba',
            'slug' => 'sistema-de-prueba',
            'is_active' => true,
        ]);
    }

    public function test_the_module_registers_its_capabilities(): void
    {
        $keys = $this->capabilities->keys();

        foreach (['can_create_tables', 'can_create_campaigns', 'can_create', 'manages_registrations', 'adds_campaign_sessions', 'manages_roster'] as $name) {
            $this->assertContains('game-tables.'.$name, $keys);
        }
    }

    public function test_creating_follows_the_creation_settings(): void
    {
        $this->assertTrue($this->passes('can_create_tables'));
        $this->assertTrue($this->passes('can_create_campaigns'));
        $this->assertTrue($this->passes('can_create'));

        $this->configure('registered', allowedContent: 'tables');
        $this->assertTrue($this->passes('can_create_tables'));
        $this->assertFalse($this->passes('can_create_campaigns'));
        $this->assertTrue($this->passes('can_create'));

        $this->configure('permission');
        $this->assertFalse($this->passes('can_create_tables'));
        $this->assertFalse($this->passes('can_create_campaigns'));
        $this->assertFalse($this->passes('can_create'));

        $this->configure('registered', enabled: false);
        $this->assertFalse($this->passes('can_create_tables'));
        $this->assertFalse($this->passes('can_create_campaigns'));
    }

    public function test_a_campaign_director_refused_by_the_general_rules_may_still_create_its_sessions(): void
    {
        $this->configure('permission');
        $this->campaignDirectedByJane();

        $this->assertTrue($this->passes('can_create_tables'));
        $this->assertTrue($this->passes('adds_campaign_sessions'));
        $this->assertTrue($this->passes('can_create'));
        $this->assertFalse($this->passes('can_create_campaigns'));
    }

    public function test_an_event_open_to_everyone_lets_people_the_general_rules_refuse_create_tables(): void
    {
        $this->configure('permission');
        $this->eventWithTables(EligibilityOverride::create(CreationAccessLevel::Registered));

        $this->assertTrue($this->passes('can_create_tables'));
        $this->assertTrue($this->passes('can_create'));
    }

    public function test_an_event_without_its_own_rule_follows_the_general_rules(): void
    {
        $this->configure('permission');
        $this->eventWithTables();

        $this->assertFalse($this->passes('can_create_tables'));
    }

    public function test_managing_registrations_needs_a_published_single_table_that_delegates_them(): void
    {
        $this->assertFalse($this->passes('manages_registrations'));

        $table = $this->tableDirectedByJane(delegates: false);
        $this->assertFalse($this->passes('manages_registrations'));

        $table->update(['delegates_registrations' => true]);
        $this->assertTrue($this->passes('manages_registrations'));

        $table->update(['is_published' => false]);
        $this->assertFalse($this->passes('manages_registrations'));
    }

    public function test_sessions_and_roster_need_an_approved_or_published_campaign_directed_by_the_person(): void
    {
        $this->assertFalse($this->passes('adds_campaign_sessions'));
        $this->assertFalse($this->passes('manages_roster'));

        $campaign = $this->campaignDirectedByJane(published: false, creationStatus: FrontendCreationStatus::Draft);
        $this->assertFalse($this->passes('adds_campaign_sessions'));
        $this->assertFalse($this->passes('manages_roster'));

        $campaign->update(['frontend_creation_status' => FrontendCreationStatus::Approved]);
        $this->assertTrue($this->passes('adds_campaign_sessions'));
        $this->assertTrue($this->passes('manages_roster'));

        $other = UserModel::factory()->create();
        $this->assertFalse($this->capabilities->evaluate('game-tables.manages_roster', $other));
    }

    public function test_something_that_is_not_a_user_passes_nothing(): void
    {
        foreach (['can_create_tables', 'can_create_campaigns', 'can_create', 'manages_registrations', 'adds_campaign_sessions', 'manages_roster'] as $name) {
            $this->assertFalse($this->capabilities->evaluate('game-tables.'.$name, new stdClass));
        }
    }

    private function passes(string $name): bool
    {
        $passes = $this->capabilities->evaluate('game-tables.'.$name, $this->jane);
        // Null means an unknown or failing capability: never a valid answer here
        $this->assertNotNull($passes, $name);

        return $passes;
    }

    private function configure(string $accessLevel, string $allowedContent = 'both', bool $enabled = true): void
    {
        config()->set('modules.settings.game-tables', [
            'frontend_creation' => [
                'enabled' => $enabled,
                'access_level' => $accessLevel,
                'required_permission' => 'gametables.create_from_web',
                'allowed_content' => $allowedContent,
                'publication' => ['mode' => 'approval'],
            ],
        ]);
    }

    private function eventWithTables(?EligibilityOverride $override = null): EventModel
    {
        $event = EventModel::factory()->published()->upcoming()->create();
        app(EventGameTableConfigRepositoryInterface::class)->save(new EventGameTableConfig(
            eventId: (string) $event->id,
            tablesEnabled: true,
            schedulingMode: SchedulingMode::FreeSchedule,
            timeSlots: [],
            locationMode: LocationMode::FreeChoice,
            eligibilityOverride: $override,
        ));

        return $event;
    }

    private function janeAsGameMaster(): GameMasterModel
    {
        return GameMasterModel::create([
            'id' => (string) Str::uuid(),
            'user_id' => $this->jane->id,
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'role' => GameMasterRole::Main,
            'notify_by_email' => true,
            'is_name_public' => true,
        ]);
    }

    private function campaignDirectedByJane(bool $published = true, ?FrontendCreationStatus $creationStatus = null): CampaignModel
    {
        $campaign = CampaignModel::create([
            'id' => (string) Str::uuid(),
            'game_system_id' => $this->gameSystem->id,
            'created_by' => UserModel::factory()->create()->id,
            'title' => 'Campaña de Jane',
            'slug' => 'campana-de-jane-'.Str::random(6),
            'frequency' => 'weekly',
            'status' => 'recruiting',
            'max_players' => 5,
            'is_published' => $published,
            'frontend_creation_status' => $creationStatus,
        ]);
        $campaign->gameMasters()->attach($this->janeAsGameMaster()->id, ['sort_order' => 1]);

        return $campaign;
    }

    private function tableDirectedByJane(bool $delegates): GameTableModel
    {
        $table = GameTableModel::create([
            'id' => (string) Str::uuid(),
            'game_system_id' => $this->gameSystem->id,
            'created_by' => UserModel::factory()->create()->id,
            'title' => 'Mesa de Jane',
            'slug' => 'mesa-de-jane',
            'starts_at' => now()->addWeek(),
            'duration_minutes' => 240,
            'table_type' => 'one_shot',
            'table_format' => 'in_person',
            'status' => 'scheduled',
            'min_players' => 1,
            'max_players' => 5,
            'language' => 'es',
            'experience_level' => 'none',
            'character_creation' => 'pre_generated',
            'registration_type' => 'everyone',
            'auto_confirm' => false,
            'delegates_registrations' => $delegates,
            'is_published' => true,
            'published_at' => now(),
        ]);
        $table->gameMasters()->attach($this->janeAsGameMaster()->id, ['source' => 'local', 'excluded' => false, 'sort_order' => 1]);

        return $table;
    }
}
