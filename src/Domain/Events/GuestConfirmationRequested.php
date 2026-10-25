<?php

declare(strict_types=1);

namespace Modules\GameTables\Domain\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A guest registration from the public form waits for the guest to confirm their email: the
 * confirmation link is (re)sent to them. Nobody else hears of the registration yet.
 */
final readonly class GuestConfirmationRequested
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public string $participantId,
        public string $gameTableId,
        public string $email,
        public string $firstName,
        public string $confirmationToken,
        // Whether the registration holds its place (seat or waiting list position) until the deadline
        public bool $holdsPlace,
        // The deadline as shown to the guest (d/m/Y H:i), only when the place is held
        public ?string $deadline,
    ) {}
}
