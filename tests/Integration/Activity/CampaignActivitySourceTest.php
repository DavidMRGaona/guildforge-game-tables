<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Integration\Activity;

use App\Application\Activity\Contracts\ActivitySourceInterface;
use App\Application\Activity\DTOs\ActivityFactDTO;
use App\Domain\Activity\ActivityKind;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Modules\GameTables\Domain\Enums\FrontendCreationStatus;
use Modules\GameTables\Domain\Enums\GameMasterRole;
use Modules\GameTables\Infrastructure\Activity\CampaignActivitySource;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\CampaignModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameMasterModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameSystemModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameTableModel;
use Tests\Support\Activity\ActivitySourceTestCase;

/**
 * Completed campaigns as activity (core#774): its roster played it, its direction ran it, somebody
 * created it; only published ones, the same answer whoever asks.
 */
final class CampaignActivitySourceTest extends ActivitySourceTestCase
{
    protected ?string $moduleName = 'game-tables';

    private GameSystemModel $system;

    protected function setUp(): void
    {
        parent::setUp();

        $this->useUtcStorage();
        $this->system = GameSystemModel::create(['id' => (string) Str::uuid(), 'name' => 'Sistema de ejemplo', 'slug' => Str::random(10), 'is_active' => true]);
    }

    protected function activitySource(): ActivitySourceInterface
    {
        return $this->app->make(CampaignActivitySource::class);
    }

    protected function createCountedActivitySubject(): string
    {
        $campaign = $this->campaign();
        $this->join($campaign, UserModel::factory()->create());

        return $campaign->id;
    }

    public function test_it_is_the_campaign_source(): void
    {
        $source = $this->activitySource();

        $this->assertSame('game-tables.campaign', $source->type());
        $this->assertSame(ActivityKind::Campaign, $source->kind());
        $this->assertSame(__('game-tables::messages.activity.campaign_label'), $source->label());
    }

    public function test_roster_direction_and_creator_of_a_completed_campaign(): void
    {
        $creator = UserModel::factory()->create();
        $director = UserModel::factory()->create();
        $player = UserModel::factory()->create();
        $campaign = $this->campaign(['created_by' => $creator->id]);
        $this->direct($campaign, $director);
        $this->join($campaign, $player);
        $this->sessionOf($campaign, '2026-09-05 16:00:00', 'completed');
        $this->sessionOf($campaign, '2026-09-19 16:00:00', 'completed');
        $this->sessionOf($campaign, '2026-10-03 16:00:00', 'cancelled');

        $facts = $this->activitySource()->facts($campaign->id);

        $this->assertEqualsCanonicalizing([
            [$player->id, 'player'],
            [$director->id, 'director'],
            [$creator->id, 'creator'],
        ], $this->pairs($facts));

        foreach ($facts as $fact) {
            // When its last completed session took place
            $this->assertEquals(new DateTimeImmutable('2026-09-19T16:00:00+00:00'), $fact->occurredAt);
            $this->assertSame($this->system->id, $fact->attributes['game_system']->id);
            $this->assertSame('Sistema de ejemplo', $fact->attributes['game_system']->label);
        }
    }

    public function test_without_completed_sessions_it_took_place_when_it_was_created(): void
    {
        $campaign = $this->campaign();
        $campaign->forceFill(['created_at' => '2026-02-01 10:00:00'])->saveQuietly();

        foreach ($this->activitySource()->facts($campaign->id) as $fact) {
            $this->assertEquals(new DateTimeImmutable('2026-02-01T10:00:00+00:00'), $fact->occurredAt);
        }
    }

    public function test_the_direction_is_what_the_module_shows_to_anybody(): void
    {
        $webCreator = UserModel::factory()->create();
        $director = UserModel::factory()->create();
        $hidden = UserModel::factory()->create();
        $campaign = $this->campaign([
            'created_by' => $webCreator->id,
            'frontend_creation_status' => FrontendCreationStatus::Approved,
        ]);
        $this->direct($campaign, $director);
        $this->direct($campaign, $hidden, namePublic: false);
        // Who also plays still counts as a player
        $this->join($campaign, $hidden);

        $pairs = $this->pairs($this->activitySource()->facts($campaign->id));

        $this->assertEqualsCanonicalizing([
            [$director->id, 'director'],
            [$hidden->id, 'player'],
            [$webCreator->id, 'creator'],
        ], $pairs);
    }

