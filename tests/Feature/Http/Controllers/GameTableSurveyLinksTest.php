<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Feature\Http\Controllers;

use App\Application\Surveys\Contracts\SurveySubjectLinksInterface;
use App\Application\Surveys\DTOs\SurveyLinkDTO;
use App\Application\Surveys\DTOs\SurveySubjectLinksDTO;
use App\Application\Surveys\Enums\SurveyLinkAction;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use ArrayObject;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\CampaignModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameSystemModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameTableModel;
use Tests\Support\Modules\ModuleTestCase;

/**
 * The table's page links to its surveys through the core's contract, whatever module answers it:
 * nothing changes without one.
 */
final class GameTableSurveyLinksTest extends ModuleTestCase
{
    protected ?string $moduleName = 'game-tables';

    protected bool $autoEnableModule = true;

    private GameSystemModel $system;

    /** @var ArrayObject<int, array{string, string, ?string}> what the page asked the contract */
    private ArrayObject $asked;

    protected function setUp(): void
    {
        parent::setUp();

        $this->asked = new ArrayObject;
        $this->system = GameSystemModel::create(['id' => (string) Str::uuid(), 'name' => 'Sistema de ejemplo', 'slug' => Str::random(10), 'is_active' => true]);
    }

    public function test_without_a_surveys_module_the_page_has_no_survey_links(): void
    {
        $table = $this->table();

        $this->actingAs(UserModel::factory()->create())->get(route('gametables.show', $table->slug))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('surveyLinks', null));
    }

    public function test_the_page_shows_what_the_contract_links_for_the_person(): void
    {
        $table = $this->table();
        $user = UserModel::factory()->create();
        $this->fakeLinks(new SurveySubjectLinksDTO(
            manageUrl: 'https://example.com/encuestas/sujeto/game-table/'.$table->id,
            surveys: [new SurveyLinkDTO('Valoración de la partida', 'https://example.com/encuestas/1', SurveyLinkAction::Respond)],
        ));

        $this->actingAs($user)->get(route('gametables.show', $table->slug))
            ->assertInertia(fn (Assert $page) => $page
                ->where('surveyLinks.manageUrl', 'https://example.com/encuestas/sujeto/game-table/'.$table->id)
                ->where('surveyLinks.surveys.0.title', 'Valoración de la partida')
                ->where('surveyLinks.surveys.0.url', 'https://example.com/encuestas/1')
                ->where('surveyLinks.surveys.0.action', 'respond'));

        $this->assertSame([['game-table', $table->id, $user->id]], $this->asked->getArrayCopy());
    }

    public function test_links_show_whatever_the_state_of_the_table(): void
    {
        $table = $this->table(['status' => 'scheduled']);
        $this->fakeLinks(new SurveySubjectLinksDTO(manageUrl: 'https://example.com/encuestas/sujeto/game-table/'.$table->id));

        $this->actingAs(UserModel::factory()->create())->get(route('gametables.show', $table->slug))
            ->assertInertia(fn (Assert $page) => $page->where('surveyLinks.manageUrl', 'https://example.com/encuestas/sujeto/game-table/'.$table->id));
    }

    public function test_nothing_to_link_gives_no_links(): void
    {
        $table = $this->table();
        $this->fakeLinks(SurveySubjectLinksDTO::none());

        $this->actingAs(UserModel::factory()->create())->get(route('gametables.show', $table->slug))
            ->assertInertia(fn (Assert $page) => $page->where('surveyLinks', null));
    }

    public function test_a_visitor_is_not_asked_about(): void
    {
        $table = $this->table();
        $this->fakeLinks(new SurveySubjectLinksDTO(manageUrl: 'https://example.com/x'));

        $this->get(route('gametables.show', $table->slug))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('surveyLinks', null));

        $this->assertSame([], $this->asked->getArrayCopy());
    }

    public function test_a_campaign_session_is_asked_about_as_such(): void
    {
        $session = $this->table(['campaign_id' => CampaignModel::create([
            'id' => (string) Str::uuid(),
            'game_system_id' => $this->system->id,
            'created_by' => UserModel::factory()->create()->id,
            'title' => 'Campaña de ejemplo',
            'slug' => Str::random(10),
            'status' => 'recruiting',
        ])->id]);
        $user = UserModel::factory()->create();
        $this->fakeLinks(new SurveySubjectLinksDTO(manageUrl: 'https://example.com/encuestas/sujeto/campaign-session/'.$session->id));

        $this->actingAs($user)->get(route('gametables.show', $session->slug))
            ->assertInertia(fn (Assert $page) => $page->where('surveyLinks.manageUrl', 'https://example.com/encuestas/sujeto/campaign-session/'.$session->id));

        $this->assertSame([['campaign-session', $session->id, $user->id]], $this->asked->getArrayCopy());
    }

    private function fakeLinks(SurveySubjectLinksDTO $links): void
    {
        $this->app->instance(SurveySubjectLinksInterface::class, new readonly class($links, $this->asked) implements SurveySubjectLinksInterface
        {
            /**
             * @param  ArrayObject<int, array{string, string, ?string}>  $asked
             */
            public function __construct(private SurveySubjectLinksDTO $links, private ArrayObject $asked) {}

            public function forSubject(string $sourceType, string $subjectId, ?string $userId): SurveySubjectLinksDTO
            {
                $this->asked[] = [$sourceType, $subjectId, $userId];

                return $this->links;
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
            'starts_at' => now()->subDay(),
            'duration_minutes' => 240,
            'table_type' => 'one_shot',
            'table_format' => 'in_person',
            'status' => 'completed',
            'min_players' => 1,
            'max_players' => 5,
            'max_spectators' => 0,
            'language' => 'es',
            'registration_type' => 'everyone',
            'is_published' => true,
        ], $attributes));
    }
}
