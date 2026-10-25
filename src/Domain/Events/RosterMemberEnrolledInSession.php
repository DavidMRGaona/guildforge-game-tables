<?php

declare(strict_types=1);

namespace Modules\GameTables\Domain\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A roster member was placed in a campaign session when it was published: with a seat, or on the
 * waiting list ($waiting) when the session had no room left.
 */
final readonly class RosterMemberEnrolledInSession
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public string $participantId,
        public string $gameTableId,
        public string $userId,
        public bool $waiting,
    ) {}
}
