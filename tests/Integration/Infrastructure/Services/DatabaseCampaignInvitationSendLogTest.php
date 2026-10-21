<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Integration\Infrastructure\Services;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\GameTables\Application\Services\CampaignInvitationSendLogInterface;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\CampaignModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameSystemModel;
use Tests\Support\Modules\ModuleTestCase;

final class DatabaseCampaignInvitationSendLogTest extends ModuleTestCase
{
    protected ?string $moduleName = 'game-tables';

    protected bool $autoEnableModule = true;

    private CampaignModel $campaign;

    private CampaignModel $other;

    private DateTimeImmutable $now;

    protected function setUp(): void
    {
        parent::setUp();

        $this->now = new DateTimeImmutable('2026-11-02 10:00:00');
        $mary = UserModel::factory()->create(['name' => 'Mary Major', 'email' => 'mary@example.com']);
        $system = GameSystemModel::create(['id' => (string) Str::uuid(), 'name' => 'Sistema', 'slug' => 'sistema', 'is_active' => true]);
        $this->campaign = $this->makeCampaign($system, $mary, 'una');
        $this->other = $this->makeCampaign($system, $mary, 'otra');
    }

    private function makeCampaign(GameSystemModel $system, UserModel $owner, string $slug): CampaignModel
    {
        return CampaignModel::create([
            'id' => (string) Str::uuid(),
            'game_system_id' => $system->id,
            'created_by' => $owner->id,
            'title' => 'Campaña '.$slug,
            'slug' => $slug,
            'status' => 'recruiting',
            'frontend_creation_status' => 'approved',
            'accepts_new_players' => true,
            'roster_access' => 'anyone',
        ]);
    }

    private function log(): CampaignInvitationSendLogInterface
    {
        return $this->app->make(CampaignInvitationSendLogInterface::class);
    }

    public function test_sends_since_counts_only_the_window(): void
    {
        $email = 'john@example.com';
        $this->log()->recordSend($this->campaign->id, $email, $this->now->modify('-31 days'));
        $this->log()->recordSend($this->campaign->id, $email, $this->now->modify('-10 days'));
        $this->log()->recordSend($this->campaign->id, $email, $this->now->modify('-1 hour'));
        $this->log()->recordRejection($this->campaign->id, $email, $this->now->modify('-2 days'));

        $this->assertSame(2, $this->log()->sendsSince($this->campaign->id, $email, $this->now->modify('-30 days')));
    }

    public function test_last_send_at(): void
    {
        $email = 'john@example.com';
        $this->assertNull($this->log()->lastSendAt($this->campaign->id, $email));

        $this->log()->recordSend($this->campaign->id, $email, $this->now->modify('-3 days'));
        $this->log()->recordSend($this->campaign->id, $email, $this->now->modify('-1 day'));
        $this->log()->recordRejection($this->campaign->id, $email, $this->now);

        $last = $this->log()->lastSendAt($this->campaign->id, $email);
        $this->assertNotNull($last);
        $this->assertSame('2026-11-01 10:00:00', $last->format('Y-m-d H:i:s'));
    }

    public function test_rejected_since(): void
    {
        $email = 'john@example.com';
        $this->log()->recordSend($this->campaign->id, $email, $this->now);
        $this->assertFalse($this->log()->rejectedSince($this->campaign->id, $email, $this->now->modify('-30 days')));

        $this->log()->recordRejection($this->campaign->id, $email, $this->now->modify('-31 days'));
        $this->assertFalse($this->log()->rejectedSince($this->campaign->id, $email, $this->now->modify('-30 days')));

        $this->log()->recordRejection($this->campaign->id, $email, $this->now->modify('-5 days'));
        $this->assertTrue($this->log()->rejectedSince($this->campaign->id, $email, $this->now->modify('-30 days')));
    }

    public function test_hash_is_per_campaign(): void
    {
        $this->log()->recordSend($this->campaign->id, 'john@example.com', $this->now);
        $this->log()->recordRejection($this->campaign->id, 'john@example.com', $this->now);

        $since = $this->now->modify('-30 days');
        $this->assertSame(0, $this->log()->sendsSince($this->other->id, 'john@example.com', $since));
        $this->assertFalse($this->log()->rejectedSince($this->other->id, 'john@example.com', $since));
        $this->assertNull($this->log()->lastSendAt($this->other->id, 'john@example.com'));
        $this->assertSame(0, $this->log()->sendsSince($this->campaign->id, 'jane@example.com', $since));

        $hashes = DB::table('gametables_campaign_invitation_sends')->pluck('email_hash')->unique();
        $this->assertCount(1, $hashes);
        $this->assertSame(hash_hmac('sha256', $this->campaign->id.'|john@example.com', (string) config('app.key')), $hashes->first());
    }

    public function test_email_is_never_stored(): void
    {
        $this->log()->recordSend($this->campaign->id, 'john@example.com', $this->now);
        $this->log()->recordRejection($this->campaign->id, 'john@example.com', $this->now);

        $rows = DB::table('gametables_campaign_invitation_sends')->get();
        $this->assertCount(2, $rows);
        foreach ($rows as $row) {
            foreach ((array) $row as $value) {
                $this->assertStringNotContainsString('john', (string) $value);
                $this->assertStringNotContainsString('@', (string) $value);
            }
        }
    }

    public function test_prune_before(): void
    {
        $this->log()->recordSend($this->campaign->id, 'john@example.com', $this->now->modify('-40 days'));
        $this->log()->recordRejection($this->campaign->id, 'john@example.com', $this->now->modify('-31 days'));
        $this->log()->recordSend($this->campaign->id, 'john@example.com', $this->now->modify('-2 days'));

        $deleted = $this->log()->pruneBefore($this->now->modify('-30 days'));

        $this->assertSame(2, $deleted);
        $this->assertSame(1, DB::table('gametables_campaign_invitation_sends')->count());
    }
}
