<?php

declare(strict_types=1);

namespace Modules\GameTables\Domain\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * The direction accepted a request: the player is in the roster and was placed in the open
 * sessions listed in $sessions (with a seat, or on the waiting list).
 */
final readonly class CampaignJoinRequestAccepted
{
    use Dispatchable;
    use SerializesModels;

    /**
     * @param  list<array{game_table_id: string, waiting: bool}>  $sessions
     */
    public function __construct(
        public string $requestId,
        public string $campaignId,
        public string $userId,
        public array $sessions,
    ) {}
}
