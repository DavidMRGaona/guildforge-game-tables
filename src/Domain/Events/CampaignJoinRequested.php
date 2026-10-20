<?php

declare(strict_types=1);

namespace Modules\GameTables\Domain\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A player asked to join the roster of a campaign.
 */
final class CampaignJoinRequested
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly string $requestId,
        public readonly string $campaignId,
        public readonly string $userId,
    ) {}
}
