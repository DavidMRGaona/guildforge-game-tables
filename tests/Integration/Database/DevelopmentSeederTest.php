<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Integration\Database;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Modules\GameTables\Database\Seeders\DevelopmentSeeder;
use Modules\GameTables\Domain\Enums\FrontendCreationStatus;
use Modules\GameTables\Domain\Enums\GuestConfirmationMode;
use Modules\GameTables\Domain\Enums\JoinRequestStatus;
use Modules\GameTables\Domain\Enums\ParticipantCancellationReason;
use Modules\GameTables\Domain\Enums\ParticipantStatus;
use Modules\GameTables\Domain\Enums\RegistrationType;
use Modules\GameTables\Domain\Enums\TableStatus;
use Modules\GameTables\Domain\Events\RosterMemberEnrolledInSession;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\CampaignJoinRequestModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\CampaignModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameTableModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\ParticipantModel;
use Tests\Support\Modules\ModuleTestCase;

final class DevelopmentSeederTest extends ModuleTestCase
{
    protected ?string $moduleName = 'game-tables';

    protected bool $autoEnableModule = true;

    /**
     * Every table the seeder may write to.
     *
     * @var list<string>
     */
    private const array TABLES = [
        'users',
        'user_role',
        'gametables_publishers',
        'gametables_game_systems',
        'gametables_content_warnings',
        'gametables_campaigns',
        'gametables_campaign_players',
        'gametables_campaign_gm',
        'gametables_campaign_join_requests',
        'gametables_tables',
        'gametables_table_gm',
        'gametables_table_content_warnings',
        'gametables_game_masters',
        'gametables_participants',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        // database/ is outside the module's autoloaded src/
        foreach (glob(dirname(__DIR__, 3).'/database/seeders/*Seeder.php') ?: [] as $seeder) {
            require_once $seeder;
        }

        // Enabling the module above ran it without demo data; these tests ask for it
        DevelopmentSeeder::$seedInTests = true;
    }

    protected function tearDown(): void
    {
        DevelopmentSeeder::$seedInTests = false;

        parent::tearDown();
    }

    public function test_it_writes_nothing_in_production(): void
    {
        $this->assertSame(0, GameTableModel::query()->count());

        $this->app['env'] = 'production';
        $this->assertTrue($this->app->environment('production'));

        $before = $this->counts();

        $this->runSeeder();

        $this->assertSame($before, $this->counts());
        $this->assertSame(0, GameTableModel::query()->count());
        $this->assertSame(0, UserModel::query()->where('email', 'like', '%@example.local')->count());
    }

    public function test_module_tests_get_no_demo_data_unless_they_ask(): void
    {
        DevelopmentSeeder::$seedInTests = false;

        $this->runSeeder();

        $this->assertSame(0, GameTableModel::query()->count());
        $this->assertSame(0, CampaignModel::query()->count());
        $this->assertSame(0, UserModel::query()->count());
    }

    public function test_running_it_twice_creates_nothing_new(): void
    {
        $this->runSeeder();
        $first = $this->counts();

        $this->runSeeder();

        $this->assertSame($first, $this->counts());
        $this->assertGreaterThan(0, $first['gametables_tables']);
        $this->assertGreaterThan(0, $first['gametables_participants']);
    }

    public function test_it_sends_no_mail_notification_job_or_roster_event(): void
    {
        Notification::fake();
        Mail::fake();
        Queue::fake();
        Event::fake([RosterMemberEnrolledInSession::class]);

        $this->runSeeder();

        Notification::assertNothingSent();
        Mail::assertNothingOutgoing();
        Queue::assertNothingPushed();
        Event::assertNotDispatched(RosterMemberEnrolledInSession::class);
    }

    public function test_the_sample_people_have_verified_accounts_with_the_base_role(): void
    {
        $this->runSeeder();

        foreach (['john.doe', 'jane.doe', 'richard.roe', 'mary.major'] as $local) {
            $user = UserModel::query()->where('email', $local.'@example.local')->firstOrFail();

            $this->assertNotNull($user->email_verified_at);
            $this->assertTrue($user->roles()->where('name', 'member')->exists(), $local.' lacks the member role');
        }
    }

