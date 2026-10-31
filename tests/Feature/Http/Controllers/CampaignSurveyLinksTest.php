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
use Tests\Support\Modules\ModuleTestCase;

/**
 * The campaign's page links to its surveys through the core's contract, whatever module answers
 * it: nothing changes without one.
 */
final class CampaignSurveyLinksTest extends ModuleTestCase
{
    protected ?string $moduleName = 'game-tables';

    protected bool $autoEnableModule = true;

    private CampaignModel $campaign;

    /** @var ArrayObject<int, array{string, string, ?string}> what the page asked the contract */
    private ArrayObject $asked;

    protected function setUp(): void
    {
        parent::setUp();

        $this->asked = new ArrayObject;
        $system = GameSystemModel::create(['id' => (string) Str::uuid(), 'name' => 'Sistema de ejemplo', 'slug' => Str::random(10), 'is_active' => true]);
        $this->campaign = CampaignModel::create([
            'id' => (string) Str::uuid(),
            'game_system_id' => $system->id,
            'created_by' => UserModel::factory()->create()->id,
            'title' => 'Campaña de ejemplo',
            'slug' => 'campana-'.Str::lower(Str::random(8)),
            'status' => 'completed',
            'is_published' => true,
        ]);
    }

    public function test_without_a_surveys_module_the_page_has_no_survey_links(): void
    {
        $this->actingAs(UserModel::factory()->create())->get(route('campaigns.show', $this->campaign->slug))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('surveyLinks', null));
    }

    public function test_a_director_gets_the_campaign_surveys_page(): void
    {
        $user = UserModel::factory()->create();
        $this->fakeLinks(new SurveySubjectLinksDTO(manageUrl: 'https://example.com/encuestas/sujeto/campaign/'.$this->campaign->id));

        $this->actingAs($user)->get(route('campaigns.show', $this->campaign->slug))
            ->assertInertia(fn (Assert $page) => $page
                ->where('surveyLinks.manageUrl', 'https://example.com/encuestas/sujeto/campaign/'.$this->campaign->id)
                ->where('surveyLinks.surveys', []));

        $this->assertSame([['campaign', $this->campaign->id, $user->id]], $this->asked->getArrayCopy());
    }

    public function test_a_roster_member_gets_their_surveys(): void
    {
        $this->fakeLinks(new SurveySubjectLinksDTO(surveys: [
            new SurveyLinkDTO('Valoración de la campaña', 'https://example.com/encuestas/1', SurveyLinkAction::ViewResults),
        ]));

        $this->actingAs(UserModel::factory()->create())->get(route('campaigns.show', $this->campaign->slug))
            ->assertInertia(fn (Assert $page) => $page
                ->where('surveyLinks.manageUrl', null)
                ->where('surveyLinks.surveys.0.title', 'Valoración de la campaña')
                ->where('surveyLinks.surveys.0.url', 'https://example.com/encuestas/1')
                ->where('surveyLinks.surveys.0.action', 'view_results'));
    }

    public function test_nothing_to_link_gives_no_links(): void
    {
        $this->fakeLinks(SurveySubjectLinksDTO::none());

        $this->actingAs(UserModel::factory()->create())->get(route('campaigns.show', $this->campaign->slug))
            ->assertInertia(fn (Assert $page) => $page->where('surveyLinks', null));
    }

    public function test_a_visitor_is_not_asked_about(): void
    {
        $this->fakeLinks(new SurveySubjectLinksDTO(manageUrl: 'https://example.com/x'));

        $this->get(route('campaigns.show', $this->campaign->slug))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('surveyLinks', null));

        $this->assertSame([], $this->asked->getArrayCopy());
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
}
