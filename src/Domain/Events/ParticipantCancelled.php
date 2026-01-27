<?php

declare(strict_types=1);

namespace Modules\GameTables\Domain\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class ParticipantCancelled
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly string $participantId,
        public readonly string $gameTableId,
        public readonly string $userId,
        public readonly bool $wasConfirmed,
    ) {}
}