    public function test_the_closed_campaign_has_its_roster_and_a_pending_join_request(): void
    {
        $this->runSeeder();

        $campaign = CampaignModel::query()->where('slug', 'cronicas-marca-del-este')->firstOrFail();

        $this->assertFalse($campaign->accepts_new_players);
        $this->assertTrue($campaign->is_published);
        $this->assertEqualsCanonicalizing(
            ['jane.doe@example.local', 'richard.roe@example.local'],
            $campaign->players()->pluck('email')->all(),
        );
        $this->assertTrue($campaign->gameMasters()->where('gametables_game_masters.user_id', $this->userId('john.doe'))->exists());

        $requests = CampaignJoinRequestModel::query()->where('campaign_id', $campaign->id)->get();
        $this->assertCount(1, $requests);
        $this->assertSame(JoinRequestStatus::Pending, $requests->first()?->status);
        $this->assertSame($this->userId('mary.major'), $requests->first()?->user_id);
    }

    public function test_the_closed_campaign_sessions_have_roster_registrations_and_an_absence(): void
    {
        $this->runSeeder();

        $campaign = CampaignModel::query()->where('slug', 'cronicas-marca-del-este')->firstOrFail();
        $sessions = GameTableModel::query()->where('campaign_id', $campaign->id)->orderBy('starts_at')->get();

        $this->assertCount(3, $sessions);
        $this->assertSame(TableStatus::Completed, $sessions[0]->status);
        $this->assertTrue($sessions[0]->starts_at->isPast());

        foreach ([$sessions[1], $sessions[2]] as $upcoming) {
            $this->assertSame(TableStatus::Scheduled, $upcoming->status);
            $this->assertTrue($upcoming->is_published);
            $this->assertTrue($upcoming->starts_at->isFuture());
        }

        $rows = ParticipantModel::query()->whereIn('game_table_id', $sessions->pluck('id'))->get();
        $this->assertTrue($rows->every(fn (ParticipantModel $row): bool => $row->from_roster));

        $absences = $rows->where('status', ParticipantStatus::Cancelled);
        $this->assertCount(1, $absences);
        $absence = $absences->first();
        $this->assertSame($this->userId('richard.roe'), $absence?->user_id);
        $this->assertSame(ParticipantCancellationReason::SessionAbsence, $absence?->cancellation_reason);
        $this->assertNotNull($absence?->cancelled_at);
        $this->assertTrue($sessions[1]->is($absence?->gameTable) || $sessions[2]->is($absence?->gameTable));

        $this->assertSame(5, $rows->where('status', ParticipantStatus::Confirmed)->count());
    }

    public function test_the_recruiting_campaign_mixes_roster_and_outsiders(): void
    {
        $this->runSeeder();

        $campaign = CampaignModel::query()->where('slug', 'herederos-del-dragon')->firstOrFail();
        $this->assertTrue($campaign->accepts_new_players);
        $this->assertSame(['jane.doe@example.local'], $campaign->players()->pluck('email')->all());

        $session = GameTableModel::query()->where('campaign_id', $campaign->id)->sole();
        $this->assertSame(RegistrationType::Everyone, $session->registration_type);
        $rows = ParticipantModel::query()->where('game_table_id', $session->id)->get();
        $this->assertTrue($rows->firstWhere('user_id', $this->userId('jane.doe'))?->from_roster);
        $this->assertFalse($rows->firstWhere('user_id', $this->userId('richard.roe'))?->from_roster);
        $this->assertGreaterThan($rows->count(), $session->max_players);
    }

