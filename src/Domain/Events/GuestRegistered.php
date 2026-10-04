<?php

declare(strict_types=1);

namespace Modules\GameTables\Domain\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\GameTables\Domain\Enums\ParticipantStatus;

final class GuestRegistered
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly string $participantId,
        public readonly string $gameTableId,
        public readonly string $email,
        public readonly string $firstName,
        public readonly string $role,
        public readonly string $cancellationToken,
        public readonly ParticipantStatus $status,
        public readonly ?int $waitingListPosition = null,
    ) {}
}
