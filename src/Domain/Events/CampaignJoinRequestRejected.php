<?php

declare(strict_types=1);

namespace Modules\GameTables\Domain\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * The direction rejected a request to join a campaign, optionally saying why.
 */
final readonly class CampaignJoinRequestRejected
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public string $requestId,
        public string $campaignId,
        public string $userId,
        public ?string $reason,
    ) {}
}
