<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Integration\Publications;

use App\Application\Publications\Contracts\PublicationRosterInterface;
use App\Application\Publications\DTOs\PublicationMembershipDTO;
use App\Application\Publications\DTOs\PublicationRosterDTO;
use App\Application\Publications\Services\PublicationSourceRegistryInterface;
use App\Domain\Publications\Events\PublicationRosterChanged;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use DateTimeImmutable;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Modules\GameTables\Application\Services\GameMasterServiceInterface;
use Modules\GameTables\Domain\Events\CampaignRosterMemberAdded;
use Modules\GameTables\Domain\Events\CampaignRosterMemberLeft;
use Modules\GameTables\Domain\Events\CampaignRosterMemberRemoved;
use Modules\GameTables\Domain\Repositories\CampaignRosterRepositoryInterface;
use Modules\GameTables\Domain\ValueObjects\CampaignId;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\CampaignModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameMasterModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameSystemModel;
use Modules\GameTables\Infrastructure\Publications\CampaignPublicationSource;
use Tests\Support\Modules\ModuleTestCase;

/**
 * The campaigns offer who plays (their roster) and who directs (their game masters with an
 * account), so other modules can give them private spaces (core#467).
 */
final class CampaignRosterPublicationTest extends ModuleTestCase
{
    protected ?string $moduleName = 'game-tables';

    protected bool $autoEnableModule = true;

    private GameSystemModel $system;

    protected function setUp(): void
    {
        parent::setUp();

        $this->useUtcStorage();
        $this->system = GameSystemModel::create(['id' => (string) Str::uuid(), 'name' => 'Sistema de ejemplo', 'slug' => Str::random(10), 'is_active' => true]);
    }

    public function test_the_campaign_source_offers_its_roster(): void
    {
        $this->assertInstanceOf(PublicationRosterInterface::class, $this->app->make(PublicationSourceRegistryInterface::class)->source('campaign'));
    }

    public function test_the_roster_has_the_players_and_the_directors_with_an_account(): void
    {
        $campaign = $this->campaign();
        $player = $this->player($campaign);
        $main = $this->director($campaign, 'main');
        $coGm = $this->director($campaign, 'co_gm');
        // A director without an account directs, but has nobody to give a space to
        $this->gameMasters()->addToCampaign($campaign->id, ['first_name' => 'Sin cuenta', 'role' => 'co_gm']);

        $roster = $this->source()->roster($campaign->id);

        $this->assertInstanceOf(PublicationRosterDTO::class, $roster);
        $this->assertSame([$player], $roster->memberIds);
        $this->assertEqualsCanonicalizing([$main, $coGm], $roster->leaderIds);
    }

    public function test_campaigns_that_are_not_publications_have_no_roster(): void
    {
        $this->assertNull($this->source()->roster($this->campaign(['is_published' => false])->id));
        $this->assertNull($this->source()->roster((string) Str::uuid()));
        $this->assertNull($this->source()->roster('not-a-uuid'));
    }

    public function test_a_finished_campaign_still_published_keeps_its_roster(): void
    {
        $campaign = $this->campaign(['status' => 'completed']);
        $player = $this->player($campaign);

        $this->assertSame([$player], $this->source()->roster($campaign->id)?->memberIds);
    }

    public function test_the_campaigns_of_a_person_say_whether_they_direct_them(): void
    {
        $user = UserModel::factory()->create()->id;
        $plays = $this->campaign();
        $directs = $this->campaign();
        $both = $this->campaign();
        $this->player($plays, $user);
        $this->director($directs, 'co_gm', $user);
        $this->player($both, $user);
        $this->director($both, 'main', $user);

        $memberships = collect($this->source()->rostersOf($user))
            ->mapWithKeys(static fn (PublicationMembershipDTO $membership): array => [$membership->id => $membership->isLeader]);

        $this->assertCount(3, $memberships);
        $this->assertFalse($memberships[$plays->id]);
        $this->assertTrue($memberships[$directs->id]);
        $this->assertTrue($memberships[$both->id]);
    }

    public function test_finished_cancelled_and_unpublished_campaigns_are_left_out_of_a_persons_list(): void
    {
        $user = UserModel::factory()->create()->id;

        foreach ([['status' => 'completed'], ['status' => 'cancelled'], ['is_published' => false]] as $attributes) {
            $this->player($this->campaign($attributes), $user);
        }

        $this->assertSame([], $this->source()->rostersOf($user));
        $this->assertSame([], $this->source()->rostersOf('not-a-uuid'));
    }

