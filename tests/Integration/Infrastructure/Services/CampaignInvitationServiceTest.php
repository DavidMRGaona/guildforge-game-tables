<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Integration\Infrastructure\Services;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Modules\GameTables\Application\Services\CampaignInvitationSendLogInterface;
use Modules\GameTables\Application\Services\CampaignInvitationServiceInterface;
use Modules\GameTables\Domain\Entities\CampaignInvitation;
use Modules\GameTables\Domain\Events\CampaignInvitationSent;
use Modules\GameTables\Domain\Exceptions\CampaignNotFoundException;
use Modules\GameTables\Domain\Exceptions\RosterActionRefusedException;
use Modules\GameTables\Domain\Repositories\CampaignInvitationRepositoryInterface;
use Modules\GameTables\Domain\Repositories\CampaignRosterRepositoryInterface;
use Modules\GameTables\Domain\ValueObjects\CampaignId;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\CampaignModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameSystemModel;
use Tests\Support\Modules\ModuleTestCase;

/**
 * Inviting players by email, resending and cancelling, with the send limits. Real repositories,
 * lock and send log.
 */
final class CampaignInvitationServiceTest extends ModuleTestCase
{
    protected ?string $moduleName = 'game-tables';

    protected bool $autoEnableModule = true;

    private CampaignModel $campaign;

    private CampaignId $campaignId;

    private UserModel $mary;

    private UserModel $john;

    private UserModel $jane;

    protected function setUp(): void
    {
        parent::setUp();

        Event::fake([CampaignInvitationSent::class]);
        config()->set('modules.settings.game-tables', []);
        $this->travelTo(CarbonImmutable::parse('2026-11-02 10:00:00'));

        $this->mary = UserModel::factory()->create(['name' => 'Mary Major', 'email' => 'mary@example.com']);
        $this->john = UserModel::factory()->create(['name' => 'John Doe', 'email' => 'john@example.com']);
        $this->jane = UserModel::factory()->create(['name' => 'Jane Doe', 'email' => 'jane@example.com']);

        $system = GameSystemModel::create(['id' => (string) Str::uuid(), 'name' => 'Sistema', 'slug' => 'sistema', 'is_active' => true]);
        $this->campaign = CampaignModel::create([
            'id' => (string) Str::uuid(),
            'game_system_id' => $system->id,
            'created_by' => $this->mary->id,
            'title' => 'La cripta olvidada',
            'slug' => 'la-cripta-olvidada',
            'status' => 'recruiting',
            'frontend_creation_status' => 'approved',
            'accepts_new_players' => true,
            'max_players' => 3,
            'roster_access' => 'anyone',
        ]);
        $this->campaignId = new CampaignId($this->campaign->id);
    }

    private function service(): CampaignInvitationServiceInterface
    {
        return $this->app->make(CampaignInvitationServiceInterface::class);
    }

    private function invitations(): CampaignInvitationRepositoryInterface
    {
        return $this->app->make(CampaignInvitationRepositoryInterface::class);
    }

    private function sendLog(): CampaignInvitationSendLogInterface
    {
        return $this->app->make(CampaignInvitationSendLogInterface::class);
    }

    private function invite(string $email): void
    {
        $this->service()->invite($this->campaign->id, $email, $this->mary->id);
    }

    private function pending(string $email): CampaignInvitation
    {
        $invitation = $this->invitations()->findPending($this->campaignId, $email);
        $this->assertNotNull($invitation);

        return $invitation;
    }

    private function assertRefused(string $reason, Closure $action): void
    {
        try {
            $action();
            $this->fail("Expected the action to be refused with {$reason}");
        } catch (RosterActionRefusedException $e) {
            $this->assertSame($reason, $e->reason);
        }
    }

    public function test_invite_creates_and_announces(): void
    {
        $this->invite('ana@example.com');

        $invitation = $this->pending('ana@example.com');
        $this->assertSame(1, $invitation->sendCount);
        $this->assertSame('Mary Major', $invitation->inviterName);
        $this->assertSame($this->mary->id, $invitation->invitedBy);
        $this->assertEquals(CarbonImmutable::now()->addDays(14)->toDateTimeImmutable(), $invitation->expiresAt);
        Event::assertDispatchedTimes(CampaignInvitationSent::class, 1);
        Event::assertDispatched(
            CampaignInvitationSent::class,
            fn (CampaignInvitationSent $event): bool => $event->invitationId === $invitation->id->value
                && $event->campaignId === $this->campaign->id
                && $event->email === 'ana@example.com'
                && hash('sha256', $event->token) === $invitation->tokenHash,
        );
    }

