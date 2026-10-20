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

    /**
     * The account with this id while it is active: anonymized, deleted and unknown accounts (or
     * an id that is not a UUID) give null.
     */
    public function findActiveById(string $userId): ?AccountDTO;
}
