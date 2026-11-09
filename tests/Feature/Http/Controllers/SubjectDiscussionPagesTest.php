<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Feature\Http\Controllers;

use App\Application\Discussions\Contracts\SubjectDiscussionInterface;
use App\Application\Discussions\DTOs\DiscussionActivityDTO;
use App\Application\Discussions\DTOs\SubjectDiscussionDTO;
use App\Application\Discussions\DTOs\TopicActivityDTO;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use ArrayObject;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\CampaignModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameSystemModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameTableModel;
use RuntimeException;
use Tests\Support\Modules\ModuleTestCase;

/**
 * Table and campaign pages show the activity of their forum discussion through the core's
 * contract: whatever a forum module answers for the person who looks, nothing without one, and
 * the same page without the block when it fails. Campaign sessions have no discussion of their own.
 */
final class SubjectDiscussionPagesTest extends ModuleTestCase
{
    protected ?string $moduleName = 'game-tables';

    protected bool $autoEnableModule = true;

    private GameSystemModel $system;

    /** @var ArrayObject<int, array{string, string, ?string}> */
    private ArrayObject $asked;

    protected function setUp(): void
    {
        parent::setUp();

        $this->asked = new ArrayObject;
        $this->system = GameSystemModel::create(['id' => (string) Str::uuid(), 'name' => 'Sistema de ejemplo', 'slug' => Str::random(10), 'is_active' => true]);
    }

    public function test_without_a_forum_module_the_pages_have_no_discussion(): void
    {
        $table = $this->table();
        $campaign = $this->campaign();

        $this->get(route('gametables.show', $table->slug))->assertInertia(fn (Assert $page) => $page->where('discussion', null));
        $this->get(route('campaigns.show', $campaign->slug))->assertInertia(fn (Assert $page) => $page->where('discussion', null));
    }

    public function test_a_table_page_shows_what_the_forum_module_answers_for_the_person(): void
    {
        $this->fakeDiscussions();
        $user = UserModel::factory()->create();
        $table = $this->table();

        $this->actingAs($user)->get(route('gametables.show', $table->slug))->assertInertia(fn (Assert $page) => $page
            ->where('discussion.discussion.commentCount', 3)
            ->where('discussion.discussion.url', 'https://forum.example.org/d/game-table/'.$table->id)
            ->where('discussion.topics.0.label', 'Sistema de ejemplo')
            ->where('discussion.topics.0.discussionCount', 2));

        $this->assertSame([['game-table', $table->id, (string) $user->id]], $this->asked->getArrayCopy());
    }

    public function test_a_campaign_page_asks_for_the_campaign_and_a_visitor_is_no_one(): void
    {
        $this->fakeDiscussions();
        $campaign = $this->campaign();

        $this->get(route('campaigns.show', $campaign->slug))->assertInertia(fn (Assert $page) => $page
            ->where('discussion.discussion.url', 'https://forum.example.org/d/campaign/'.$campaign->id));

        $this->assertSame([['campaign', $campaign->id, null]], $this->asked->getArrayCopy());
    }

    public function test_a_campaign_session_has_no_discussion_of_its_own(): void
    {
        $this->fakeDiscussions();
        $session = $this->table(['campaign_id' => $this->campaign()->id]);

        $this->get(route('gametables.show', $session->slug))->assertInertia(fn (Assert $page) => $page->where('discussion', null));
        $this->assertSame([], $this->asked->getArrayCopy());
    }

    public function test_a_failing_forum_module_leaves_the_pages_without_the_block(): void
    {
        $this->app->instance(SubjectDiscussionInterface::class, new class implements SubjectDiscussionInterface
        {
            public function forSubject(string $type, string $id, ?string $userId): ?SubjectDiscussionDTO
            {
                throw new RuntimeException('The forum module failed');
            }
        });

        $this->get(route('gametables.show', $this->table()->slug))->assertOk()->assertInertia(fn (Assert $page) => $page->where('discussion', null));
        $this->get(route('campaigns.show', $this->campaign()->slug))->assertOk()->assertInertia(fn (Assert $page) => $page->where('discussion', null));
    }

    private function fakeDiscussions(): void
    {
        $this->app->instance(SubjectDiscussionInterface::class, new readonly class($this->asked) implements SubjectDiscussionInterface
        {
            /**
             * @param  ArrayObject<int, array{string, string, ?string}>  $asked
             */
            public function __construct(private ArrayObject $asked) {}

            public function forSubject(string $type, string $id, ?string $userId): SubjectDiscussionDTO
            {
                $this->asked[] = [$type, $id, $userId];

                return new SubjectDiscussionDTO(
                    new DiscussionActivityDTO("https://forum.example.org/d/{$type}/{$id}", 'https://web.example.org/foro/entrar', 3),
                    [new TopicActivityDTO('Sistema de ejemplo', 'https://forum.example.org/t/sistema', 2)],
                );
            }
        });
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
            'starts_at' => now()->addWeek(),
            'duration_minutes' => 240,
            'table_type' => 'one_shot',
            'table_format' => 'in_person',
            'status' => 'scheduled',
            'min_players' => 1,
            'max_players' => 5,
            'max_spectators' => 0,
            'language' => 'es',
            'registration_type' => 'everyone',
            'is_published' => true,
        ], $attributes));
    }

    private function campaign(): CampaignModel
    {
        return CampaignModel::create([
            'id' => (string) Str::uuid(),
            'game_system_id' => $this->system->id,
            'created_by' => UserModel::factory()->create()->id,
            'title' => 'Campaña de ejemplo',
            'slug' => 'campana-'.Str::lower(Str::random(8)),
            'status' => 'recruiting',
            'is_published' => true,
        ]);
    }
}
