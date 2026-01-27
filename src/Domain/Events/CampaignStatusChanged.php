<?php

declare(strict_types=1);

namespace Modules\GameTables\Domain\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Modules\GameTables\Domain\Enums\CampaignStatus;
use Modules\GameTables\Domain\ValueObjects\CampaignId;

final readonly class CampaignStatusChanged
{
    use Dispatchable;

    public function __construct(
        public CampaignId $campaignId,
        public CampaignStatus $previousStatus,
        public CampaignStatus $newStatus,
    ) {
    }
}
