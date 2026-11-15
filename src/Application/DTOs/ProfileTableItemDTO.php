<?php

declare(strict_types=1);

namespace Modules\GameTables\Application\DTOs;

use DateTimeImmutable;

/**
 * A table on a public profile: what its public page already shows, picked one by one.
 */
final readonly class ProfileTableItemDTO
{
    public function __construct(
        public string $id,
        public string $title,
        public string $url,
        public DateTimeImmutable $startsAt,
        public string $gameSystemName,
    ) {}
}
