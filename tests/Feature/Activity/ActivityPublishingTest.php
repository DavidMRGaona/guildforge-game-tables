<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Feature\Activity;

use App\Application\Activity\Services\ActivityPublisherInterface;
use App\Domain\Activity\Events\ActivityRecorded;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Modules\GameTables\Application\Services\CampaignRosterServiceInterface;
use Modules\GameTables\Application\Services\GameMasterServiceInterface;
use Modules\GameTables\Application\Services\GameTableServiceInterface;
use Modules\GameTables\Domain\Enums\GameMasterRole;
use Modules\GameTables\Domain\Enums\TableStatus;
use Modules\GameTables\Domain\Events\CampaignInvitationAccepted;
use Modules\GameTables\Domain\Events\CampaignJoinRequestAccepted;
use Modules\GameTables\Domain\Events\CampaignRosterMemberAdded;
use Modules\GameTables\Domain\Events\CampaignRosterMemberLeft;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\CampaignModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameMasterModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameSystemModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameTableModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\ParticipantModel;
use Tests\Support\Modules\ModuleTestCase;

/**
 * Every change that may change the facts of a session or a campaign is published through the
 * core (ActivityRecorded, after the commit), whatever made it; changes that cannot change them say
 * nothing.
 */
final class ActivityPublishingTest extends ModuleTestCase
{
    private const string SESSION = 'game-tables.session';

    private const string CAMPAIGN = 'game-tables.campaign';

    protected ?string $moduleName = 'game-tables';

    protected bool $autoEnableModule = true;

    private GameSystemModel $system;

    protected function setUp(): void
    {
        parent::setUp();

        $this->useUtcStorage();
        $this->system = GameSystemModel::create(['id' => (string) Str::uuid(), 'name' => 'Sistema de ejemplo', 'slug' => Str::random(10), 'is_active' => true]);
    }

    public function test_completing_a_table_publishes_it(): void
    {
        $table = $this->table(['status' => 'in_progress']);
        $this->fakeActivity();

        $this->app->make(GameTableServiceInterface::class)->complete($table->id);

        $this->assertRecorded(self::SESSION, $table->id);
    }

    public function test_completing_through_the_status_field_publishes_it(): void
    {
        $table = $this->table(['status' => 'in_progress']);
        $this->fakeActivity();

        $this->app->make(GameTableServiceInterface::class)->changeStatus($table->id, TableStatus::Completed);

        $this->assertRecorded(self::SESSION, $table->id);
    }

    public function test_saving_a_table_that_is_not_completed_says_nothing(): void
    {
        $table = $this->table(['status' => 'scheduled']);
        $this->fakeActivity();

        $table->update(['title' => 'Otra mesa']);
        $this->participant($table, UserModel::factory()->create(), 'confirmed');

        $this->assertNothingOfThisModuleRecorded();
    }

    public function test_changing_a_completed_table_publishes_it(): void
    {
        $table = $this->table();
        $this->fakeActivity();

        $table->update(['is_published' => false]);

        $this->assertRecorded(self::SESSION, $table->id);
    }

    public function test_a_table_that_leaves_completed_publishes_it(): void
    {
        $table = $this->table();
        $this->fakeActivity();

        $table->update(['status' => 'cancelled']);

        $this->assertRecorded(self::SESSION, $table->id);
    }

    public function test_deleting_a_completed_table_publishes_it(): void
    {
        $table = $this->table();
        $this->fakeActivity();

        $table->delete();

        $this->assertRecorded(self::SESSION, $table->id);
    }

    public function test_a_participant_confirmed_cancelled_or_removed_after_completion_publishes_the_table(): void
    {
        $table = $this->table();
        $player = $this->participant($table, UserModel::factory()->create(), 'pending');
        $removed = $this->participant($table, UserModel::factory()->create(), 'confirmed');

        $this->fakeActivity();
        $player->update(['status' => 'confirmed']);
        $this->assertRecorded(self::SESSION, $table->id);

        $this->fakeActivity();
        $player->update(['status' => 'cancelled']);
        $this->assertRecorded(self::SESSION, $table->id);

        $this->fakeActivity();
        $removed->delete();
        $this->assertRecorded(self::SESSION, $table->id);
    }

