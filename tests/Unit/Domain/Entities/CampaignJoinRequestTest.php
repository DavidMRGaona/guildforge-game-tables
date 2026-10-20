<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Unit\Domain\Entities;

use DateTimeImmutable;
use Modules\GameTables\Domain\Entities\CampaignJoinRequest;
use Modules\GameTables\Domain\Enums\JoinRequestStatus;
use Modules\GameTables\Domain\Exceptions\RosterActionRefusedException;
use Modules\GameTables\Domain\ValueObjects\CampaignId;
use Modules\GameTables\Domain\ValueObjects\JoinRequestId;
use Tests\Support\Modules\ModuleTestCase;

final class CampaignJoinRequestTest extends ModuleTestCase
{
    protected ?string $moduleName = 'game-tables';

    protected bool $autoEnableModule = true;

    private function pending(): CampaignJoinRequest
    {
        return new CampaignJoinRequest(
            id: JoinRequestId::generate(),
            campaignId: CampaignId::generate(),
            userId: 'user-john',
            status: JoinRequestStatus::Pending,
            message: 'Me gustaría jugar',
        );
    }

    public function test_accept_sets_status_decider_and_date(): void
    {
        $request = $this->pending();
        $now = new DateTimeImmutable('2026-10-21 10:00:00');

        $request->accept('user-jane', $now);

        $this->assertSame(JoinRequestStatus::Accepted, $request->status);
        $this->assertSame('user-jane', $request->decidedBy);
        $this->assertSame($now, $request->decidedAt);
        $this->assertFalse($request->isPending());
    }

    public function test_reject_stores_the_reason_and_blank_becomes_null(): void
    {
        $request = $this->pending();
        $now = new DateTimeImmutable('2026-10-21 10:00:00');

        $request->reject('user-jane', 'Plantilla completa', $now);

        $this->assertSame(JoinRequestStatus::Rejected, $request->status);
        $this->assertSame('Plantilla completa', $request->decisionReason);
        $this->assertSame('user-jane', $request->decidedBy);

        $blank = $this->pending();
        $blank->reject('user-jane', '  ', $now);
        $this->assertNull($blank->decisionReason);
    }

    public function test_withdraw_sets_status_and_date(): void
    {
        $request = $this->pending();
        $now = new DateTimeImmutable('2026-10-21 10:00:00');

        $request->withdraw($now);

        $this->assertSame(JoinRequestStatus::Withdrawn, $request->status);
        $this->assertSame($now, $request->decidedAt);
    }

    public function test_a_second_decision_is_refused(): void
    {
        $now = new DateTimeImmutable('2026-10-21 10:00:00');
        $request = $this->pending();
        $request->accept(null, $now);

        foreach ([
            fn () => $request->accept(null, $now),
            fn () => $request->reject('user-jane', null, $now),
            fn () => $request->withdraw($now),
        ] as $decision) {
            try {
                $decision();
                $this->fail('Expected a refusal');
            } catch (RosterActionRefusedException $e) {
                $this->assertSame('not_pending', $e->reason);
                $this->assertSame('Esta solicitud ya no está pendiente.', $e->getMessage());
            }
        }
    }
}
