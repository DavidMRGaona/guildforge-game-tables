<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Integration\Infrastructure\Services;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Carbon\CarbonImmutable;
use Closure;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Modules\GameTables\Application\Services\CampaignInvitationSendLogInterface;
use Modules\GameTables\Application\Services\CampaignInvitationServiceInterface;
use Modules\GameTables\Application\Services\CampaignRosterServiceInterface;
use Modules\GameTables\Domain\Entities\CampaignInvitation;
use Modules\GameTables\Domain\Enums\RosterAccess;
use Modules\GameTables\Domain\Events\CampaignInvitationAccepted;
use Modules\GameTables\Domain\Events\CampaignInvitationSent;
use Modules\GameTables\Domain\Events\CampaignRosterMemberAdded;
use Modules\GameTables\Domain\Exceptions\CampaignNotFoundException;
use Modules\GameTables\Domain\Exceptions\RosterActionRefusedException;
use Modules\GameTables\Domain\Repositories\CampaignInvitationRepositoryInterface;
use Modules\GameTables\Domain\Repositories\CampaignRosterRepositoryInterface;
use Modules\GameTables\Domain\ValueObjects\CampaignId;
use Modules\GameTables\Domain\ValueObjects\CampaignInvitationId;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\CampaignInvitationModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\CampaignJoinRequestModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\CampaignModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameSystemModel;
use Modules\GameTables\Infrastructure\Services\DatabaseCampaignInvitationSendLog;
use RuntimeException;
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

        Event::fake([CampaignInvitationSent::class, CampaignInvitationAccepted::class, CampaignRosterMemberAdded::class]);
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
            $this->service()->cancel($this->campaign->id, $this->pending('ana@example.com')->id->value);
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
        $this->assertRefused('invitation_too_soon', fn () => $this->service()->resend($this->campaign->id, $this->pending('ana@example.com')->id->value, $this->mary->id));
        Event::assertDispatchedTimes(CampaignInvitationSent::class, 1);
    }

    // The night the clocks go back lasts 25 hours: 24 hours by the wall clock would be one too many
    public function test_the_24_hours_between_sends_are_elapsed_time_across_a_clock_change(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-24 12:30:00'));
        $this->invite('ana@example.com');

        // 23 and a half hours later
        $this->travelTo(CarbonImmutable::parse('2026-10-25 11:00:00'));
        $this->assertRefused('invitation_too_soon', fn () => $this->service()->resend($this->campaign->id, $this->pending('ana@example.com')->id->value, $this->mary->id));

        // 24 and a half hours later
        $this->travelTo(CarbonImmutable::parse('2026-10-25 12:00:00'));
        $this->service()->resend($this->campaign->id, $this->pending('ana@example.com')->id->value, $this->mary->id);

        Event::assertDispatchedTimes(CampaignInvitationSent::class, 2);
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

        $this->service()->resend($this->campaign->id, $first->id->value, $this->mary->id);

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
        $this->assertRefused('invitation_not_found', fn () => $this->service()->resend($this->campaign->id, (string) Str::uuid(), $this->mary->id));

        $this->invite('ana@example.com');
        $id = $this->pending('ana@example.com')->id->value;
        $this->travel(15)->days();

        $this->assertRefused('invitation_not_found', fn () => $this->service()->resend($this->campaign->id, $id, $this->mary->id));
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

    private function otherCampaignInvitation(): CampaignInvitation
    {
        $other = CampaignModel::create([
            'id' => (string) Str::uuid(),
            'game_system_id' => $this->campaign->game_system_id,
            'created_by' => $this->mary->id,
            'title' => 'Otra campaña',
            'slug' => 'otra-campana',
            'status' => 'recruiting',
            'frontend_creation_status' => 'approved',
            'accepts_new_players' => true,
            'max_players' => 3,
            'roster_access' => 'anyone',
        ]);
        $this->service()->invite($other->id, 'ana@example.com', $this->mary->id);
        Event::assertDispatchedTimes(CampaignInvitationSent::class, 1);

        $invitation = $this->invitations()->findPending(new CampaignId($other->id), 'ana@example.com');
        $this->assertNotNull($invitation);

        return $invitation;
    }

    public function test_resend_of_another_campaigns_invitation_answers_not_found(): void
    {
        $foreign = $this->otherCampaignInvitation();
        $this->travel(25)->hours();

        $this->assertRefused('invitation_not_found', fn () => $this->service()->resend($this->campaign->id, $foreign->id->value, $this->mary->id));

        $this->assertSame(1, $this->invitations()->find($foreign->id)?->sendCount);
        Event::assertDispatchedTimes(CampaignInvitationSent::class, 1);
    }

    public function test_cancel_of_another_campaigns_invitation_answers_not_found(): void
    {
        $foreign = $this->otherCampaignInvitation();

        $this->assertRefused('invitation_not_found', fn () => $this->service()->cancel($this->campaign->id, $foreign->id->value));

        $this->assertNotNull($this->invitations()->find($foreign->id));
        Event::assertDispatchedTimes(CampaignInvitationSent::class, 1);
    }

    public function test_cancel_deletes(): void
    {
        $this->invite('ana@example.com');

        $this->service()->cancel($this->campaign->id, $this->pending('ana@example.com')->id->value);

        $this->assertNull($this->invitations()->findPending($this->campaignId, 'ana@example.com'));
        $this->assertRefused('invitation_not_found', fn () => $this->service()->cancel($this->campaign->id, (string) Str::uuid()));
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
        $this->service()->resend($this->campaign->id, $this->pending('ana@example.com')->id->value, $this->mary->id);
        $this->travel(25)->hours();
        $this->service()->resend($this->campaign->id, $this->pending('ana@example.com')->id->value, $this->mary->id);
        $this->travel(25)->hours();
        $later = $this->service()->pendingFor($this->campaign->id, CarbonImmutable::now()->toDateTimeImmutable());
        $this->assertFalse($later[0]->canResend);

        // Expired invitations are left out
        $this->travel(15)->days();
        $this->assertSame([], $this->service()->pendingFor($this->campaign->id, CarbonImmutable::now()->toDateTimeImmutable()));
    }

    /**
     * Invites the email and returns the plain token the email would carry.
     */
    private function tokenFor(string $email): string
    {
        $this->invite($email);

        $tokens = Event::dispatched(CampaignInvitationSent::class)
            ->map(fn (array $args): CampaignInvitationSent => $args[0])
            ->filter(fn (CampaignInvitationSent $event): bool => $event->email === mb_strtolower(trim($email)));

        $last = $tokens->last();
        $this->assertNotNull($last);

        return $last->token;
    }

    private function isMember(UserModel $user): bool
    {
        return $this->app->make(CampaignRosterRepositoryInterface::class)->contains($this->campaignId, $user->id);
    }

    public function test_accept_adds_to_the_roster_and_deletes(): void
    {
        $token = $this->tokenFor('john@example.com');

        $this->service()->accept($token, $this->john->id);

        $this->assertTrue($this->isMember($this->john));
        $this->assertNull($this->invitations()->findPending($this->campaignId, 'john@example.com'));
        Event::assertDispatched(CampaignInvitationAccepted::class, fn (CampaignInvitationAccepted $e): bool => $e->campaignId === $this->campaign->id && $e->userId === $this->john->id);
        Event::assertDispatched(CampaignRosterMemberAdded::class);
    }

    public function test_accept_matches_the_account_email_case_insensitively(): void
    {
        $ana = UserModel::factory()->create(['email' => 'Ana@Example.com']);
        $token = $this->tokenFor('ana@example.com');

        $this->service()->accept($token, $ana->id);

        $this->assertTrue($this->isMember($ana));
    }

    public function test_accept_with_another_account_refuses(): void
    {
        $token = $this->tokenFor('john@example.com');

        $this->assertRefused('invitation_other_account', fn () => $this->service()->accept($token, $this->jane->id));

        $this->assertNotNull($this->invitations()->findPending($this->campaignId, 'john@example.com'));
        $this->assertFalse($this->isMember($this->jane));
        Event::assertNotDispatched(CampaignInvitationAccepted::class);
    }

    public function test_accept_with_an_expired_token_refuses(): void
    {
        $token = $this->tokenFor('john@example.com');
        $this->travel(15)->days();

        $this->assertRefused('invitation_not_found', fn () => $this->service()->accept($token, $this->john->id));

        $this->assertFalse($this->isMember($this->john));
    }

    public function test_accept_with_an_unknown_or_malformed_token_refuses(): void
    {
        $this->assertRefused('invitation_not_found', fn () => $this->service()->accept(Str::random(64), $this->john->id));
        $this->assertRefused('invitation_not_found', fn () => $this->service()->accept("x' OR 1=1 --", $this->john->id));
        $this->assertRefused('invitation_not_found', fn () => $this->service()->reject(''));
        $this->assertNull($this->service()->viewByToken('!!', CarbonImmutable::now()->toDateTimeImmutable()));
    }

    public function test_accepting_twice_answers_not_found_the_second_time(): void
    {
        $token = $this->tokenFor('john@example.com');
        $this->service()->accept($token, $this->john->id);

        $this->assertRefused('invitation_not_found', fn () => $this->service()->accept($token, $this->john->id));
    }

    public function test_accept_with_a_full_roster_keeps_the_invitation(): void
    {
        $token = $this->tokenFor('john@example.com');
        $roster = $this->app->make(CampaignRosterRepositoryInterface::class);
        foreach ([$this->jane, UserModel::factory()->create(), UserModel::factory()->create()] as $member) {
            $roster->add($this->campaignId, $member->id, CarbonImmutable::now());
        }

        $this->assertRefused('roster_full', fn () => $this->service()->accept($token, $this->john->id));

        $this->assertNotNull($this->invitations()->findPending($this->campaignId, 'john@example.com'));
        $this->assertFalse($this->isMember($this->john));
    }

    public function test_accepting_for_a_finished_campaign_refuses_and_deletes(): void
    {
        $token = $this->tokenFor('john@example.com');
        $this->campaign->update(['status' => 'completed']);

        $this->assertRefused('finished', fn () => $this->service()->accept($token, $this->john->id));

        $this->assertNull($this->invitations()->findPending($this->campaignId, 'john@example.com'));
    }

    public function test_accepting_when_already_a_member_refuses_and_deletes(): void
    {
        $token = $this->tokenFor('john@example.com');
        $this->app->make(CampaignRosterRepositoryInterface::class)->add($this->campaignId, $this->john->id, CarbonImmutable::now());

        $this->assertRefused('account_already_member', fn () => $this->service()->accept($token, $this->john->id));

        $this->assertNull($this->invitations()->findPending($this->campaignId, 'john@example.com'));
    }

    public function test_accept_skips_members_only(): void
    {
        $this->campaign->update(['roster_access' => RosterAccess::MembersOnly->value]);
        $token = $this->tokenFor('john@example.com');

        $this->service()->accept($token, $this->john->id);

        $this->assertTrue($this->isMember($this->john));
    }

    public function test_accept_uses_the_account_when_the_inviter_is_gone(): void
    {
        $this->app->make(CampaignRosterServiceInterface::class)
            ->requestToJoin($this->campaign->id, $this->john->id, null);
        $token = $this->tokenFor('john@example.com');
        CampaignInvitationModel::query()->update(['invited_by_user_id' => null]);

        $this->service()->accept($token, $this->john->id);

        $this->assertTrue($this->isMember($this->john));
        $request = CampaignJoinRequestModel::query()->where('user_id', $this->john->id)->firstOrFail();
        $this->assertSame('accepted', $request->status->value);
        $this->assertSame($this->john->id, $request->decided_by);
    }

    public function test_reject_deletes_and_records(): void
    {
        $token = $this->tokenFor('john@example.com');

        $this->service()->reject($token);

        $this->assertNull($this->invitations()->findPending($this->campaignId, 'john@example.com'));
        $this->assertTrue($this->sendLog()->rejectedSince($this->campaign->id, 'john@example.com', CarbonImmutable::now()->subDay()->toDateTimeImmutable()));
        $this->assertRefused('invitation_not_found', fn () => $this->service()->reject($token));
        $this->assertFalse($this->isMember($this->john));
    }

    public function test_a_failed_deletion_records_no_rejection(): void
    {
        $token = $this->tokenFor('john@example.com');
        $inner = $this->invitations();
        $this->app->instance(CampaignInvitationRepositoryInterface::class, new readonly class($inner) implements CampaignInvitationRepositoryInterface
        {
            public function __construct(private CampaignInvitationRepositoryInterface $inner) {}

            public function save(CampaignInvitation $invitation): void
            {
                $this->inner->save($invitation);
            }

            public function find(CampaignInvitationId $id): ?CampaignInvitation
            {
                return $this->inner->find($id);
            }

            public function findByTokenHash(string $hash): ?CampaignInvitation
            {
                return $this->inner->findByTokenHash($hash);
            }

            public function findPending(CampaignId $campaignId, string $email): ?CampaignInvitation
            {
                return $this->inner->findPending($campaignId, $email);
            }

            public function pendingFor(CampaignId $campaignId): array
            {
                return $this->inner->pendingFor($campaignId);
            }

            public function pendingForEmail(string $email, DateTimeImmutable $now): array
            {
                return $this->inner->pendingForEmail($email, $now);
            }

            public function delete(CampaignInvitationId $id): void
            {
                throw new RuntimeException('The database went away');
            }

            public function deleteExpired(DateTimeImmutable $now): int
            {
                return $this->inner->deleteExpired($now);
            }

            public function deleteForCampaignsNotOpen(): int
            {
                return $this->inner->deleteForCampaignsNotOpen();
            }
        });

        try {
            $this->service()->reject($token);
            $this->fail('Expected the deletion to fail');
        } catch (RuntimeException) {
        }

        $this->assertSame(0, $this->rejectionRows());
        $this->assertNotNull($inner->findPending($this->campaignId, 'john@example.com'));
    }

    public function test_a_failed_rejection_record_keeps_the_invitation(): void
    {
        $token = $this->tokenFor('john@example.com');
        // Writes the row, then fails: the row must not survive either
        $this->app->instance(CampaignInvitationSendLogInterface::class, new readonly class(new DatabaseCampaignInvitationSendLog) implements CampaignInvitationSendLogInterface
        {
            public function __construct(private CampaignInvitationSendLogInterface $inner) {}

            public function recordSend(string $campaignId, string $email, DateTimeImmutable $at): void
            {
                $this->inner->recordSend($campaignId, $email, $at);
            }

            public function recordRejection(string $campaignId, string $email, DateTimeImmutable $at): void
            {
                $this->inner->recordRejection($campaignId, $email, $at);

                throw new RuntimeException('The database went away');
            }

            public function sendsSince(string $campaignId, string $email, DateTimeImmutable $since): int
            {
                return $this->inner->sendsSince($campaignId, $email, $since);
            }

            public function lastSendAt(string $campaignId, string $email): ?DateTimeImmutable
            {
                return $this->inner->lastSendAt($campaignId, $email);
            }

            public function rejectedSince(string $campaignId, string $email, DateTimeImmutable $since): bool
            {
                return $this->inner->rejectedSince($campaignId, $email, $since);
            }

            public function pruneBefore(DateTimeImmutable $before): int
            {
                return $this->inner->pruneBefore($before);
            }
        });

        try {
            $this->service()->reject($token);
            $this->fail('Expected the rejection record to fail');
        } catch (RuntimeException) {
        }

        $this->assertSame(0, $this->rejectionRows());
        $this->assertNotNull($this->invitations()->findPending($this->campaignId, 'john@example.com'));
    }

    private function rejectionRows(): int
    {
        return DB::table('gametables_campaign_invitation_sends')->where('kind', 'rejected')->count();
    }

    public function test_accept_by_id_adds_a_verified_account(): void
    {
        $this->tokenFor('john@example.com');
        $id = $this->pending('john@example.com')->id->value;

        $this->service()->acceptById($id, $this->john->id);

        $this->assertTrue($this->isMember($this->john));
        $this->assertNull($this->invitations()->find(new CampaignInvitationId($id)));
    }

    public function test_accept_by_id_requires_a_verified_email(): void
    {
        $this->john->forceFill(['email_verified_at' => null])->save();
        $this->tokenFor('john@example.com');
        $id = $this->pending('john@example.com')->id->value;

        $this->assertRefused('email_not_verified', fn () => $this->service()->acceptById($id, $this->john->id));

        $this->assertNotNull($this->invitations()->findPending($this->campaignId, 'john@example.com'));
    }

    public function test_accept_by_id_for_another_email_answers_not_found(): void
    {
        $this->tokenFor('john@example.com');
        $id = $this->pending('john@example.com')->id->value;

        $this->assertRefused('invitation_not_found', fn () => $this->service()->acceptById($id, $this->jane->id));
        $this->assertRefused('invitation_not_found', fn () => $this->service()->acceptById('not-a-uuid', $this->jane->id));

        $this->assertNotNull($this->invitations()->findPending($this->campaignId, 'john@example.com'));
    }

    public function test_reject_by_id_applies_the_same_ownership_rule(): void
    {
        $this->tokenFor('john@example.com');
        $id = $this->pending('john@example.com')->id->value;

        $this->assertRefused('invitation_not_found', fn () => $this->service()->rejectById($id, $this->jane->id));
        $this->john->forceFill(['email_verified_at' => null])->save();
        $this->assertRefused('email_not_verified', fn () => $this->service()->rejectById($id, $this->john->id));
        $this->assertNotNull($this->invitations()->findPending($this->campaignId, 'john@example.com'));

        $this->john->forceFill(['email_verified_at' => now()])->save();
        $this->service()->rejectById($id, $this->john->id);

        $this->assertNull($this->invitations()->findPending($this->campaignId, 'john@example.com'));
        $this->assertTrue($this->sendLog()->rejectedSince($this->campaign->id, 'john@example.com', CarbonImmutable::now()->subDay()->toDateTimeImmutable()));
    }

    public function test_view_by_token_shows_the_live_invitation_only(): void
    {
        $token = $this->tokenFor('john@example.com');
        $now = CarbonImmutable::now()->toDateTimeImmutable();

        $view = $this->service()->viewByToken($token, $now);

        $this->assertNotNull($view);
        $this->assertSame('john@example.com', $view->email);
        $this->assertSame('La cripta olvidada', $view->campaignTitle);
        $this->assertSame('Mary Major', $view->inviterName);

        $this->assertNull($this->service()->viewByToken(Str::random(64), $now));
        $this->assertNull($this->service()->viewByToken($token, CarbonImmutable::now()->addDays(15)->toDateTimeImmutable()));
    }
}
