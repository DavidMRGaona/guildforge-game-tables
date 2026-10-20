<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Unit\Domain\Entities;

use DateTimeImmutable;
use Modules\GameTables\Domain\Entities\Campaign;
use Modules\GameTables\Domain\Enums\CampaignFrequency;
use Modules\GameTables\Domain\Enums\CampaignStatus;
use Modules\GameTables\Domain\Enums\FrontendCreationStatus;
use Modules\GameTables\Domain\Enums\RosterAccess;
use Modules\GameTables\Domain\Exceptions\RosterActionRefusedException;
use Modules\GameTables\Domain\ValueObjects\CampaignId;
use Modules\GameTables\Domain\ValueObjects\GameSystemId;
use Tests\Support\Modules\ModuleTestCase;

final class CampaignTest extends ModuleTestCase
{
    protected ?string $moduleName = 'game-tables';

    protected bool $autoEnableModule = true;

    public function test_it_creates_campaign_with_required_data(): void
    {
        $id = CampaignId::generate();
        $gameSystemId = GameSystemId::generate();
        $createdById = 'user-uuid-123';

        $campaign = new Campaign(
            id: $id,
            gameSystemId: $gameSystemId,
            createdBy: $createdById,
            title: 'Curse of Strahd',
            slug: 'curse-of-strahd',
            status: CampaignStatus::Recruiting,
        );

        $this->assertInstanceOf(Campaign::class, $campaign);
        $this->assertTrue($id->equals($campaign->id));
        $this->assertTrue($gameSystemId->equals($campaign->gameSystemId));
        $this->assertEquals('user-uuid-123', $campaign->createdBy);
        $this->assertEquals('Curse of Strahd', $campaign->title);
        $this->assertEquals(CampaignStatus::Recruiting, $campaign->status);
        $this->assertNull($campaign->description);
        $this->assertNull($campaign->frequency);
        $this->assertNull($campaign->sessionCount);
        $this->assertEquals(0, $campaign->currentSession);
        $this->assertTrue($campaign->acceptsNewPlayers);
    }

    public function test_it_creates_campaign_with_all_data(): void
    {
        $id = CampaignId::generate();
        $gameSystemId = GameSystemId::generate();
        $createdAt = new DateTimeImmutable('2026-01-01 10:00:00');

        $campaign = new Campaign(
            id: $id,
            gameSystemId: $gameSystemId,
            createdBy: 'user-uuid-123',
            title: 'Waterdeep: Dragon Heist',
            slug: 'waterdeep-dragon-heist',
            status: CampaignStatus::Active,
            description: 'Una aventura urbana en la Ciudad de los Esplendores',
            frequency: CampaignFrequency::Weekly,
            sessionCount: 12,
            currentSession: 3,
            acceptsNewPlayers: false,
            createdAt: $createdAt,
        );

        $this->assertEquals('Waterdeep: Dragon Heist', $campaign->title);
        $this->assertEquals('Una aventura urbana en la Ciudad de los Esplendores', $campaign->description);
        $this->assertEquals(CampaignFrequency::Weekly, $campaign->frequency);
        $this->assertEquals(12, $campaign->sessionCount);
        $this->assertEquals(3, $campaign->currentSession);
        $this->assertFalse($campaign->acceptsNewPlayers);
        $this->assertEquals($createdAt, $campaign->createdAt);
    }

    public function test_it_can_update_info(): void
    {
        $campaign = $this->createCampaign();

        $campaign->updateInfo(
            title: 'Updated Title',
            description: 'Updated description',
            frequency: CampaignFrequency::Biweekly,
            sessionCount: 20,
        );

        $this->assertEquals('Updated Title', $campaign->title);
        $this->assertEquals('Updated description', $campaign->description);
        $this->assertEquals(CampaignFrequency::Biweekly, $campaign->frequency);
        $this->assertEquals(20, $campaign->sessionCount);
    }

    public function test_it_can_change_status(): void
    {
        $campaign = $this->createCampaign(status: CampaignStatus::Recruiting);

        $campaign->changeStatus(CampaignStatus::Active);

        $this->assertEquals(CampaignStatus::Active, $campaign->status);
    }

    public function test_it_can_start(): void
    {
        $campaign = $this->createCampaign(status: CampaignStatus::Recruiting);

        $campaign->start();

        $this->assertEquals(CampaignStatus::Active, $campaign->status);
    }

    public function test_it_can_put_on_hold(): void
    {
        $campaign = $this->createCampaign(status: CampaignStatus::Active);

        $campaign->putOnHold();

        $this->assertEquals(CampaignStatus::OnHold, $campaign->status);
    }

    public function test_it_can_resume(): void
    {
        $campaign = $this->createCampaign(status: CampaignStatus::OnHold);

        $campaign->resume();

        $this->assertEquals(CampaignStatus::Active, $campaign->status);
    }

    public function test_it_can_complete(): void
    {
        $campaign = $this->createCampaign(status: CampaignStatus::Active);

        $campaign->complete();

        $this->assertEquals(CampaignStatus::Completed, $campaign->status);
    }

