<?php

declare(strict_types=1);

namespace Modules\GameTables\Application\Services;

use Modules\GameTables\Application\DTOs\TableRegistrationRowDTO;
use Modules\GameTables\Application\DTOs\TableRegistrationsPageDTO;

/**
 * What a table's director sees of its registrations, players and spectators only. Who may see it
 * is the policy's call (GameTablePolicy::manageRegistrations).
 */
interface TableRegistrationsQueryInterface
{
    public function page(string $gameTableId): TableRegistrationsPageDTO;

    /**
     * One registration of the table; null when the id is another table's, unknown or not a player
     * or spectator, so one table's director never acts on another table.
     */
    public function row(string $gameTableId, string $participantId): ?TableRegistrationRowDTO;
}