    public function test_registrations_that_never_made_anybody_a_player_say_nothing(): void
    {
        $table = $this->table();
        $this->fakeActivity();

        $pending = $this->participant($table, UserModel::factory()->create(), 'pending');
        $pending->update(['status' => 'waiting_list']);
        $pending->delete();
        $confirmed = $this->participant($this->table(['status' => 'scheduled']), UserModel::factory()->create(), 'confirmed');
        $confirmed->update(['notes' => 'Llega tarde']);

        Event::assertNotDispatched(ActivityRecorded::class, static fn (ActivityRecorded $event): bool => $event->subjectId === $table->id);
        Event::assertNotDispatched(ActivityRecorded::class, static fn (ActivityRecorded $event): bool => $event->subjectId === $confirmed->game_table_id);
    }

    public function test_saving_a_completed_table_without_changing_its_facts_says_nothing(): void
    {
        $table = $this->table();
        $this->fakeActivity();

        $table->update(['title' => 'Otra mesa', 'synopsis' => 'Otro resumen']);

        $this->assertNothingOfThisModuleRecorded();
    }

    public function test_publishing_a_completed_table_publishes_it(): void
    {
        $table = $this->table(['is_published' => false]);
        $this->fakeActivity();

        $table->update(['is_published' => true]);

        $this->assertRecorded(self::SESSION, $table->id);
    }

    public function test_a_completed_session_moved_in_time_or_deleted_publishes_its_campaign(): void
    {
        $campaign = $this->campaign();
        $session = $this->table(['campaign_id' => $campaign->id, 'table_type' => 'campaign_session']);

        $this->fakeActivity();
        $session->update(['starts_at' => now()->subMonth()]);
        $this->assertRecorded(self::CAMPAIGN, $campaign->id);

        $this->fakeActivity();
        $session->delete();
        $this->assertRecorded(self::CAMPAIGN, $campaign->id);
    }

    public function test_completing_a_session_publishes_its_completed_campaign(): void
    {
        $campaign = $this->campaign();
        $session = $this->table(['campaign_id' => $campaign->id, 'table_type' => 'campaign_session', 'status' => 'in_progress']);
        $this->fakeActivity();

        $this->app->make(GameTableServiceInterface::class)->complete($session->id);

        $this->assertRecorded(self::SESSION, $session->id);
        $this->assertRecorded(self::CAMPAIGN, $campaign->id);
    }

    public function test_changing_the_direction_of_a_completed_table_publishes_it(): void
    {
        $table = $this->table();
        $service = $this->app->make(GameMasterServiceInterface::class);
        $user = UserModel::factory()->create();

        $this->fakeActivity();
        $gm = $service->addToTable($table->id, ['user_id' => $user->id, 'first_name' => 'John', 'last_name' => 'Doe', 'role' => GameMasterRole::Main->value]);
        $this->assertRecorded(self::SESSION, $table->id);

        $this->fakeActivity();
        $service->removeFromTable($table->id, $gm->id);
        $this->assertRecorded(self::SESSION, $table->id);
    }

    public function test_excluding_an_inherited_director_publishes_the_session(): void
    {
        $campaign = $this->campaign(['status' => 'active']);
        $gm = $this->directCampaign($campaign, UserModel::factory()->create());
        $session = $this->table(['campaign_id' => $campaign->id, 'table_type' => 'campaign_session']);
        $this->fakeActivity();

        $this->app->make(GameMasterServiceInterface::class)->excludeFromTable($session->id, $gm->id);

        $this->assertRecorded(self::SESSION, $session->id);
    }

