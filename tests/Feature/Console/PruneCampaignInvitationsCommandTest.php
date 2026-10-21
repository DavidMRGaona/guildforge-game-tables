<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Feature\Console;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Carbon\CarbonImmutable;
use DateTimeImmutable;
use Illuminate\Console\Scheduling\Event as ScheduledEvent;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\PendingCommand;
use Modules\GameTables\Application\Services\CampaignInvitationSendLogInterface;
use Modules\GameTables\Console\Commands\PruneCampaignInvitationsCommand;
use Modules\GameTables\Domain\Entities\CampaignInvitation;
use Modules\GameTables\Domain\Repositories\CampaignInvitationRepositoryInterface;
use Modules\GameTables\Domain\ValueObjects\CampaignId;
use Modules\GameTables\Domain\ValueObjects\CampaignInvitationId;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\CampaignInvitationModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\CampaignModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameSystemModel;
use Tests\Support\Modules\ModuleTestCase;

final class PruneCampaignInvitationsCommandTest extends ModuleTestCase
{
    protected ?string $moduleName = 'game-tables';

    protected bool $autoEnableModule = true;

    private UserModel $mary;

    private GameSystemModel $system;

    private CampaignModel $campaign;

    protected function setUp(): void
    {
        parent::setUp();

        // The module is booted after the console application exists, so its commands have to be
        // added to it by hand
        $this->app->make(Kernel::class)->registerCommand($this->app->make(PruneCampaignInvitationsCommand::class));

        $this->travelTo(CarbonImmutable::parse('2026-11-02 10:00:00'));
        $this->mary = UserModel::factory()->create(['name' => 'Mary Major', 'email' => 'mary@example.com']);
        $this->system = GameSystemModel::create(['id' => (string) Str::uuid(), 'name' => 'Sistema', 'slug' => 'sistema', 'is_active' => true]);
        $this->campaign = $this->makeCampaign();
    }

    public function test_prunes_expired_invitations(): void
    {
        $this->invite($this->campaign, 'a@example.com', 'h1', '2026-11-01 10:00:00');
        $this->invite($this->campaign, 'b@example.com', 'h2', '2026-11-10 10:00:00');

        $this->runCommand('Pruned 1 expired invitation(s), 0 of closed campaigns, 0 send record(s).');

        $this->assertSame(1, CampaignInvitationModel::query()->count());
        $this->assertSame('h2', CampaignInvitationModel::query()->firstOrFail()->token_hash);
    }

    public function test_prunes_invitations_of_finished_campaigns(): void
    {
        $completed = $this->makeCampaign(['status' => 'completed']);
        $this->invite($this->campaign, 'a@example.com', 'h1', '2026-11-10 10:00:00');
        $this->invite($completed, 'b@example.com', 'h2', '2026-11-10 10:00:00');

        $this->runCommand('Pruned 0 expired invitation(s), 1 of closed campaigns, 0 send record(s).');

        $this->assertSame([$this->campaign->id], CampaignInvitationModel::query()->pluck('campaign_id')->all());
    }

    public function test_prunes_send_records_older_than_30_days(): void
    {
        $log = $this->app->make(CampaignInvitationSendLogInterface::class);
        $now = new DateTimeImmutable('2026-11-02 10:00:00');
        $log->recordSend($this->campaign->id, 'a@example.com', $now->modify('-31 days'));
        $log->recordRejection($this->campaign->id, 'a@example.com', $now->modify('-40 days'));
        $log->recordSend($this->campaign->id, 'a@example.com', $now->modify('-29 days'));

        $this->runCommand('Pruned 0 expired invitation(s), 0 of closed campaigns, 2 send record(s).');

        $this->assertSame(1, DB::table('gametables_campaign_invitation_sends')->count());
    }

    public function test_is_scheduled_daily(): void
    {
        $events = array_values(array_filter(
            app(Schedule::class)->events(),
            fn (ScheduledEvent $event): bool => str_contains((string) $event->command, 'game-tables:prune-campaign-invitations'),
        ));

        $this->assertCount(1, $events);
        $this->assertSame('0 0 * * *', $events[0]->expression);
        $this->assertTrue($events[0]->withoutOverlapping);
        $this->assertSame(10, $events[0]->expiresAt);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeCampaign(array $overrides = []): CampaignModel
    {
        return CampaignModel::create(array_merge([
            'id' => (string) Str::uuid(),
            'game_system_id' => $this->system->id,
            'created_by' => $this->mary->id,
            'title' => 'La cripta olvidada',
            'slug' => 'campana-'.Str::random(8),
            'status' => 'recruiting',
            'frontend_creation_status' => 'approved',
            'accepts_new_players' => true,
            'roster_access' => 'anyone',
        ], $overrides));
    }

    private function invite(CampaignModel $campaign, string $email, string $hash, string $expiresAt): void
    {
        $this->app->make(CampaignInvitationRepositoryInterface::class)->save(new CampaignInvitation(
            id: CampaignInvitationId::generate(),
            campaignId: new CampaignId($campaign->id),
            email: $email,
            invitedBy: $this->mary->id,
            inviterName: 'Mary Major',
            tokenHash: $hash,
            expiresAt: new DateTimeImmutable($expiresAt),
            sendCount: 1,
            lastSentAt: new DateTimeImmutable('2026-11-02 09:00:00'),
        ));
    }

    private function runCommand(string $expectedOutput): void
    {
        $command = $this->artisan('game-tables:prune-campaign-invitations');
        $this->assertInstanceOf(PendingCommand::class, $command);
        $command->expectsOutput($expectedOutput)->assertSuccessful()->run();
    }
}
