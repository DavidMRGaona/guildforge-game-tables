<?php

declare(strict_types=1);

namespace Modules\GameTables\Domain\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * The direction added a player to the roster directly: the player was placed in the open
 * sessions listed in $sessions (with a seat, or on the waiting list).
 */
final readonly class CampaignRosterMemberAdded
{
    use Dispatchable;
    use SerializesModels;

    /**
     * @param  list<array{game_table_id: string, waiting: bool}>  $sessions
     */
    public function __construct(
        public string $campaignId,
        public string $userId,
        public array $sessions,
    ) {}
}
