<?php

declare(strict_types=1);

namespace Modules\GameTables\Application\Services;

use Modules\GameTables\Application\DTOs\AccountDTO;

interface AccountLookupInterface
{
    /**
     * The active account with this email (compared without case or surrounding spaces); anonymized
     * and deleted accounts do not count.
     */
    public function findByEmail(string $email): ?AccountDTO;

    public function findById(string $userId): ?AccountDTO;
}
