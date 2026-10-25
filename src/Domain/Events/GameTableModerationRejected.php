<?php

declare(strict_types=1);

namespace Modules\GameTables\Domain\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final readonly class GameTableModerationRejected
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public string $gameTableId,
        public string $title,
        public string $createdBy,
        public string $reason,
    ) {}
}
