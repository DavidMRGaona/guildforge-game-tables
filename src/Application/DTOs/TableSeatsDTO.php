<?php

declare(strict_types=1);

namespace Modules\GameTables\Application\DTOs;

/**
 * The seats of a table as the server counts them when it takes a registration.
 */
final readonly class TableSeatsDTO
{
    public function __construct(
        public int $takenPlayerSeats,
        public int $freePlayerSeats,
        public int $takenSpectatorSeats,
        public int $freeSpectatorSeats,
        public int $waitingPlayers,
    ) {}
}