    public function test_including_an_inherited_director_again_publishes_the_session(): void
    {
        $campaign = $this->campaign(['status' => 'active']);
        $gm = $this->directCampaign($campaign, UserModel::factory()->create());
        $session = $this->table(['campaign_id' => $campaign->id, 'table_type' => 'campaign_session']);
        $service = $this->app->make(GameMasterServiceInterface::class);
        $service->excludeFromTable($session->id, $gm->id);
        $this->fakeActivity();

        $service->includeInTable($session->id, $gm->id);

        $this->assertRecorded(self::SESSION, $session->id);
    }

    public function test_a_game_master_who_shows_or_hides_their_name_publishes_what_they_ran(): void
    {
        $campaign = $this->campaign();
        $gm = $this->directCampaign($campaign, UserModel::factory()->create());
        $session = $this->table(['campaign_id' => $campaign->id, 'table_type' => 'campaign_session']);
        $this->fakeActivity();

        $gm->update(['is_name_public' => false]);

        $this->assertRecorded(self::CAMPAIGN, $campaign->id);
        $this->assertRecorded(self::SESSION, $session->id);
    }

    public function test_deleting_a_game_master_publishes_what_they_ran(): void
    {
        $table = $this->table();
        $gm = $this->gameMaster(UserModel::factory()->create());
        $table->gameMasters()->attach($gm->id, ['source' => 'local', 'excluded' => false, 'sort_order' => 1]);
        $this->fakeActivity();

        $gm->delete();

        $this->assertRecorded(self::SESSION, $table->id);
    }

    public function test_a_game_master_linked_to_another_account_publishes_what_they_ran(): void
    {
        $table = $this->table();
        $gm = $this->gameMaster(UserModel::factory()->create());
        $table->gameMasters()->attach($gm->id, ['source' => 'local', 'excluded' => false, 'sort_order' => 1]);
        $this->fakeActivity();

        $gm->update(['user_id' => UserModel::factory()->create()->id]);

        $this->assertRecorded(self::SESSION, $table->id);
    }

    public function test_completing_a_campaign_publishes_it(): void
    {
        $campaign = $this->campaign(['status' => 'active']);
        $this->fakeActivity();

        $campaign->update(['status' => 'completed']);

        $this->assertRecorded(self::CAMPAIGN, $campaign->id);
    }

    public function test_a_campaign_completed_twice_is_published_each_time(): void
    {
        $campaign = $this->campaign();
        $this->fakeActivity();

        $campaign->update(['status' => 'active']);
        $campaign->update(['status' => 'completed']);

        Event::assertDispatchedTimes(ActivityRecorded::class, 2);
        $this->assertRecorded(self::CAMPAIGN, $campaign->id);
    }

    public function test_saving_a_campaign_that_is_not_completed_says_nothing(): void
    {
        $campaign = $this->campaign(['status' => 'active']);
        $this->fakeActivity();

        $campaign->update(['title' => 'Otra campaña']);

        $this->assertNothingOfThisModuleRecorded();
    }

    public function test_a_roster_change_while_completed_publishes_the_campaign(): void
    {
        $campaign = $this->campaign();
        $player = UserModel::factory()->create();
        DB::table('gametables_campaign_players')->insert(['campaign_id' => $campaign->id, 'user_id' => $player->id, 'created_at' => now(), 'updated_at' => now()]);
        $this->fakeActivity();

        $this->app->make(CampaignRosterServiceInterface::class)->removeMember($campaign->id, $player->id, $campaign->created_by);

        $this->assertRecorded(self::CAMPAIGN, $campaign->id);
    }

    public function test_changing_the_direction_of_a_completed_campaign_publishes_it_and_its_completed_sessions(): void
    {
        $campaign = $this->campaign();
        $session = $this->table(['campaign_id' => $campaign->id, 'table_type' => 'campaign_session']);
        $upcoming = $this->table(['campaign_id' => $campaign->id, 'table_type' => 'campaign_session', 'status' => 'scheduled']);
        $this->fakeActivity();

        $this->directCampaign($campaign, UserModel::factory()->create());

        $this->assertRecorded(self::CAMPAIGN, $campaign->id);
        $this->assertRecorded(self::SESSION, $session->id);
        Event::assertNotDispatched(ActivityRecorded::class, static fn (ActivityRecorded $event): bool => $event->subjectId === $upcoming->id);
    }

