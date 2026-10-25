<?php

declare(strict_types=1);

namespace Modules\GameTables\Domain\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final readonly class CampaignModerationRejected
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public string $campaignId,
        public string $title,
        public string $createdBy,
        public string $reason,
    ) {}
}