    public function test_deactivated_accounts_on_the_roster_are_reported(): void
    {
        $campaign = $this->campaign();
        $deactivated = UserModel::factory()->create();
        $this->join($campaign, $deactivated);
        $deactivated->delete();

        $this->assertContains([$deactivated->id, 'player'], $this->pairs($this->activitySource()->facts($campaign->id)));
    }

    public function test_campaigns_that_are_not_completed_give_nothing(): void
    {
        foreach (['recruiting', 'active', 'on_hold', 'cancelled'] as $status) {
            $campaign = $this->campaign(['status' => $status]);
            $this->join($campaign, UserModel::factory()->create());

            $this->assertSame([], $this->activitySource()->facts($campaign->id), "A {$status} campaign gives no facts.");
        }
    }

    public function test_unpublished_campaigns_give_nothing(): void
    {
        $campaign = $this->campaign(['is_published' => false]);
        $this->join($campaign, UserModel::factory()->create());

        $this->assertSame([], $this->activitySource()->facts($campaign->id));
        $this->assertNotContains($campaign->id, $this->historyIds());
    }

    public function test_the_answer_is_the_same_with_somebody_signed_in(): void
    {
        $hidden = $this->campaign(['is_published' => false]);
        $counted = $this->createCountedActivitySubject();
        $anonymous = $this->activitySource()->facts($counted);
        $history = $this->historyIds();

        $this->actingAs(UserModel::query()->findOrFail($hidden->created_by));

        $this->assertSame([], $this->activitySource()->facts($hidden->id));
        $this->assertEquals($anonymous, $this->activitySource()->facts($counted));
        $this->assertSame($history, $this->historyIds());
    }

    public function test_an_unreadable_cursor_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->activitySource()->history('not a cursor', 10);
    }

    public function test_describe_names_published_campaigns_with_their_page(): void
    {
        $id = $this->createCountedActivitySubject();
        $campaign = CampaignModel::query()->findOrFail($id);
        $hidden = $this->campaign(['is_published' => false]);

        $described = $this->activitySource()->describe([$id, $hidden->id], null);

        $this->assertSame([$id], array_keys($described));
        $this->assertSame('Campaña de ejemplo', $described[$id]->title);
        $this->assertSame(route('campaigns.show', $campaign->slug, false), $described[$id]->url);
    }

    /**
     * @param  list<ActivityFactDTO>  $facts
     * @return list<array{0: string, 1: string}>
     */
    private function pairs(array $facts): array
    {
        return array_map(static fn (ActivityFactDTO $fact): array => [$fact->userId, $fact->role->value], $facts);
    }

    /**
     * @return list<string>
     */
    private function historyIds(): array
    {
        $ids = [];
        $cursor = null;

        do {
            $page = $this->activitySource()->history($cursor, 100);
            foreach ($page->subjects as $subject) {
                $ids[] = $subject->subjectId;
            }
            $cursor = $page->nextCursor;
        } while ($cursor !== null);

        return $ids;
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

    private function sessionOf(CampaignModel $campaign, string $startsAt, string $status): GameTableModel
    {
        return GameTableModel::create([
            'id' => (string) Str::uuid(),
            'game_system_id' => $this->system->id,
            'campaign_id' => $campaign->id,
            'created_by' => $campaign->created_by,
            'title' => 'Sesión de ejemplo',
            'slug' => 'sesion-'.Str::lower(Str::random(8)),
            'starts_at' => $startsAt,
            'duration_minutes' => 240,
            'table_type' => 'campaign_session',
            'table_format' => 'online',
            'status' => $status,
            'min_players' => 1,
            'max_players' => 5,
            'language' => 'es',
            'registration_type' => 'everyone',
            'is_published' => true,
        ]);
    }

    private function direct(CampaignModel $campaign, UserModel $user, bool $namePublic = true): GameMasterModel
    {
        $gm = GameMasterModel::create([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'first_name' => 'John',
            'last_name' => 'Doe',
            'role' => GameMasterRole::Main,
            'notify_by_email' => true,
            'is_name_public' => $namePublic,
        ]);
        $campaign->gameMasters()->attach($gm->id, ['sort_order' => 1]);

        return $gm;
    }

    private function join(CampaignModel $campaign, UserModel $user): void
    {
        DB::table('gametables_campaign_players')->insert([
            'campaign_id' => $campaign->id,
            'user_id' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
