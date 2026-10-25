<?php

declare(strict_types=1);

namespace Modules\GameTables\Domain\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final readonly class ParticipantPromotedFromWaitingList
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public string $participantId,
        public string $gameTableId,
        public ?string $userId,
        // False on tables with manual confirmation: the promoted player waits as pending
        public bool $confirmed = true,
    ) {}
}