    public function test_it_can_cancel(): void
    {
        $campaign = $this->createCampaign(status: CampaignStatus::Active);

        $campaign->cancel();

        $this->assertEquals(CampaignStatus::Cancelled, $campaign->status);
    }

    public function test_it_can_advance_session(): void
    {
        $campaign = $this->createCampaign();

        $campaign->advanceSession();

        $this->assertEquals(1, $campaign->currentSession);

        $campaign->advanceSession();

        $this->assertEquals(2, $campaign->currentSession);
    }

    public function test_it_can_open_recruitment(): void
    {
        $campaign = $this->createCampaign(acceptsNewPlayers: false);

        $campaign->openRecruitment();

        $this->assertTrue($campaign->acceptsNewPlayers);
    }

    public function test_it_can_close_recruitment(): void
    {
        $campaign = $this->createCampaign(acceptsNewPlayers: true);

        $campaign->closeRecruitment();

        $this->assertFalse($campaign->acceptsNewPlayers);
    }

    public function test_it_checks_if_active(): void
    {
        $activeCampaign = $this->createCampaign(status: CampaignStatus::Active);
        $completedCampaign = $this->createCampaign(status: CampaignStatus::Completed);

        $this->assertTrue($activeCampaign->isActive());
        $this->assertFalse($completedCampaign->isActive());
    }

    public function test_it_checks_if_recruiting(): void
    {
        $recruitingCampaign = $this->createCampaign(status: CampaignStatus::Recruiting);
        $activeCampaign = $this->createCampaign(status: CampaignStatus::Active);

        $this->assertTrue($recruitingCampaign->isRecruiting());
        $this->assertFalse($activeCampaign->isRecruiting());
    }

    public function test_it_calculates_progress(): void
    {
        $campaign = new Campaign(
            id: CampaignId::generate(),
            gameSystemId: GameSystemId::generate(),
            createdBy: 'user-uuid-123',
            title: 'Test Campaign',
            slug: 'test-campaign',
            status: CampaignStatus::Active,
            sessionCount: 10,
            currentSession: 5,
        );

        $this->assertEquals(50.0, $campaign->progressPercentage());
    }

    public function test_it_returns_null_progress_when_no_session_count(): void
    {
        $campaign = $this->createCampaign();

        $this->assertNull($campaign->progressPercentage());
    }

    public function test_the_author_may_change_it_only_while_it_is_an_unpublished_draft_or_rejected(): void
    {
        $campaign = $this->createCampaign();
        $campaign->frontendCreationStatus = FrontendCreationStatus::Draft;
        $this->assertTrue($campaign->canBeChangedByAuthor());

        $campaign->frontendCreationStatus = FrontendCreationStatus::Rejected;
        $this->assertTrue($campaign->canBeChangedByAuthor());

        $campaign->frontendCreationStatus = FrontendCreationStatus::PendingReview;
        $this->assertFalse($campaign->canBeChangedByAuthor());

        $campaign->frontendCreationStatus = null;
        $this->assertFalse($campaign->canBeChangedByAuthor());

        // Published from the panel while still a draft: the author no longer changes it
        $campaign->frontendCreationStatus = FrontendCreationStatus::Draft;
        $campaign->isPublished = true;
        $this->assertFalse($campaign->canBeChangedByAuthor());
    }

    // Rejecting sends it back to the author as a draft that keeps the reason, not to a separate status
    public function test_rejecting_returns_it_to_an_unpublished_draft_with_the_reason(): void
    {
        $campaign = $this->createCampaign();
        $campaign->frontendCreationStatus = FrontendCreationStatus::PendingReview;
        $campaign->isPublished = true;

        $campaign->rejectFrontendCreation('Falta la sinopsis');

        $this->assertSame(FrontendCreationStatus::Draft, $campaign->frontendCreationStatus);
        $this->assertSame('Falta la sinopsis', $campaign->moderationNotes);
        $this->assertFalse($campaign->isPublished);
        $this->assertSame('Falta la sinopsis', $campaign->rejectionReason());
        $this->assertTrue($campaign->canBeChangedByAuthor());
    }

    // The reason shows until the author sends it again: submitting clears it
    public function test_submitting_for_review_clears_the_rejection_reason(): void
    {
        $campaign = $this->createCampaign();
        $campaign->frontendCreationStatus = FrontendCreationStatus::PendingReview;
        $campaign->rejectFrontendCreation('Falta la sinopsis');

        $campaign->submitForModeration();

        $this->assertSame(FrontendCreationStatus::PendingReview, $campaign->frontendCreationStatus);
        $this->assertNull($campaign->moderationNotes);
        $this->assertNull($campaign->rejectionReason());
    }

