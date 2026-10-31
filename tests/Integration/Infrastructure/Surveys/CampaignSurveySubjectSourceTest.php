<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Integration\Infrastructure\Surveys;

use App\Application\Surveys\DTOs\SurveySubjectDTO;
use App\Application\Surveys\Enums\SurveySubjectStatus;
use App\Application\Surveys\Services\SurveySubjectSourceRegistryInterface;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\GameTables\Domain\Enums\GameMasterRole;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\CampaignModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameMasterModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameSystemModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameTableModel;
use Modules\GameTables\Infrastructure\Surveys\CampaignSurveySubjectSource;
use Tests\Support\Modules\ModuleTestCase;

/**
 * A whole campaign as something a survey can be about: its snapshot, its roster as the audience
 * and its directors as the people who manage its surveys.
 */
final class CampaignSurveySubjectSourceTest extends ModuleTestCase
{
    protected ?string $moduleName = 'game-tables';

    protected bool $autoEnableModule = true;

    private CampaignSurveySubjectSource $source;

    private GameSystemModel $system;

    protected function setUp(): void
    {
        parent::setUp();

        $this->useUtcStorage();
        $this->source = $this->app->make(CampaignSurveySubjectSource::class);
        $this->system = GameSystemModel::create(['id' => (string) Str::uuid(), 'name' => 'Sistema de ejemplo', 'slug' => Str::random(10), 'is_active' => true]);
    }

    public function test_the_registry_offers_campaigns(): void
    {
        $source = $this->app->make(SurveySubjectSourceRegistryInterface::class)->source('campaign');

        $this->assertInstanceOf(CampaignSurveySubjectSource::class, $source);
        $this->assertSame('campaign', $source->sourceType());
        $this->assertSame(__('game-tables::messages.surveys.campaign_label'), $source->sourceLabel());
    }

    public function test_the_registry_still_offers_tables_on_their_own(): void
    {
        $registry = $this->app->make(SurveySubjectSourceRegistryInterface::class);

        $this->assertNotNull($registry->source('game-table'));
        $this->assertNotNull($registry->source('campaign-session'));
    }

    public function test_find_returns_the_campaign_snapshot_spanning_its_sessions(): void
    {
        $director = UserModel::factory()->create();
        $campaign = $this->campaign(['frequency' => 'weekly']);
        $this->direct($campaign, $director);
        $this->sessionOf($campaign, '2030-01-12 16:00:00', 240);
        $this->sessionOf($campaign, '2030-01-05 16:00:00', 180);
        $this->sessionOf($campaign, '2030-01-19 17:00:00', 120);
        $this->sessionOf($campaign, '2030-02-01 16:00:00', 240, 'cancelled');
        $this->sessionOf($campaign, '2030-03-01 16:00:00', 240, 'draft');

        $subject = $this->source->find($campaign->id);

        $this->assertInstanceOf(SurveySubjectDTO::class, $subject);
        $this->assertSame('campaign', $subject->sourceType);
        $this->assertSame($campaign->id, $subject->subjectId);
        $this->assertSame('Campaña de ejemplo', $subject->title);
        $this->assertEquals(new DateTimeImmutable('2030-01-05T16:00:00+00:00'), $subject->startsAt);
        $this->assertEquals(new DateTimeImmutable('2030-01-19T19:00:00+00:00'), $subject->endsAt);
        $this->assertSame([$director->id], $subject->organizerIds);
        $this->assertSame(route('campaigns.show', $campaign->slug), $subject->url);
        $this->assertSame('Sistema de ejemplo', $subject->attribute('game_system')?->value);
        $this->assertSame(__('game-tables::messages.enums.campaign_frequency.weekly'), $subject->attribute('frequency')?->value);
        $this->assertSame(__('game-tables::messages.surveys.attributes.frequency'), $subject->attribute('frequency')->label);
        $this->assertSame(SurveySubjectStatus::Active, $subject->status);
    }

    public function test_find_without_sessions_has_no_dates(): void
    {
        $subject = $this->source->find($this->campaign()->id);

        $this->assertInstanceOf(SurveySubjectDTO::class, $subject);
        $this->assertNull($subject->startsAt);
        $this->assertNull($subject->endsAt);
        $this->assertNull($subject->attribute('frequency')?->value);
    }

