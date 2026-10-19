<?php

declare(strict_types=1);

namespace Modules\GameTables\Domain\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * An unconfirmed guest registration lapsed. Only the guest may be told, and only when it held a
 * place until a deadline; the organisation never heard of it and hears nothing now.
 */
final class GuestRegistrationExpired
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly string $participantId,
        public readonly string $gameTableId,
        public readonly string $email,
        public readonly string $firstName,
        // Whether the guest gets the expiry email
        public readonly bool $notifyGuest,
    ) {}
}