    public function test_only_a_draft_the_author_may_change_has_a_rejection_reason(): void
    {
        $campaign = $this->createCampaign();
        $campaign->moderationNotes = 'Falta la sinopsis';

        $campaign->frontendCreationStatus = FrontendCreationStatus::Draft;
        $this->assertSame('Falta la sinopsis', $campaign->rejectionReason());

        // Rows rejected before 2.10.0, until the deploy converts them
        $campaign->frontendCreationStatus = FrontendCreationStatus::Rejected;
        $this->assertSame('Falta la sinopsis', $campaign->rejectionReason());

        // Approval notes are not a rejection
        $campaign->frontendCreationStatus = FrontendCreationStatus::Approved;
        $this->assertNull($campaign->rejectionReason());

        $campaign->frontendCreationStatus = FrontendCreationStatus::Draft;
        $campaign->moderationNotes = null;
        $this->assertNull($campaign->rejectionReason());
    }

    private function createCampaign(
        CampaignStatus $status = CampaignStatus::Recruiting,
        bool $acceptsNewPlayers = true,
    ): Campaign {
        return new Campaign(
            id: CampaignId::generate(),
            gameSystemId: GameSystemId::generate(),
            createdBy: 'user-uuid-123',
            title: 'Test Campaign',
            slug: 'test-campaign',
            status: $status,
            acceptsNewPlayers: $acceptsNewPlayers,
        );
    }

    private function rosterCampaign(
        CampaignStatus $status = CampaignStatus::Recruiting,
        bool $accepts = true,
        ?int $max = null,
        bool $published = false,
        ?FrontendCreationStatus $frontend = null,
        string $createdBy = 'user-uuid-123',
    ): Campaign {
        return new Campaign(
            id: CampaignId::generate(),
            gameSystemId: GameSystemId::generate(),
            createdBy: $createdBy,
            title: 'Curse of Strahd',
            slug: 'curse-of-strahd',
            status: $status,
            acceptsNewPlayers: $accepts,
            maxPlayers: $max,
            isPublished: $published,
            frontendCreationStatus: $frontend,
        );
    }

    public function test_an_active_campaign_accepting_players_is_open(): void
    {
        $this->assertTrue($this->rosterCampaign(CampaignStatus::Recruiting)->canAcceptNewPlayers());
        $this->assertTrue($this->rosterCampaign(CampaignStatus::Active)->canAcceptNewPlayers());
        $this->assertFalse($this->rosterCampaign(CampaignStatus::OnHold)->canAcceptNewPlayers());
        $this->assertFalse($this->rosterCampaign(CampaignStatus::Completed)->canAcceptNewPlayers());
        $this->assertFalse($this->rosterCampaign(CampaignStatus::Cancelled)->canAcceptNewPlayers());
        $this->assertFalse($this->rosterCampaign(CampaignStatus::Active, accepts: false)->canAcceptNewPlayers());
    }

    public function test_available_for_sessions_needs_approval_or_publication_and_not_finished(): void
    {
        $this->assertFalse($this->rosterCampaign(frontend: FrontendCreationStatus::Draft)->isAvailableForSessions());
        $this->assertFalse($this->rosterCampaign(frontend: FrontendCreationStatus::PendingReview)->isAvailableForSessions());
        $this->assertTrue($this->rosterCampaign(frontend: FrontendCreationStatus::Approved)->isAvailableForSessions());
        $this->assertTrue($this->rosterCampaign(published: true)->isAvailableForSessions());
        $this->assertFalse($this->rosterCampaign(CampaignStatus::Completed, published: true)->isAvailableForSessions());
        $this->assertTrue($this->rosterCampaign(CampaignStatus::Completed)->isFinished());
        $this->assertFalse($this->rosterCampaign(CampaignStatus::OnHold)->isFinished());
    }

    public function test_has_room_for(): void
    {
        $limited = $this->rosterCampaign(max: 5);

        $this->assertTrue($limited->hasRoomFor(4));
        $this->assertFalse($limited->hasRoomFor(5));
        $this->assertTrue($this->rosterCampaign()->hasRoomFor(500));
    }

    public function test_web_creator_directs_only_web_campaigns(): void
    {
        $web = $this->rosterCampaign(frontend: FrontendCreationStatus::Approved);
        $panel = $this->rosterCampaign();

        $this->assertTrue($web->isDirectedFromWebBy('user-uuid-123'));
        $this->assertFalse($web->isDirectedFromWebBy('other'));
        $this->assertFalse($panel->isDirectedFromWebBy('user-uuid-123'));
    }

    public function test_roster_settings_refuse_a_max_below_the_roster(): void
    {
        $campaign = $this->rosterCampaign();

        try {
            $campaign->changeRosterSettings(true, RosterAccess::MembersOnly, 3, 4);
            $this->fail('Expected a refusal');
        } catch (RosterActionRefusedException $e) {
            $this->assertSame('max_below_roster', $e->reason);
            $this->assertSame('El máximo de jugadores no puede ser menor que la plantilla actual (4).', $e->getMessage());
        }

        $campaign->changeRosterSettings(false, RosterAccess::MembersOnly, null, 4);
        $this->assertFalse($campaign->acceptsNewPlayers);
        $this->assertSame(RosterAccess::MembersOnly, $campaign->rosterAccess);
        $this->assertNull($campaign->maxPlayers);
    }
}