    public function test_the_status_follows_the_campaign(): void
    {
        foreach (['recruiting', 'active', 'on_hold'] as $status) {
            $this->assertSame(SurveySubjectStatus::Active, $this->source->find($this->campaign(['status' => $status])->id)?->status);
        }

        $this->assertSame(SurveySubjectStatus::Ended, $this->source->find($this->campaign(['status' => 'completed'])->id)?->status);
        $this->assertSame(SurveySubjectStatus::Cancelled, $this->source->find($this->campaign(['status' => 'cancelled'])->id)?->status);
    }

    public function test_unknown_ids_give_nothing(): void
    {
        $user = UserModel::factory()->create();

        $this->assertNull($this->source->find((string) Str::uuid()));
        $this->assertNull($this->source->find('not-a-campaign'));
        $this->assertSame(0, $this->source->audience((string) Str::uuid())->count());
        $this->assertSame(0, $this->source->audience('not-a-campaign')->count());
        $this->assertFalse($this->source->canManage($user->id, (string) Str::uuid()));
        $this->assertFalse($this->source->canManage($user->id, 'not-a-campaign'));
    }

    public function test_audience_is_the_roster_without_anonymized_accounts(): void
    {
        $campaign = $this->campaign();
        $first = UserModel::factory()->create();
        $second = UserModel::factory()->create();
        $gone = UserModel::factory()->create(['anonymized_at' => now()]);
        $this->join($campaign, $first, '2030-01-01 10:00:00');
        $this->join($campaign, $second, '2030-01-02 10:00:00');
        $this->join($campaign, $gone, '2030-01-03 10:00:00');
        $this->join($this->campaign(), UserModel::factory()->create(), '2030-01-01 10:00:00');

        $audience = $this->source->audience($campaign->id);

        $this->assertSame([$first->id, $second->id], $audience->userIds);
        $this->assertSame([], $audience->guests);
    }

    public function test_audience_leaves_out_directors_outside_the_roster(): void
    {
        $campaign = $this->campaign();
        $outside = UserModel::factory()->create();
        $playing = UserModel::factory()->create();
        $this->direct($campaign, $outside);
        $this->direct($campaign, $playing);
        $this->join($campaign, $playing, '2030-01-01 10:00:00');

        $this->assertSame([$playing->id], $this->source->audience($campaign->id)->userIds);
    }

    public function test_only_live_campaign_directors_can_manage(): void
    {
        $campaign = $this->campaign();
        $director = UserModel::factory()->create();
        $member = UserModel::factory()->create();
        $gm = $this->direct($campaign, $director);
        $this->join($campaign, $member, '2030-01-01 10:00:00');

        $this->assertTrue($this->source->canManage($director->id, $campaign->id));
        $this->assertFalse($this->source->canManage($member->id, $campaign->id));

        $campaign->gameMasters()->detach($gm->id);

        $this->assertFalse($this->source->canManage($director->id, $campaign->id));
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
        ], $attributes));
    }

    private function sessionOf(CampaignModel $campaign, string $startsAt, int $minutes, string $status = 'scheduled'): GameTableModel
    {
        return GameTableModel::create([
            'id' => (string) Str::uuid(),
            'game_system_id' => $this->system->id,
            'campaign_id' => $campaign->id,
            'created_by' => UserModel::factory()->create()->id,
            'title' => 'Sesión de ejemplo',
            'slug' => 'sesion-'.Str::lower(Str::random(8)),
            'starts_at' => $startsAt,
            'duration_minutes' => $minutes,
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

    private function direct(CampaignModel $campaign, UserModel $user): GameMasterModel
    {
        $gm = GameMasterModel::create([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'first_name' => 'John',
            'last_name' => 'Doe',
            'role' => GameMasterRole::Main,
            'notify_by_email' => true,
            'is_name_public' => true,
        ]);
        $campaign->gameMasters()->attach($gm->id, ['sort_order' => 1]);

        return $gm;
    }

    private function join(CampaignModel $campaign, UserModel $user, string $at): void
    {
        DB::table('gametables_campaign_players')->insert([
            'campaign_id' => $campaign->id,
            'user_id' => $user->id,
            'created_at' => $at,
            'updated_at' => $at,
        ]);
    }
}
