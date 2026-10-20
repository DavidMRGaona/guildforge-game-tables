<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Integration\Infrastructure\Persistence\Eloquent\Repositories;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use DateTimeImmutable;
use Illuminate\Support\Str;
use Modules\GameTables\Domain\Enums\RosterAccess;
use Modules\GameTables\Domain\Repositories\CampaignRepositoryInterface;
use Modules\GameTables\Domain\Repositories\CampaignRosterRepositoryInterface;
use Modules\GameTables\Domain\ValueObjects\CampaignId;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\CampaignModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameSystemModel;
use Tests\Support\Modules\ModuleTestCase;

final class CampaignRosterRepositoryTest extends ModuleTestCase
{
    protected ?string $moduleName = 'game-tables';

    protected bool $autoEnableModule = true;

    private CampaignRosterRepositoryInterface $roster;

    private CampaignId $campaignId;

    private CampaignId $otherCampaignId;

    private DateTimeImmutable $now;

    protected function setUp(): void
    {
        parent::setUp();

        $this->roster = $this->app->make(CampaignRosterRepositoryInterface::class);
        $this->now = new DateTimeImmutable('2026-10-21 10:00:00');
        $this->campaignId = new CampaignId($this->makeCampaign()->id);
        $this->otherCampaignId = new CampaignId($this->makeCampaign()->id);
    }

    private function makeCampaign(): CampaignModel
    {
        $system = GameSystemModel::create(['id' => (string) Str::uuid(), 'name' => 'Sistema '.Str::random(5), 'slug' => Str::random(10), 'is_active' => true]);

        return CampaignModel::create([
            'id' => (string) Str::uuid(),
            'game_system_id' => $system->id,
            'created_by' => UserModel::factory()->create()->id,
            'title' => 'Campaña',
            'slug' => Str::random(10),
            'status' => 'recruiting',
        ]);
    }

    public function test_add_is_idempotent(): void
    {
        $john = UserModel::factory()->create();

        $this->roster->add($this->campaignId, $john->id, $this->now);
        $this->roster->add($this->campaignId, $john->id, $this->now);

        $this->assertTrue($this->roster->contains($this->campaignId, $john->id));
        $this->assertSame(1, $this->roster->count($this->campaignId));
        $this->assertSame([$john->id], $this->roster->memberIds($this->campaignId));
    }

    public function test_remove_reports_whether_it_removed(): void
    {
        $john = UserModel::factory()->create();
        $this->roster->add($this->campaignId, $john->id, $this->now);

        $this->assertTrue($this->roster->remove($this->campaignId, $john->id));
        $this->assertFalse($this->roster->remove($this->campaignId, $john->id));
        $this->assertFalse($this->roster->contains($this->campaignId, $john->id));
    }

    public function test_member_ids_follow_the_joining_order(): void
    {
        $john = UserModel::factory()->create();
        $jane = UserModel::factory()->create();
        $this->roster->add($this->campaignId, $jane->id, new DateTimeImmutable('2026-10-21 11:00:00'));
        $this->roster->add($this->campaignId, $john->id, new DateTimeImmutable('2026-10-21 09:00:00'));

        $this->assertSame([$john->id, $jane->id], $this->roster->memberIds($this->campaignId));
    }

    public function test_anonymized_members_are_not_counted(): void
    {
        $john = UserModel::factory()->create();
        $jane = UserModel::factory()->create();
        $this->roster->add($this->campaignId, $john->id, $this->now);
        $this->roster->add($this->campaignId, $jane->id, $this->now);
        $this->roster->add($this->otherCampaignId, $jane->id, $this->now);

        $john->forceFill(['anonymized_at' => $this->now])->save();

        $this->assertFalse($this->roster->contains($this->campaignId, $john->id));
        $this->assertSame(1, $this->roster->count($this->campaignId));
        $this->assertSame([$jane->id], $this->roster->memberIds($this->campaignId));
        $this->assertSame(
            [$this->campaignId->value => 1, $this->otherCampaignId->value => 1],
            $this->roster->countsFor([$this->campaignId, $this->otherCampaignId]),
        );
    }

    public function test_counts_for_includes_empty_campaigns_as_zero(): void
    {
        $this->assertSame([$this->campaignId->value => 0], $this->roster->countsFor([$this->campaignId]));
    }

    public function test_the_campaign_repository_keeps_the_roster_access(): void
    {
        $campaigns = $this->app->make(CampaignRepositoryInterface::class);
        $campaign = $campaigns->findOrFail($this->campaignId);
        $this->assertSame(RosterAccess::Anyone, $campaign->rosterAccess);

        $campaign->changeRosterSettings(true, RosterAccess::MembersOnly, null, 0);
        $campaigns->save($campaign);

        $this->assertSame(RosterAccess::MembersOnly, $campaigns->findOrFail($this->campaignId)->rosterAccess);
    }
}
