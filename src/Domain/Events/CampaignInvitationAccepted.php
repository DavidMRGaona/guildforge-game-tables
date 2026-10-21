<?php

declare(strict_types=1);

namespace Modules\GameTables\Domain\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * An account joined the campaign through an invitation. The invitation is already deleted.
 */
final class CampaignInvitationAccepted
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly string $campaignId,
        public readonly string $userId,
    ) {}
}
