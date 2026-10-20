<?php

declare(strict_types=1);

namespace Modules\GameTables\Domain\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A player left the roster of their own accord: their seats in the upcoming sessions were
 * already given up. Nothing is sent to the player.
 */
final class CampaignRosterMemberLeft
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly string $campaignId,
        public readonly string $userId,
    ) {}
}