    public function test_invite_answers_the_same_for_an_account_and_for_no_account(): void
    {
        $this->invite('john@example.com');
        $this->invite('nadie@example.com');

        $this->assertNotNull($this->invitations()->findPending($this->campaignId, 'john@example.com'));
        $this->assertNotNull($this->invitations()->findPending($this->campaignId, 'nadie@example.com'));
        Event::assertDispatchedTimes(CampaignInvitationSent::class, 2);
    }

    public function test_invite_normalises_the_email(): void
    {
        $this->invite('  Ana@Example.COM ');
        $this->assertSame(1, $this->pending('ana@example.com')->sendCount);

        $this->travel(25)->hours();
        $this->invite('ana@example.com');

        $this->assertSame(2, $this->pending('ana@example.com')->sendCount);
        $this->assertCount(1, $this->invitations()->pendingFor($this->campaignId));
        Event::assertDispatchedTimes(CampaignInvitationSent::class, 2);
    }

    public function test_inviting_a_director_refuses(): void
    {
        $this->assertRefused('is_director', fn () => $this->invite('mary@example.com'));

        $this->assertSame([], $this->invitations()->pendingFor($this->campaignId));
        Event::assertNotDispatched(CampaignInvitationSent::class);
    }

    public function test_inviting_a_member_refuses(): void
    {
        $this->app->make(CampaignRosterRepositoryInterface::class)->add($this->campaignId, $this->john->id, CarbonImmutable::now());

        $this->assertRefused('account_already_member', fn () => $this->invite('John@example.com'));

        $this->assertSame([], $this->invitations()->pendingFor($this->campaignId));
        Event::assertNotDispatched(CampaignInvitationSent::class);
    }

    public function test_inviting_with_a_full_roster_refuses(): void
    {
        $roster = $this->app->make(CampaignRosterRepositoryInterface::class);
        foreach ([$this->john, $this->jane, UserModel::factory()->create()] as $member) {
            $roster->add($this->campaignId, $member->id, CarbonImmutable::now());
        }

        $this->assertRefused('roster_full', fn () => $this->invite('ana@example.com'));

        Event::assertNotDispatched(CampaignInvitationSent::class);
    }

    public function test_inviting_to_a_finished_campaign_refuses(): void
    {
        $this->campaign->update(['status' => 'completed']);

        $this->assertRefused('finished', fn () => $this->invite('ana@example.com'));

        Event::assertNotDispatched(CampaignInvitationSent::class);
    }

    public function test_inviting_to_an_unknown_campaign_fails(): void
    {
        $this->expectException(CampaignNotFoundException::class);

        $this->service()->invite((string) Str::uuid(), 'ana@example.com', $this->mary->id);
    }

    public function test_a_fourth_send_within_30_days_refuses(): void
    {
        foreach ([0, 1, 2] as $_) {
            $this->invite('ana@example.com');
            $this->travel(25)->hours();
        }

        $this->assertRefused('invitation_limit', fn () => $this->invite('ana@example.com'));
        Event::assertDispatchedTimes(CampaignInvitationSent::class, 3);
    }

    public function test_cancelling_does_not_reset_the_send_limit(): void
    {
        foreach ([0, 1, 2] as $_) {
            $this->invite('ana@example.com');
            $this->service()->cancel($this->pending('ana@example.com')->id->value);
            $this->travel(25)->hours();
        }

        $this->assertRefused('invitation_limit', fn () => $this->invite('ana@example.com'));
        $this->assertNull($this->invitations()->findPending($this->campaignId, 'ana@example.com'));
    }

    public function test_sends_within_24_hours_refuse(): void
    {
        $this->invite('ana@example.com');
        $this->travel(23)->hours();

        $this->assertRefused('invitation_too_soon', fn () => $this->invite('ana@example.com'));
        $this->assertRefused('invitation_too_soon', fn () => $this->service()->resend($this->pending('ana@example.com')->id->value, $this->mary->id));
        Event::assertDispatchedTimes(CampaignInvitationSent::class, 1);
    }

