<?php

declare(strict_types=1);

namespace Modules\GameTables\Domain\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * The direction rejected a request to join a campaign, optionally saying why.
 */
final class CampaignJoinRequestRejected
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly string $requestId,
        public readonly string $campaignId,
        public readonly string $userId,
        public readonly ?string $reason,
    ) {}
}
