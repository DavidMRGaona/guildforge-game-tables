<?php

declare(strict_types=1);

namespace Modules\GameTables\Domain\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Modules\GameTables\Domain\Entities\Campaign;

final readonly class CampaignCreated
{
    use Dispatchable;

    public function __construct(
        public Campaign $campaign,
    ) {
    }
}
