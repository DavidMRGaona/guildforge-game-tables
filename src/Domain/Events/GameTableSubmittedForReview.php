<?php

declare(strict_types=1);

namespace Modules\GameTables\Domain\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class GameTableSubmittedForReview
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly string $gameTableId,
        public readonly string $title,
        public readonly string $createdBy,
    ) {}
}