    public function test_removing_a_campaign_director_publishes_it_and_its_completed_sessions(): void
    {
        $campaign = $this->campaign();
        $gm = $this->directCampaign($campaign, UserModel::factory()->create());
        $session = $this->table(['campaign_id' => $campaign->id, 'table_type' => 'campaign_session']);
        $this->fakeActivity();

        $this->app->make(GameMasterServiceInterface::class)->removeFromCampaign($campaign->id, $gm->id);

        $this->assertRecorded(self::CAMPAIGN, $campaign->id);
        $this->assertRecorded(self::SESSION, $session->id);
    }

    public function test_every_roster_change_publishes_a_completed_campaign(): void
    {
        // The service refuses to add to a finished campaign: the roster events themselves, as
        // CampaignRosterService and CampaignInvitationService dispatch them
        $campaign = $this->campaign();
        $userId = UserModel::factory()->create()->id;
        $events = [
            new CampaignRosterMemberAdded($campaign->id, $userId, []),
            new CampaignRosterMemberLeft($campaign->id, $userId),
            new CampaignJoinRequestAccepted((string) Str::uuid(), $campaign->id, $userId, []),
            new CampaignInvitationAccepted($campaign->id, $userId),
        ];

        foreach ($events as $event) {
            $this->fakeActivity();
            Event::dispatch($event);
            $this->assertRecorded(self::CAMPAIGN, $campaign->id);
        }
    }

    public function test_publishing_a_completed_campaign_publishes_it_and_its_completed_sessions(): void
    {
        $campaign = $this->campaign(['is_published' => false]);
        $session = $this->table(['campaign_id' => $campaign->id, 'table_type' => 'campaign_session']);
        $this->fakeActivity();

        $campaign->update(['is_published' => true]);

        $this->assertRecorded(self::CAMPAIGN, $campaign->id);
        $this->assertRecorded(self::SESSION, $session->id);
    }

    public function test_unpublishing_a_campaign_publishes_its_completed_sessions(): void
    {
        $campaign = $this->campaign(['status' => 'active']);
        $session = $this->table(['campaign_id' => $campaign->id, 'table_type' => 'campaign_session']);
        $this->fakeActivity();

        $campaign->update(['is_published' => false]);

        $this->assertRecorded(self::SESSION, $session->id);
    }

    public function test_deleting_a_completed_campaign_publishes_it_and_its_completed_sessions(): void
    {
        $campaign = $this->campaign();
        $session = $this->table(['campaign_id' => $campaign->id, 'table_type' => 'campaign_session']);
        $this->fakeActivity();

        $campaign->delete();

        $this->assertRecorded(self::CAMPAIGN, $campaign->id);
        $this->assertRecorded(self::SESSION, $session->id);
    }

    public function test_renaming_a_game_system_publishes_what_was_played_with_it(): void
    {
        $table = $this->table();
        $campaign = $this->campaign();
        $this->fakeActivity();

        $this->system->update(['name' => 'Sistema renombrado']);

        $this->assertRecorded(self::SESSION, $table->id);
        $this->assertRecorded(self::CAMPAIGN, $campaign->id);
    }

