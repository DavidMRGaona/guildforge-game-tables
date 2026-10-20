<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Integration\Infrastructure\Persistence\Eloquent\Repositories;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use DateTimeImmutable;
use Illuminate\Support\Str;
use Modules\GameTables\Domain\Entities\CampaignJoinRequest;
use Modules\GameTables\Domain\Enums\JoinRequestStatus;
use Modules\GameTables\Domain\Exceptions\RosterActionRefusedException;
use Modules\GameTables\Domain\Repositories\CampaignJoinRequestRepositoryInterface;
use Modules\GameTables\Domain\ValueObjects\CampaignId;
use Modules\GameTables\Domain\ValueObjects\JoinRequestId;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\CampaignModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameSystemModel;
use Tests\Support\Modules\ModuleTestCase;

final class CampaignJoinRequestRepositoryTest extends ModuleTestCase
{
    protected ?string $moduleName = 'game-tables';

    protected bool $autoEnableModule = true;

    private CampaignJoinRequestRepositoryInterface $requests;

    private CampaignId $campaignId;

    private DateTimeImmutable $now;

    protected function setUp(): void
    {
        parent::setUp();

        $this->requests = $this->app->make(CampaignJoinRequestRepositoryInterface::class);
        $this->now = new DateTimeImmutable('2026-10-21 10:00:00');
        $this->campaignId = new CampaignId($this->makeCampaign()->id);
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

    private function request(string $userId, ?CampaignId $campaignId = null, JoinRequestStatus $status = JoinRequestStatus::Pending): CampaignJoinRequest
    {
        return new CampaignJoinRequest(
            id: JoinRequestId::generate(),
            campaignId: $campaignId ?? $this->campaignId,
            userId: $userId,
            status: $status,
            message: 'Hola',
        );
    }

    public function test_it_round_trips_a_request(): void
    {
        $john = UserModel::factory()->create();
        $jane = UserModel::factory()->create();
        $request = $this->request($john->id);
        $this->requests->save($request);

        $request->reject($jane->id, 'Completa', $this->now);
        $this->requests->save($request);

        $found = $this->requests->find($request->id);
        $this->assertNotNull($found);
        $this->assertSame(JoinRequestStatus::Rejected, $found->status);
        $this->assertSame('Hola', $found->message);
        $this->assertSame('Completa', $found->decisionReason);
        $this->assertSame($jane->id, $found->decidedBy);
        $this->assertNotNull($found->decidedAt);
        $this->assertNotNull($found->createdAt);
        $this->assertNull($this->requests->find(JoinRequestId::generate()));
    }

    public function test_a_second_pending_request_is_refused_by_the_index(): void
    {
        $john = UserModel::factory()->create();
        $this->requests->save($this->request($john->id));

        try {
            $this->requests->save($this->request($john->id));
            $this->fail('Expected a refusal');
        } catch (RosterActionRefusedException $e) {
            $this->assertSame('request_pending', $e->reason);
            $this->assertSame('Ya tienes una solicitud pendiente.', $e->getMessage());
        }
    }

    public function test_a_rejected_request_does_not_block_a_new_pending_one(): void
    {
        $john = UserModel::factory()->create();
        $this->requests->save($this->request($john->id, status: JoinRequestStatus::Rejected));

        $this->requests->save($this->request($john->id));

        $this->assertNotNull($this->requests->findPending($this->campaignId, $john->id));
    }

    public function test_find_pending_ignores_other_users_and_decided_requests(): void
    {
        $john = UserModel::factory()->create();
        $jane = UserModel::factory()->create();
        $this->requests->save($this->request($john->id, status: JoinRequestStatus::Withdrawn));

        $this->assertNull($this->requests->findPending($this->campaignId, $john->id));
        $this->assertNull($this->requests->findPending($this->campaignId, $jane->id));
    }

    public function test_pending_for_is_oldest_first_and_hides_anonymized_requesters(): void
    {
        $john = UserModel::factory()->create();
        $jane = UserModel::factory()->create();
        $richard = UserModel::factory()->create();
        $first = $this->request($jane->id);
        $second = $this->request($john->id);
        $hidden = $this->request($richard->id);
        $this->requests->save($first);
        $this->requests->save($second);
        $this->requests->save($hidden);
        \DB::table('gametables_campaign_join_requests')->where('id', $first->id->value)->update(['created_at' => '2026-10-20 10:00:00']);
        \DB::table('gametables_campaign_join_requests')->where('id', $second->id->value)->update(['created_at' => '2026-10-20 11:00:00']);
        $richard->forceFill(['anonymized_at' => $this->now])->save();

        $pending = $this->requests->pendingFor($this->campaignId);

        $this->assertSame([$first->id->value, $second->id->value], array_map(fn (CampaignJoinRequest $r): string => $r->id->value, $pending));
    }

    public function test_pending_counts_for(): void
    {
        $other = new CampaignId($this->makeCampaign()->id);
        $empty = new CampaignId($this->makeCampaign()->id);
        $john = UserModel::factory()->create();
        $jane = UserModel::factory()->create();
        $ghost = UserModel::factory()->create(['anonymized_at' => $this->now]);
        $this->requests->save($this->request($john->id));
        $this->requests->save($this->request($jane->id));
        $this->requests->save($this->request($ghost->id));
        $this->requests->save($this->request($john->id, $other));
        $this->requests->save($this->request($jane->id, $other, JoinRequestStatus::Accepted));

        $this->assertSame(
            [$this->campaignId->value => 2, $other->value => 1, $empty->value => 0],
            $this->requests->pendingCountsFor([$this->campaignId, $other, $empty]),
        );
    }
}
