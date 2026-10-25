<?php

declare(strict_types=1);

namespace Modules\GameTables\Domain\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final readonly class ParticipantCancelled
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public string $participantId,
        public string $gameTableId,
        public ?string $userId,
        public bool $wasConfirmed,
        // True when the organisation cancelled it from the panel: the participant gets its own
        // notice and the directors none
        public bool $byOrganization = false,
    ) {}
}
