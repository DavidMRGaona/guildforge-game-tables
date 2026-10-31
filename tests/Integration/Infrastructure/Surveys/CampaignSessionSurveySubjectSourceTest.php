<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Integration\Infrastructure\Surveys;

use App\Application\Surveys\DTOs\SurveyGuestDTO;
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
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\ParticipantModel;
use Modules\GameTables\Infrastructure\Surveys\CampaignSessionSurveySubjectSource;
use Tests\Support\Modules\ModuleTestCase;

/**
 * A campaign session as something a survey can be about, apart from tables on their own: its
 * snapshot, who is asked to answer and who manages its surveys.
 */
final class CampaignSessionSurveySubjectSourceTest extends ModuleTestCase
{
    protected ?string $moduleName = 'game-tables';

    protected bool $autoEnableModule = true;

    private CampaignSessionSurveySubjectSource $source;

    private GameSystemModel $system;

    private CampaignModel $campaign;

    protected function setUp(): void
    {
        parent::setUp();

        $this->useUtcStorage();
        $this->source = $this->app->make(CampaignSessionSurveySubjectSource::class);
        $this->system = GameSystemModel::create(['id' => (string) Str::uuid(), 'name' => 'Sistema de ejemplo', 'slug' => Str::random(10), 'is_active' => true]);
        $this->campaign = CampaignModel::create([
            'id' => (string) Str::uuid(),
            'game_system_id' => $this->system->id,
            'created_by' => UserModel::factory()->create()->id,
            'title' => 'Campaña de ejemplo',
            'slug' => Str::random(10),
            'status' => 'active',
        ]);
    }

    public function test_the_registry_offers_campaign_sessions(): void
    {
        $source = $this->app->make(SurveySubjectSourceRegistryInterface::class)->source('campaign-session');

        $this->assertInstanceOf(CampaignSessionSurveySubjectSource::class, $source);
        $this->assertSame('campaign-session', $source->sourceType());
        $this->assertSame(__('game-tables::messages.surveys.campaign_session_label'), $source->sourceLabel());
    }

    public function test_find_returns_the_session_snapshot_with_its_campaign(): void
    {
        $director = UserModel::factory()->create();
        $session = $this->campaignSession(['starts_at' => '2030-01-05 16:00:00', 'duration_minutes' => 180]);
        $this->direct($session, $director);

        $subject = $this->source->find($session->id);

        $this->assertInstanceOf(SurveySubjectDTO::class, $subject);
        $this->assertSame('campaign-session', $subject->sourceType);
        $this->assertSame($session->id, $subject->subjectId);
        $this->assertSame('Sesión de ejemplo', $subject->title);
        $this->assertEquals(new DateTimeImmutable('2030-01-05T16:00:00+00:00'), $subject->startsAt);
        $this->assertEquals(new DateTimeImmutable('2030-01-05T19:00:00+00:00'), $subject->endsAt);
        $this->assertSame([$director->id], $subject->organizerIds);
        $this->assertSame(route('gametables.show', $session->slug), $subject->url);
        $this->assertSame('Sistema de ejemplo', $subject->attribute('game_system')?->value);
        $this->assertSame('Campaña de ejemplo', $subject->attribute('campaign')?->value);
        $this->assertSame(__('game-tables::messages.surveys.attributes.campaign'), $subject->attribute('campaign')->label);
        $this->assertSame(__('game-tables::messages.enums.table_format.online'), $subject->attribute('table_format')?->value);
        $this->assertSame(__('game-tables::messages.enums.table_type.campaign_session'), $subject->attribute('table_type')?->value);
        $this->assertSame(SurveySubjectStatus::Active, $subject->status);
    }

    public function test_the_status_follows_the_session(): void
    {
        $this->assertSame(SurveySubjectStatus::Active, $this->source->find($this->campaignSession(['status' => 'in_progress'])->id)?->status);
        $this->assertSame(SurveySubjectStatus::Ended, $this->source->find($this->campaignSession(['status' => 'completed'])->id)?->status);
        $this->assertSame(SurveySubjectStatus::Cancelled, $this->source->find($this->campaignSession(['status' => 'cancelled'])->id)?->status);
    }

    public function test_find_ignores_tables_on_their_own_and_unknown_ids(): void
    {
        $table = $this->campaignSession(['campaign_id' => null]);

        $this->assertNull($this->source->find($table->id));
        $this->assertNull($this->source->find((string) Str::uuid()));
        $this->assertNull($this->source->find('not-a-session'));
    }

