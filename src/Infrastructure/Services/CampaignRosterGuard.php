<?php

declare(strict_types=1);

namespace Modules\GameTables\Infrastructure\Services;

use Modules\GameTables\Domain\Entities\Campaign;
use Modules\GameTables\Domain\Exceptions\RosterActionRefusedException;
use Modules\GameTables\Domain\Repositories\CampaignRosterRepositoryInterface;

final readonly class CampaignRosterGuard
{
    public function __construct(
        private CampaignRosterRepositoryInterface $roster,
    ) {}

    public function ensureCanChange(Campaign $campaign): void
    {
        if (! $campaign->isApprovedOrPublished()) {
            throw RosterActionRefusedException::because('unavailable');
        }

        if ($campaign->isFinished()) {
            throw RosterActionRefusedException::because('finished');
        }
    }

    /**
     * Counted under the campaign lock, so two changes never take the last place.
     */
    public function ensureRoomIn(Campaign $campaign): void
    {
        if (! $campaign->hasRoomFor($this->roster->count($campaign->id))) {
            throw RosterActionRefusedException::because('roster_full');
        }
    }
}
