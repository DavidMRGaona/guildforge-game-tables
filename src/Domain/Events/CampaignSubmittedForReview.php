<?php

declare(strict_types=1);

namespace Modules\GameTables\Domain\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class CampaignSubmittedForReview
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly string $campaignId,
        public readonly string $title,
        public readonly string $createdBy,
    ) {}
}
