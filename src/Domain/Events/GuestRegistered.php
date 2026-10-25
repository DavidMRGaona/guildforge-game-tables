<?php

declare(strict_types=1);

namespace Modules\GameTables\Domain\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\GameTables\Domain\Enums\ParticipantStatus;

final readonly class GuestRegistered
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public string $participantId,
        public string $gameTableId,
        public string $email,
        public string $firstName,
        public string $role,
        public string $cancellationToken,
        public ParticipantStatus $status,
        // The place among those waiting for the same role (1, 2…), not the stored position, which
        // may have gaps; the name stays so queued events keep working across the update
        public ?int $waitingListPosition = null,
        // False when the organisation itself acted from the panel: only the guest is told
        public bool $notifyOrganization = true,
    ) {}
}