    public function test_joining_leaving_and_being_removed_tell_the_listeners(): void
    {
        $campaign = $this->campaign();
        $user = (string) Str::uuid();
        Event::fake([PublicationRosterChanged::class]);

        event(new CampaignRosterMemberAdded($campaign->id, $user, []));
        event(new CampaignRosterMemberLeft($campaign->id, $user));
        event(new CampaignRosterMemberRemoved($campaign->id, $user));

        Event::assertDispatchedTimes(PublicationRosterChanged::class, 3);
        Event::assertDispatched(PublicationRosterChanged::class, static fn (PublicationRosterChanged $change): bool => $change->type === 'campaign'
            && $change->id === $campaign->id
            && $change->userIds === [$user]);
    }

    public function test_the_roster_of_an_unpublished_campaign_says_nothing(): void
    {
        $campaign = $this->campaign(['is_published' => false]);
        Event::fake([PublicationRosterChanged::class]);

        event(new CampaignRosterMemberLeft($campaign->id, (string) Str::uuid()));
        $this->director($campaign, 'main');

        Event::assertNotDispatched(PublicationRosterChanged::class);
    }

    public function test_adding_and_removing_a_director_tell_the_listeners(): void
    {
        $campaign = $this->campaign();
        $user = UserModel::factory()->create()->id;
        Event::fake([PublicationRosterChanged::class]);

        $gameMaster = $this->gameMasters()->addToCampaign($campaign->id, ['user_id' => $user, 'role' => 'co_gm']);
        $this->gameMasters()->removeFromCampaign($campaign->id, $gameMaster->id);

        Event::assertDispatchedTimes(PublicationRosterChanged::class, 2);
        Event::assertDispatched(PublicationRosterChanged::class, static fn (PublicationRosterChanged $change): bool => $change->id === $campaign->id && $change->userIds === [$user]);
    }

    public function test_a_director_without_an_account_says_nothing(): void
    {
        $campaign = $this->campaign();
        Event::fake([PublicationRosterChanged::class]);

        $this->gameMasters()->addToCampaign($campaign->id, ['first_name' => 'Sin cuenta', 'role' => 'main']);

        Event::assertNotDispatched(PublicationRosterChanged::class);
    }

    public function test_linking_a_director_to_another_account_tells_the_listeners_of_its_published_campaigns(): void
    {
        $published = $this->campaign();
        $draft = $this->campaign(['is_published' => false]);
        $before = UserModel::factory()->create()->id;
        $after = UserModel::factory()->create()->id;
        $gameMaster = $this->gameMasters()->addToCampaign($published->id, ['user_id' => $before, 'role' => 'main']);
        $draft->gameMasters()->attach($gameMaster->id, ['sort_order' => 0]);
        Event::fake([PublicationRosterChanged::class]);

        GameMasterModel::query()->findOrFail($gameMaster->id)->forceFill(['user_id' => $after])->save();

        Event::assertDispatchedTimes(PublicationRosterChanged::class, 1);
        Event::assertDispatched(PublicationRosterChanged::class, static fn (PublicationRosterChanged $change): bool => $change->id === $published->id
            && $change->userIds === [$before, $after]);
    }

    public function test_deleting_a_director_tells_the_listeners_of_its_campaigns(): void
    {
        $campaign = $this->campaign();
        $user = $this->director($campaign, 'main');
        $gameMaster = GameMasterModel::query()->where('user_id', $user)->firstOrFail();
        Event::fake([PublicationRosterChanged::class]);

        // The direction rows go by cascade, without pivot events
        $gameMaster->delete();

        Event::assertDispatched(PublicationRosterChanged::class, static fn (PublicationRosterChanged $change): bool => $change->id === $campaign->id && $change->userIds === [$user]);
    }

    private function source(): CampaignPublicationSource
    {
        return $this->app->make(CampaignPublicationSource::class);
    }

    private function gameMasters(): GameMasterServiceInterface
    {
        return $this->app->make(GameMasterServiceInterface::class);
    }

    private function player(CampaignModel $campaign, ?string $userId = null): string
    {
        $userId ??= UserModel::factory()->create()->id;
        $this->app->make(CampaignRosterRepositoryInterface::class)->add(new CampaignId($campaign->id), $userId, new DateTimeImmutable);

        return $userId;
    }

    private function director(CampaignModel $campaign, string $role, ?string $userId = null): string
    {
        $userId ??= UserModel::factory()->create()->id;
        $this->gameMasters()->addToCampaign($campaign->id, ['user_id' => $userId, 'role' => $role]);

        return $userId;
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
            'status' => 'recruiting',
            'is_published' => true,
        ], $attributes));
    }
}
