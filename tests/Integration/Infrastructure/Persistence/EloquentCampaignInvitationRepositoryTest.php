<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Integration\Infrastructure\Persistence;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use DateTimeImmutable;
use Illuminate\Support\Str;
use Modules\GameTables\Domain\Entities\CampaignInvitation;
use Modules\GameTables\Domain\Exceptions\RosterActionRefusedException;
use Modules\GameTables\Domain\Repositories\CampaignInvitationRepositoryInterface;
use Modules\GameTables\Domain\ValueObjects\CampaignId;
use Modules\GameTables\Domain\ValueObjects\CampaignInvitationId;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\CampaignInvitationModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\CampaignModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameSystemModel;
use Tests\Support\Modules\ModuleTestCase;

final class EloquentCampaignInvitationRepositoryTest extends ModuleTestCase
{
    protected ?string $moduleName = 'game-tables';

    protected bool $autoEnableModule = true;

    private UserModel $mary;

    private GameSystemModel $system;

    private CampaignModel $campaign;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mary = UserModel::factory()->create(['name' => 'Mary Major', 'email' => 'mary@example.com']);
        $this->system = GameSystemModel::create(['id' => (string) Str::uuid(), 'name' => 'Sistema', 'slug' => 'sistema', 'is_active' => true]);
        $this->campaign = $this->makeCampaign();
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeCampaign(array $overrides = []): CampaignModel
    {
        $slug = 'campana-'.Str::random(8);

        return CampaignModel::create(array_merge([
            'id' => (string) Str::uuid(),
            'game_system_id' => $this->system->id,
            'created_by' => $this->mary->id,
            'title' => 'La cripta olvidada',
            'slug' => $slug,
            'status' => 'recruiting',
            'frontend_creation_status' => 'approved',
            'accepts_new_players' => true,
            'roster_access' => 'anyone',
        ], $overrides));
    }

    private function repository(): CampaignInvitationRepositoryInterface
    {
        return $this->app->make(CampaignInvitationRepositoryInterface::class);
    }

    private function invitation(
        ?CampaignModel $campaign = null,
        string $email = 'john@example.com',
        string $hash = 'hash-a',
        string $expiresAt = '2026-11-16 10:00:00',
        string $lastSentAt = '2026-11-02 10:00:00',
    ): CampaignInvitation {
        return new CampaignInvitation(
            id: CampaignInvitationId::generate(),
            campaignId: new CampaignId(($campaign ?? $this->campaign)->id),
            email: $email,
            invitedBy: $this->mary->id,
            inviterName: 'Mary Major',
            tokenHash: $hash,
            expiresAt: new DateTimeImmutable($expiresAt),
            sendCount: 1,
            lastSentAt: new DateTimeImmutable($lastSentAt),
        );
    }

    public function test_save_and_find_by_token_hash(): void
    {
        $invitation = $this->invitation();
        $this->repository()->save($invitation);

        $byHash = $this->repository()->findByTokenHash('hash-a');
        $byId = $this->repository()->find($invitation->id);

        $this->assertNotNull($byHash);
        $this->assertNotNull($byId);
        $this->assertSame($invitation->id->value, $byHash->id->value);
        $this->assertSame('john@example.com', $byHash->email);
        $this->assertSame($this->mary->id, $byHash->invitedBy);
        $this->assertSame('Mary Major', $byHash->inviterName);
        $this->assertSame(1, $byHash->sendCount);
        $this->assertSame('2026-11-16 10:00:00', $byHash->expiresAt->format('Y-m-d H:i:s'));
        $this->assertSame('2026-11-02 10:00:00', $byHash->lastSentAt->format('Y-m-d H:i:s'));
        $this->assertNull($this->repository()->findByTokenHash('other'));
    }

    public function test_resend_is_persisted(): void
    {
        $invitation = $this->invitation();
        $this->repository()->save($invitation);

        $invitation->resend('hash-b', new DateTimeImmutable('2026-11-05 09:00:00'));
        $this->repository()->save($invitation);

        $stored = $this->repository()->find($invitation->id);
        $this->assertNotNull($stored);
        $this->assertSame(2, $stored->sendCount);
        $this->assertNull($this->repository()->findByTokenHash('hash-a'));
        $this->assertNotNull($this->repository()->findByTokenHash('hash-b'));
        $this->assertSame('2026-11-16 10:00:00', $stored->expiresAt->format('Y-m-d H:i:s'));
    }

    public function test_find_pending_by_campaign_and_email(): void
    {
        $this->repository()->save($this->invitation());

        $this->assertNotNull($this->repository()->findPending(new CampaignId($this->campaign->id), 'john@example.com'));
        $this->assertNull($this->repository()->findPending(new CampaignId($this->campaign->id), 'jane@example.com'));
        $this->assertNull($this->repository()->findPending(new CampaignId($this->makeCampaign()->id), 'john@example.com'));
    }