    public function test_the_review_workflow_states(): void
    {
        $this->runSeeder();

        $campaign = CampaignModel::query()->where('slug', 'secretos-del-norte')->firstOrFail();
        $this->assertSame(FrontendCreationStatus::PendingReview, $campaign->frontend_creation_status);
        $this->assertFalse($campaign->is_published);
        $this->assertSame($this->userId('mary.major'), $campaign->created_by);

        $pending = $this->table('torre-del-hechicero');
        $this->assertSame(FrontendCreationStatus::PendingReview, $pending->frontend_creation_status);
        $this->assertSame(TableStatus::Draft, $pending->status);
        $this->assertFalse($pending->is_published);
        $this->assertNull($pending->moderation_notes);

        $rejected = $this->table('mazmorra-sin-nombre');
        $this->assertSame(FrontendCreationStatus::Draft, $rejected->frontend_creation_status);
        $this->assertFalse($rejected->is_published);
        $this->assertNotEmpty($rejected->moderation_notes);

        $draft = $this->table('bosque-de-los-susurros');
        $this->assertSame(FrontendCreationStatus::Draft, $draft->frontend_creation_status);
        $this->assertNull($draft->moderation_notes);

        foreach ([$pending, $rejected, $draft] as $table) {
            $this->assertSame($this->userId('john.doe'), $table->created_by);
        }
    }

    public function test_the_table_scenarios(): void
    {
        $this->runSeeder();

        $full = $this->table('noche-en-el-faro');
        $rows = ParticipantModel::query()->where('game_table_id', $full->id)->get();
        $this->assertSame($full->max_players, $rows->where('status', ParticipantStatus::Confirmed)->count());
        $waiting = $rows->firstWhere('status', ParticipantStatus::WaitingList);
        $this->assertSame($this->userId('mary.major'), $waiting?->user_id);
        $this->assertSame(1, $waiting?->waiting_list_position);

        $inProgress = $this->table('asalto-al-tren-blindado');
        $this->assertSame(TableStatus::InProgress, $inProgress->status);
        $this->assertTrue($inProgress->starts_at->isPast());
        $this->assertTrue($inProgress->starts_at->copy()->addMinutes($inProgress->duration_minutes)->isFuture());

        $completed = $this->table('ruinas-de-karak');
        $this->assertSame(TableStatus::Completed, $completed->status);
        $this->assertTrue(ParticipantModel::query()->where('game_table_id', $completed->id)->where('status', ParticipantStatus::NoShow)->exists());

        $this->assertSame(RegistrationType::Invite, $this->table('cena-con-el-conde')->registration_type);

        $coDirected = $this->table('ecos-de-la-colmena');
        $this->assertSame(2, $coDirected->gameMasters()->count());
        $this->assertNotEmpty($coDirected->safety_tools);
        $this->assertNotEmpty($coDirected->custom_warnings);
        $this->assertGreaterThan(0, $coDirected->contentWarnings()->count());

        $guests = ParticipantModel::query()
            ->where('game_table_id', $this->table('feria-de-las-sombras')->id)
            ->whereNull('user_id')
            ->get();
        $this->assertCount(2, $guests);
        $awaiting = $guests->firstWhere('email_confirmed_at', null);
        $this->assertSame(GuestConfirmationMode::Wait, $awaiting?->confirmation_mode);
        $this->assertNotNull($awaiting?->confirmation_token);
        $this->assertNotNull($awaiting?->cancellation_token);
        $confirmed = $guests->first(fn (ParticipantModel $row): bool => $row->email_confirmed_at !== null);
        $this->assertSame(ParticipantStatus::Confirmed, $confirmed?->status);
    }

    private function runSeeder(): void
    {
        $seeder = $this->app->make(DevelopmentSeeder::class);
        $seeder->setContainer($this->app)->run();
    }

    /**
     * @return array<string, int>
     */
    private function counts(): array
    {
        $counts = [];

        foreach (self::TABLES as $table) {
            $counts[$table] = DB::table($table)->count();
        }

        return $counts;
    }

    private function table(string $slug): GameTableModel
    {
        return GameTableModel::query()->where('slug', $slug)->firstOrFail();
    }

    private function userId(string $local): string
    {
        return (string) UserModel::query()->where('email', $local.'@example.local')->value('id');
    }
}
