<?php

declare(strict_types=1);

namespace Modules\GameTables\Domain\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * The direction accepted a request: the player is in the roster and was placed in the open
 * sessions listed in $sessions (with a seat, or on the waiting list).
 */
final class CampaignJoinRequestAccepted
{
    use Dispatchable;
    use SerializesModels;

    /**
     * @param  list<array{game_table_id: string, waiting: bool}>  $sessions
     */
    public function __construct(
        public readonly string $requestId,
        public readonly string $campaignId,
        public readonly string $userId,
        public readonly array $sessions,
    ) {}
}
