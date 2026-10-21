<?php

declare(strict_types=1);

namespace Modules\GameTables\Domain\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * An invitation was created or resent. $token is the plain token, only for the email: the
 * database keeps just its hash.
 */
final class CampaignInvitationSent
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly string $invitationId,
        public readonly string $campaignId,
        public readonly string $email,
        public readonly string $token,
    ) {}
}