    public function test_pending_for_lists_oldest_first(): void
    {
        $first = $this->invitation(email: 'john@example.com', hash: 'h1');
        $second = $this->invitation(email: 'jane@example.com', hash: 'h2');
        $this->repository()->save($first);
        $this->repository()->save($second);
        CampaignInvitationModel::query()->whereKey($first->id->value)->update(['created_at' => '2026-11-02 09:00:00']);
        CampaignInvitationModel::query()->whereKey($second->id->value)->update(['created_at' => '2026-11-01 09:00:00']);

        $list = $this->repository()->pendingFor(new CampaignId($this->campaign->id));

        $this->assertSame(['jane@example.com', 'john@example.com'], array_map(fn (CampaignInvitation $i): string => $i->email, $list));
    }

    public function test_one_invitation_per_campaign_and_email(): void
    {
        $this->repository()->save($this->invitation());

        try {
            $this->repository()->save($this->invitation(hash: 'hash-b'));
            $this->fail('A second invitation for the same campaign and email must be refused.');
        } catch (RosterActionRefusedException $e) {
            $this->assertSame('invitation_pending', $e->reason);
        }
    }

    public function test_pending_for_email_skips_expired(): void
    {
        $other = $this->makeCampaign();
        $this->repository()->save($this->invitation(hash: 'h1', expiresAt: '2026-11-16 10:00:00'));
        $this->repository()->save($this->invitation(campaign: $other, hash: 'h2', expiresAt: '2026-11-01 10:00:00'));
        $this->repository()->save($this->invitation(campaign: $this->makeCampaign(), email: 'jane@example.com', hash: 'h3'));

        $list = $this->repository()->pendingForEmail('john@example.com', new DateTimeImmutable('2026-11-02 10:00:00'));

        $this->assertCount(1, $list);
        $this->assertSame('h1', $list[0]->tokenHash);
    }

    public function test_delete_removes_the_invitation(): void
    {
        $invitation = $this->invitation();
        $this->repository()->save($invitation);

        $this->repository()->delete($invitation->id);

        $this->assertNull($this->repository()->find($invitation->id));
    }

    public function test_delete_expired_returns_the_count(): void
    {
        $this->repository()->save($this->invitation(email: 'a@example.com', hash: 'h1', expiresAt: '2026-11-01 10:00:00'));
        $this->repository()->save($this->invitation(email: 'b@example.com', hash: 'h2', expiresAt: '2026-11-02 10:00:00'));
        $this->repository()->save($this->invitation(email: 'c@example.com', hash: 'h3', expiresAt: '2026-11-03 10:00:00'));

        $deleted = $this->repository()->deleteExpired(new DateTimeImmutable('2026-11-02 10:00:00'));

        $this->assertSame(2, $deleted);
        $this->assertSame(1, CampaignInvitationModel::query()->count());
    }

    public function test_delete_for_campaigns_not_open_keeps_published_ones(): void
    {
        $approved = $this->campaign;
        $published = $this->makeCampaign(['is_published' => true, 'frontend_creation_status' => null]);
        $pending = $this->makeCampaign(['is_published' => false, 'frontend_creation_status' => 'pending_review']);
        $unset = $this->makeCampaign(['is_published' => false, 'frontend_creation_status' => null]);
        $completed = $this->makeCampaign(['status' => 'completed']);
        $cancelled = $this->makeCampaign(['status' => 'cancelled', 'is_published' => true]);

        foreach ([$approved, $published, $pending, $unset, $completed, $cancelled] as $i => $campaign) {
            $this->repository()->save($this->invitation(campaign: $campaign, hash: 'h'.$i));
        }

        $deleted = $this->repository()->deleteForCampaignsNotOpen();

        $this->assertSame(4, $deleted);
        $left = CampaignInvitationModel::query()->pluck('campaign_id')->all();
        sort($left);
        $expected = [$approved->id, $published->id];
        sort($expected);
        $this->assertSame($expected, $left);
    }

    public function test_deleting_the_campaign_deletes_its_invitations(): void
    {
        $this->repository()->save($this->invitation());

        $this->campaign->delete();

        $this->assertSame(0, CampaignInvitationModel::query()->count());
    }

    public function test_deleting_the_inviter_keeps_the_invitation(): void
    {
        $co = UserModel::factory()->create(['name' => 'Jane Doe', 'email' => 'jane@example.com']);
        $invitation = new CampaignInvitation(
            id: CampaignInvitationId::generate(),
            campaignId: new CampaignId($this->campaign->id),
            email: 'john@example.com',
            invitedBy: $co->id,
            inviterName: 'Jane Doe',
            tokenHash: 'hash-c',
            expiresAt: new DateTimeImmutable('2026-11-16 10:00:00'),
            sendCount: 1,
            lastSentAt: new DateTimeImmutable('2026-11-02 10:00:00'),
        );
        $this->repository()->save($invitation);

        $co->forceDelete();

        $stored = $this->repository()->find($invitation->id);
        $this->assertNotNull($stored);
        $this->assertNull($stored->invitedBy);
    }
}
