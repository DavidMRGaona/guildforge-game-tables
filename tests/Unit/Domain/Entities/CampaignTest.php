<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Unit\Domain\Entities;

use DateTimeImmutable;
use Modules\GameTables\Domain\Entities\Campaign;
use Modules\GameTables\Domain\Enums\CampaignFrequency;
use Modules\GameTables\Domain\Enums\CampaignStatus;
use Modules\GameTables\Domain\ValueObjects\CampaignId;
use Modules\GameTables\Domain\ValueObjects\GameSystemId;
use PHPUnit\Framework\TestCase;

final class CampaignTest extends TestCase
{
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
}