    public function test_deleting_an_account_for_good_publishes_what_it_took_part_in(): void
    {
        $player = UserModel::factory()->create();
        $table = $this->table();
        $this->participant($table, $player, 'confirmed');
        $campaign = $this->campaign();
        DB::table('gametables_campaign_players')->insert(['campaign_id' => $campaign->id, 'user_id' => $player->id, 'created_at' => now(), 'updated_at' => now()]);
        $created = $this->table(['created_by' => $player->id]);
        // Its campaigns go with it at database level and leave their sessions on their own
        $createdCampaign = $this->campaign(['created_by' => $player->id, 'status' => 'active']);
        $orphaned = $this->table(['campaign_id' => $createdCampaign->id, 'table_type' => 'campaign_session']);
        $this->fakeActivity();

        $player->forceDelete();

        $this->assertRecorded(self::SESSION, $table->id);
        $this->assertRecorded(self::SESSION, $created->id);
        $this->assertRecorded(self::SESSION, $orphaned->id);
        $this->assertRecorded(self::CAMPAIGN, $campaign->id);
    }

    public function test_nothing_is_published_when_the_change_rolls_back(): void
    {
        $table = $this->table();
        $this->fakeActivity();

        try {
            DB::transaction(function () use ($table): void {
                $table->update(['is_published' => false]);

                throw new \RuntimeException('Rolled back');
            });
        } catch (\RuntimeException) {
        }

        $this->assertNothingOfThisModuleRecorded();
    }

    /**
     * The core's publisher is scoped and keeps the dispatcher it was built with: build it again
     * after faking, as a new request would.
     */
    private function fakeActivity(): void
    {
        Event::fake([ActivityRecorded::class]);
        $this->app->forgetInstance(ActivityPublisherInterface::class);
    }

    /**
     * Only this module's facts: the core and other modules publish their own (an account created
     * in the test is `core.account`), which say nothing about tables or campaigns.
     */
    private function assertNothingOfThisModuleRecorded(): void
    {
        Event::assertNotDispatched(ActivityRecorded::class, static fn (ActivityRecorded $event): bool => str_starts_with($event->sourceType, 'game-tables.'));
    }

    private function assertRecorded(string $type, string $id): void
    {
        Event::assertDispatched(ActivityRecorded::class, static fn (ActivityRecorded $event): bool => $event->sourceType === $type && $event->subjectId === $id);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function table(array $attributes = []): GameTableModel
    {
        return GameTableModel::create(array_merge([
            'id' => (string) Str::uuid(),
            'game_system_id' => $this->system->id,
            'created_by' => UserModel::factory()->create()->id,
            'title' => 'Mesa de ejemplo',
            'slug' => 'mesa-'.Str::lower(Str::random(8)),
            'starts_at' => now()->subDay(),
            'duration_minutes' => 240,
            'table_type' => 'one_shot',
            'table_format' => 'online',
            'status' => 'completed',
            'min_players' => 1,
            'max_players' => 5,
            'max_spectators' => 2,
            'language' => 'es',
            'registration_type' => 'everyone',
            'is_published' => true,
        ], $attributes));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function campaign(array $attributes = []): CampaignModel
    {
        return CampaignModel::create(array_merge([
            'id' => (string) Str::uuid(),
            'game_system_id' => $this->system->id,
            'created_by' => UserModel::factory()->create()->id,
            'title' => 'Campaña de ejemplo',
            'slug' => 'campana-'.Str::lower(Str::random(8)),
            'status' => 'completed',
            'is_published' => true,
        ], $attributes));
    }

    private function directCampaign(CampaignModel $campaign, UserModel $user): GameMasterModel
    {
        $gm = $this->gameMaster($user);
        $campaign->gameMasters()->attach($gm->id, ['sort_order' => 1]);

        return $gm;
    }

    private function gameMaster(UserModel $user): GameMasterModel
    {
        return GameMasterModel::create([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'first_name' => 'John',
            'last_name' => 'Doe',
            'role' => GameMasterRole::Main,
            'notify_by_email' => true,
            'is_name_public' => true,
        ]);
    }

    private function participant(GameTableModel $table, UserModel $user, string $status): ParticipantModel
    {
        return ParticipantModel::create([
            'id' => (string) Str::uuid(),
            'game_table_id' => $table->id,
            'user_id' => $user->id,
            'role' => 'player',
            'status' => $status,
            'confirmed_at' => $status === 'confirmed' ? now() : null,
        ]);
    }
}
