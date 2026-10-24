<?php

declare(strict_types=1);

namespace Modules\GameTables\Application\Services;

/**
 * Who directs a table: its effective game masters (local plus inherited, not excluded) that have an account.
 */
interface TableDirectionInterface
{
    public function isDirector(string $gameTableId, string $userId): bool;

    /**
     * Unique ids of the active accounts that direct the table.
     *
     * @return list<string>
     */
    public function directorUserIds(string $gameTableId): array;

    /**
     * @return list<string>
     */
    public function tableIdsDirectedBy(string $userId): array;
}
