<?php

declare(strict_types=1);

namespace Modules\GameTables\Application\DTOs;

final readonly class AccountDTO
{
    public function __construct(
        public string $userId,
        public string $email,
        public bool $emailVerified,
    ) {}
}
