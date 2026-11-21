<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Integration\Activity;

use App\Application\Activity\Contracts\ActivitySourceInterface;
use App\Application\Activity\DTOs\ActivityFactDTO;
use App\Application\Activity\Services\ActivityReaderInterface;
use App\Domain\Activity\ActivityKind;
use App\Domain\Activity\ActivityRole;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use DateTimeImmutable;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Modules\GameTables\Domain\Enums\GameMasterRole;
use Modules\GameTables\Infrastructure\Activity\SessionActivitySource;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\CampaignModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameMasterModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameSystemModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameTableModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\ParticipantModel;
use Tests\Support\Activity\ActivitySourceTestCase;

/**
 * Completed tables and campaign sessions as activity (core#774): who played, ran and created them,
 * only for published ones, the same answer whoever asks.
 */
final class SessionActivitySourceTest extends ActivitySourceTestCase
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
        return $this->app->make(SessionActivitySource::class);
    }

    protected function createCountedActivitySubject(): string
    {
        $table = $this->table();
        $this->player($table, UserModel::factory()->create());

        return $table->id;
    }

    public function test_it_is_the_session_source(): void
    {
        $source = $this->activitySource();

        $this->assertSame('game-tables.session', $source->type());
        $this->assertSame(ActivityKind::GameSession, $source->kind());
        $this->assertSame(__('game-tables::messages.activity.session_label'), $source->label());
    }

    public function test_players_directors_and_creator_of_a_completed_table(): void
    {
        $creator = UserModel::factory()->create();
        $director = UserModel::factory()->create();
        $coDirector = UserModel::factory()->create();
        $player = UserModel::factory()->create();
        $table = $this->table(['created_by' => $creator->id, 'starts_at' => '2026-10-03 16:00:00']);
        $this->direct($table, $director, GameMasterRole::Main);
        $this->direct($table, $coDirector, GameMasterRole::CoGm);
        $this->player($table, $player);

        $facts = $this->activitySource()->facts($table->id);

        $this->assertEqualsCanonicalizing([
            [$player->id, 'player'],
            [$director->id, 'director'],
            [$coDirector->id, 'director'],
            [$creator->id, 'creator'],
        ], $this->pairs($facts));

        foreach ($facts as $fact) {
            $this->assertEquals(new DateTimeImmutable('2026-10-03T16:00:00+00:00'), $fact->occurredAt);
            $this->assertSame($this->system->id, $fact->attributes['game_system']->id);
            $this->assertSame('Sistema de ejemplo', $fact->attributes['game_system']->label);
            $this->assertNull($fact->placement);
        }
    }

    public function test_one_person_may_hold_several_roles(): void
    {
        $creator = UserModel::factory()->create();
        $table = $this->table(['created_by' => $creator->id]);
        $this->direct($table, $creator, GameMasterRole::Main);
        // The same account twice in the direction counts once
        $this->direct($table, $creator, GameMasterRole::CoGm);

        $this->assertEqualsCanonicalizing(
            [[$creator->id, 'director'], [$creator->id, 'creator']],
            $this->pairs($this->activitySource()->facts($table->id)),
        );
    }

    public function test_only_confirmed_players_with_an_account_count(): void
    {
        $table = $this->table();
        $confirmed = UserModel::factory()->create();
        $this->player($table, $confirmed);
        $this->participant($table, UserModel::factory()->create(), 'spectator', 'confirmed');
        $this->participant($table, UserModel::factory()->create(), 'player', 'pending');
        $this->participant($table, UserModel::factory()->create(), 'player', 'cancelled');
        $this->participant($table, UserModel::factory()->create(), 'player', 'no_show');
        $this->participant($table, UserModel::factory()->create(), 'player', 'waiting_list');
        ParticipantModel::create([
            'id' => (string) Str::uuid(),
            'game_table_id' => $table->id,
            'user_id' => null,
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'email' => 'jane@example.test',
            'role' => 'player',
            'status' => 'confirmed',
            'confirmed_at' => now(),
            'email_confirmed_at' => now(),
        ]);

        $players = array_values(array_filter($this->activitySource()->facts($table->id), static fn (ActivityFactDTO $fact): bool => $fact->role === ActivityRole::Player));

        $this->assertSame([$confirmed->id], array_map(static fn (ActivityFactDTO $fact): string => $fact->userId, $players));
    }

    public function test_game_masters_without_an_account_are_left_out(): void
    {
        $table = $this->table();
        $gm = GameMasterModel::create([
            'id' => (string) Str::uuid(),
            'user_id' => null,
            'first_name' => 'Invited',
            'last_name' => 'Master',
            'email' => 'master@example.test',
            'role' => GameMasterRole::Main,
            'notify_by_email' => false,
            'is_name_public' => true,
        ]);
        $table->gameMasters()->attach($gm->id, ['source' => 'local', 'excluded' => false, 'sort_order' => 1]);

        $this->assertSame([], array_values(array_filter(
            $this->activitySource()->facts($table->id),
            static fn (ActivityFactDTO $fact): bool => $fact->role === ActivityRole::Director,
        )));
    }

    public function test_a_campaign_session_counts_the_campaign_direction_it_inherits(): void
    {
        $campaignDirector = UserModel::factory()->create();
        $excluded = UserModel::factory()->create();
        $campaign = $this->campaign();
        $this->directCampaign($campaign, $campaignDirector);
        $excludedGm = $this->directCampaign($campaign, $excluded);
        $session = $this->table(['campaign_id' => $campaign->id, 'table_type' => 'campaign_session']);
        $session->gameMasters()->attach($excludedGm->id, ['source' => 'inherited', 'excluded' => true, 'sort_order' => 0]);

        $directors = array_values(array_filter($this->activitySource()->facts($session->id), static fn (ActivityFactDTO $fact): bool => $fact->role === ActivityRole::Director));

        $this->assertSame([$campaignDirector->id], array_map(static fn (ActivityFactDTO $fact): string => $fact->userId, $directors));
    }

    public function test_tables_that_are_not_completed_give_nothing(): void
    {
        foreach (['draft', 'scheduled', 'full', 'in_progress', 'cancelled'] as $status) {
            $table = $this->table(['status' => $status]);
            $this->player($table, UserModel::factory()->create());

            $this->assertSame([], $this->activitySource()->facts($table->id), "A {$status} table gives no facts.");
        }
    }

    public function test_unpublished_tables_give_nothing(): void
    {
        $table = $this->table(['is_published' => false]);
        $this->player($table, UserModel::factory()->create());

        $this->assertSame([], $this->activitySource()->facts($table->id));
        $this->assertNotContains($table->id, $this->historyIds());
    }

    public function test_sessions_of_an_unpublished_campaign_give_nothing(): void
    {
        $session = $this->table(['campaign_id' => $this->campaign(['is_published' => false])->id, 'table_type' => 'campaign_session']);
        $this->player($session, UserModel::factory()->create());

        $this->assertSame([], $this->activitySource()->facts($session->id));
        $this->assertNotContains($session->id, $this->historyIds());
    }

    public function test_the_answer_is_the_same_with_somebody_signed_in(): void
    {
        $table = $this->table(['is_published' => false]);
        $player = UserModel::factory()->create();
        $this->player($table, $player);
        $counted = $this->createCountedActivitySubject();
        $anonymous = $this->activitySource()->facts($counted);
        $history = $this->historyIds();

        $this->actingAs(UserModel::query()->findOrFail($table->created_by));

        $this->assertSame([], $this->activitySource()->facts($table->id));
        $this->assertEquals($anonymous, $this->activitySource()->facts($counted));
        $this->assertSame($history, $this->historyIds());
    }

    public function test_ids_are_read_in_any_case(): void
    {
        $id = $this->createCountedActivitySubject();

        $this->assertEquals($this->activitySource()->facts($id), $this->activitySource()->facts(strtoupper($id)));
        $this->assertSame([], $this->activitySource()->facts('not-a-table'));
    }

    public function test_an_unreadable_cursor_throws(): void
    {
        foreach (['', 'not a cursor', base64_encode('something else'), base64_encode('v1|not-a-uuid')] as $cursor) {
            try {
                $this->activitySource()->history($cursor, 10);
                $this->fail("Cursor '{$cursor}' was read.");
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_history_is_ordered_and_pages_with_an_opaque_cursor(): void
    {
        $ids = [$this->createCountedActivitySubject(), $this->createCountedActivitySubject(), $this->createCountedActivitySubject()];
        sort($ids);

        $first = $this->activitySource()->history(null, 2);
        $second = $this->activitySource()->history($first->nextCursor, 2);

        $this->assertSame(array_slice($ids, 0, 2), array_map(static fn ($subject): string => $subject->subjectId, $first->subjects));
        $this->assertSame([$ids[2]], array_map(static fn ($subject): string => $subject->subjectId, $second->subjects));
        $this->assertNotNull($first->nextCursor);
        $this->assertStringNotContainsString($ids[1], $first->nextCursor);
        $this->assertNull($second->nextCursor);
    }

    public function test_describe_names_published_tables_with_their_page(): void
    {
        $id = $this->createCountedActivitySubject();
        $table = GameTableModel::query()->findOrFail($id);
        $hidden = $this->table(['is_published' => false]);

        $described = $this->activitySource()->describe([$id, $hidden->id, 'not-a-table'], UserModel::factory()->create());

        $this->assertSame([$id], array_keys($described));
        $this->assertSame('Mesa de ejemplo', $described[$id]->title);
        $this->assertSame(route('gametables.show', $table->slug, false), $described[$id]->url);
    }

    public function test_the_core_reads_the_source(): void
    {
        $id = $this->createCountedActivitySubject();

        $result = $this->app->make(ActivityReaderInterface::class)->facts('game-tables.session', $id);

        $this->assertTrue($result->isRead());
        $this->assertCount(2, $result->facts);
    }

    public function test_game_system_values_list_every_system(): void
    {
        $inactive = GameSystemModel::create(['id' => (string) Str::uuid(), 'name' => 'Sistema retirado', 'slug' => Str::random(10), 'is_active' => false]);
        $source = $this->app->make(SessionActivitySource::class);

        $this->assertSame(['game_system'], array_map(static fn ($attribute): string => $attribute->key, $source->attributes()));
        $values = array_map(static fn ($value): string => $value->id, $source->attributeValues('game_system'));

        // The module seeds its own catalogue too
        $this->assertContains($this->system->id, $values);
        $this->assertContains($inactive->id, $values);
        $this->assertSame(GameSystemModel::query()->count(), count($values));
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
    private function table(array $attributes = []): GameTableModel
    {
        return GameTableModel::create(array_merge([
            'id' => (string) Str::uuid(),
            'game_system_id' => $this->system->id,
            'created_by' => UserModel::factory()->create()->id,
            'title' => 'Mesa de ejemplo',
            'slug' => 'mesa-'.Str::lower(Str::random(8)),
            'starts_at' => now()->subWeek(),
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
            'status' => 'active',
            'is_published' => true,
        ], $attributes));
    }

    private function direct(GameTableModel $table, UserModel $user, GameMasterRole $role): GameMasterModel
    {
        $gm = $this->gameMaster($user, $role);
        $table->gameMasters()->attach($gm->id, ['source' => 'local', 'excluded' => false, 'sort_order' => 1]);

        return $gm;
    }

    private function directCampaign(CampaignModel $campaign, UserModel $user): GameMasterModel
    {
        $gm = $this->gameMaster($user, GameMasterRole::Main);
        $campaign->gameMasters()->attach($gm->id, ['sort_order' => 1]);

        return $gm;
    }

    private function gameMaster(UserModel $user, GameMasterRole $role): GameMasterModel
    {
        return GameMasterModel::create([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'first_name' => 'John',
            'last_name' => 'Doe',
            'role' => $role,
            'notify_by_email' => true,
            'is_name_public' => true,
        ]);
    }

    private function player(GameTableModel $table, UserModel $user): ParticipantModel
    {
        return $this->participant($table, $user, 'player', 'confirmed');
    }

    private function participant(GameTableModel $table, UserModel $user, string $role, string $status): ParticipantModel
    {
        return ParticipantModel::create([
            'id' => (string) Str::uuid(),
            'game_table_id' => $table->id,
            'user_id' => $user->id,
            'role' => $role,
            'status' => $status,
            'confirmed_at' => $status === 'confirmed' ? now() : null,
        ]);
    }
}
