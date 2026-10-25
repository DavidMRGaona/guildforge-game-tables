<?php

declare(strict_types=1);

namespace Modules\GameTables\Domain\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\GameTables\Domain\Enums\ParticipantStatus;

final readonly class ParticipantRegistered
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public string $participantId,
        public string $gameTableId,
        public ?string $userId,
        public string $role,
        public ParticipantStatus $status,
        // The place among those waiting for the same role (1, 2…), not the stored position, which
        // may have gaps; the name stays so queued events keep working across the update
        public ?int $waitingListPosition = null,
        // False when the organisation itself restored the registration: the directors are not
        // told, the participant still gets their confirmation
        public bool $notifyOrganization = true,
    ) {}
}