    public function test_after_a_rejection_inviting_refuses_for_30_days(): void
    {
        $this->sendLog()->recordSend($this->campaign->id, 'ana@example.com', CarbonImmutable::now()->toDateTimeImmutable());
        $this->sendLog()->recordRejection($this->campaign->id, 'ana@example.com', CarbonImmutable::now()->toDateTimeImmutable());

        $this->travel(29)->days();
        $this->assertRefused('invitation_limit', fn () => $this->invite('ana@example.com'));

        $this->travel(2)->days();
        $this->invite('ana@example.com');

        Event::assertDispatchedTimes(CampaignInvitationSent::class, 1);
    }

    public function test_resend_regenerates_the_token_and_keeps_the_expiry(): void
    {
        $this->invite('ana@example.com');
        $first = $this->pending('ana@example.com');
        $this->travel(25)->hours();

        $this->service()->resend($first->id->value, $this->mary->id);

        $second = $this->pending('ana@example.com');
        $this->assertSame($first->id->value, $second->id->value);
        $this->assertNotSame($first->tokenHash, $second->tokenHash);
        $this->assertEquals($first->expiresAt, $second->expiresAt);
        $this->assertSame(2, $second->sendCount);
        Event::assertDispatchedTimes(CampaignInvitationSent::class, 2);
        Event::assertDispatched(
            CampaignInvitationSent::class,
            fn (CampaignInvitationSent $event): bool => hash('sha256', $event->token) === $second->tokenHash,
        );
    }

    public function test_resending_an_unknown_or_expired_invitation_refuses(): void
    {
        $this->assertRefused('invitation_not_found', fn () => $this->service()->resend((string) Str::uuid(), $this->mary->id));

        $this->invite('ana@example.com');
        $id = $this->pending('ana@example.com')->id->value;
        $this->travel(15)->days();

        $this->assertRefused('invitation_not_found', fn () => $this->service()->resend($id, $this->mary->id));
        Event::assertDispatchedTimes(CampaignInvitationSent::class, 1);
    }

    public function test_inviting_over_an_expired_pending_invitation_replaces_it(): void
    {
        $this->invite('ana@example.com');
        $old = $this->pending('ana@example.com');
        $this->travel(15)->days();

        $this->invite('ana@example.com');

        $new = $this->pending('ana@example.com');
        $this->assertNotSame($old->id->value, $new->id->value);
        $this->assertSame(1, $new->sendCount);
        $this->assertTrue($new->expiresAt > CarbonImmutable::now()->toDateTimeImmutable());
    }

    public function test_cancel_deletes(): void
    {
        $this->invite('ana@example.com');

        $this->service()->cancel($this->pending('ana@example.com')->id->value);

        $this->assertNull($this->invitations()->findPending($this->campaignId, 'ana@example.com'));
        $this->assertRefused('invitation_not_found', fn () => $this->service()->cancel((string) Str::uuid()));
    }

    public function test_pending_for_lists_with_can_resend(): void
    {
        $this->invite('ana@example.com');
        $this->travel(2)->hours();
        $this->invite('bea@example.com');
        $this->travel(23)->hours();

        $list = $this->service()->pendingFor($this->campaign->id, CarbonImmutable::now()->toDateTimeImmutable());

        $this->assertCount(2, $list);
        $this->assertSame('ana@example.com', $list[0]->email);
        $this->assertTrue($list[0]->canResend);
        $this->assertSame('bea@example.com', $list[1]->email);
        $this->assertFalse($list[1]->canResend);
        $this->assertSame('La cripta olvidada', $list[0]->campaignTitle);
        $this->assertSame('la-cripta-olvidada', $list[0]->campaignSlug);
        $this->assertSame('Mary Major', $list[0]->inviterName);
        $this->assertSame(1, $list[0]->sendCount);
        $this->assertSame(CarbonImmutable::parse('2026-11-02 10:00:00')->addDays(14)->toIso8601String(), $list[0]->expiresAt);
        $this->assertArrayHasKey('can_resend', $list[0]->toArray());

        // Third send reached: no more resends even after 24 h
        $this->service()->resend($this->pending('ana@example.com')->id->value, $this->mary->id);
        $this->travel(25)->hours();
        $this->service()->resend($this->pending('ana@example.com')->id->value, $this->mary->id);
        $this->travel(25)->hours();
        $later = $this->service()->pendingFor($this->campaign->id, CarbonImmutable::now()->toDateTimeImmutable());
        $this->assertFalse($later[0]->canResend);

        // Expired invitations are left out
        $this->travel(15)->days();
        $this->assertSame([], $this->service()->pendingFor($this->campaign->id, CarbonImmutable::now()->toDateTimeImmutable()));
    }
}
