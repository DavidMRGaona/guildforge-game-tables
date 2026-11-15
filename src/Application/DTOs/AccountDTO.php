<?php

declare(strict_types=1);

namespace Modules\GameTables\Application\DTOs;

final readonly class AccountDTO
{
    /**
     * @param  string  $name  The account name, private: only for mail to the account itself
     * @param  string  $publicName  The name others may see: the account's public name, or its account name when it has none
     */
    public function __construct(
        public string $userId,
        public string $email,
        public bool $emailVerified,
        public string $name = '',
        public string $publicName = '',
    ) {}
}