    public function test_audience_lists_confirmed_players_and_reachable_guests(): void
    {
        $session = $this->campaignSession();
        $player = UserModel::factory()->create();
        $this->participant($session, $player, 'player', 'confirmed');
        $this->participant($session, UserModel::factory()->create(), 'player', 'waiting_list');
        $this->participant($session, UserModel::factory()->create(), 'spectator', 'confirmed');
        $this->participant($session, UserModel::factory()->create(['anonymized_at' => now()]), 'player', 'confirmed');
        $guest = $this->guest($session, 'invitada@example.com', confirmed: true);
        $this->guest($session, 'sin-confirmar@example.com', confirmed: false);

        $audience = $this->source->audience($session->id);

        $this->assertSame([$player->id], $audience->userIds);
        $this->assertEquals([new SurveyGuestDTO($guest->id, 'Jane Doe', 'invitada@example.com')], $audience->guests);
    }

    public function test_audience_is_empty_for_tables_on_their_own_and_unknown_ids(): void
    {
        $table = $this->campaignSession(['campaign_id' => null]);
        $this->participant($table, UserModel::factory()->create(), 'player', 'confirmed');

        $this->assertSame(0, $this->source->audience($table->id)->count());
        $this->assertSame(0, $this->source->audience((string) Str::uuid())->count());
        $this->assertSame(0, $this->source->audience('not-a-session')->count());
    }

    public function test_only_live_session_directors_can_manage(): void
    {
        $session = $this->campaignSession();
        $inherited = UserModel::factory()->create();
        $excluded = UserModel::factory()->create();
        $local = UserModel::factory()->create();
        $this->directCampaign($inherited);
        $excludedGm = $this->directCampaign($excluded);
        $session->gameMasters()->attach($excludedGm->id, ['source' => 'inherited', 'excluded' => true, 'sort_order' => 2]);
        $localGm = $this->direct($session, $local);

        $this->assertTrue($this->source->canManage($inherited->id, $session->id));
        $this->assertTrue($this->source->canManage($local->id, $session->id));
        $this->assertFalse($this->source->canManage($excluded->id, $session->id));
        $this->assertFalse($this->source->canManage($local->id, (string) Str::uuid()));
        $this->assertFalse($this->source->canManage($local->id, 'not-a-session'));

        $session->gameMasters()->detach($localGm->id);

        $this->assertFalse($this->source->canManage($local->id, $session->id));
    }

    public function test_directors_of_a_table_on_its_own_do_not_manage_it_here(): void
    {
        $table = $this->campaignSession(['campaign_id' => null]);
        $director = UserModel::factory()->create();
        $this->direct($table, $director);

        $this->assertFalse($this->source->canManage($director->id, $table->id));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function campaignSession(array $attributes = []): GameTableModel
    {
        return GameTableModel::create(array_merge([
            'id' => (string) Str::uuid(),
            'game_system_id' => $this->system->id,
            'campaign_id' => $this->campaign->id,
            'created_by' => UserModel::factory()->create()->id,
            'title' => 'Sesión de ejemplo',
            'slug' => 'sesion-'.Str::lower(Str::random(8)),
            'starts_at' => now()->addWeek(),
            'duration_minutes' => 240,
            'table_type' => 'campaign_session',
            'table_format' => 'online',
            'status' => 'scheduled',
            'min_players' => 1,
            'max_players' => 5,
            'max_spectators' => 2,
            'language' => 'es',
            'registration_type' => 'everyone',
            'is_published' => true,
        ], $attributes));
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

    private function direct(GameTableModel $session, UserModel $user): GameMasterModel
    {
        $gm = $this->gameMaster($user);
        $session->gameMasters()->attach($gm->id, ['source' => 'local', 'excluded' => false, 'sort_order' => 1]);

        return $gm;
    }

    private function directCampaign(UserModel $user): GameMasterModel
    {
        $gm = $this->gameMaster($user);
        DB::table('gametables_campaign_gm')->insert([
            'campaign_id' => $this->campaign->id,
            'game_master_id' => $gm->id,
            'sort_order' => 1,
        ]);

        return $gm;
    }

    private function participant(GameTableModel $session, UserModel $user, string $role, string $status): ParticipantModel
    {
        return ParticipantModel::create([
            'id' => (string) Str::uuid(),
            'game_table_id' => $session->id,
            'user_id' => $user->id,
            'role' => $role,
            'status' => $status,
            'confirmed_at' => $status === 'confirmed' ? now() : null,
        ]);
    }

    private function guest(GameTableModel $session, string $email, bool $confirmed): ParticipantModel
    {
        return ParticipantModel::create([
            'id' => (string) Str::uuid(),
            'game_table_id' => $session->id,
            'user_id' => null,
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'email' => $email,
            'role' => 'player',
            'status' => 'confirmed',
            'confirmed_at' => now(),
            'email_confirmed_at' => $confirmed ? now() : null,
        ]);
    }
}
