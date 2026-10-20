<?php

declare(strict_types=1);

namespace Modules\GameTables\Domain\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * The direction removed a player from the roster: their seats in the upcoming sessions were
 * already given up.
 */
final class CampaignRosterMemberRemoved
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly string $campaignId,
        public readonly string $userId,
    ) {}
}
