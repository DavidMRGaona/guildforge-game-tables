<?php

declare(strict_types=1);

namespace Modules\GameTables\Application\DTOs;

/**
 * A session a roster member was placed in: with a seat, or on the waiting list.
 */
final readonly class EnrolledSessionDTO
{
    public function __construct(
        public string $gameTableId,
        public bool $waiting,
    ) {}

    /**
     * @return array{game_table_id: string, waiting: bool}
     */
    public function toArray(): array
    {
        return [
            'game_table_id' => $this->gameTableId,
            'waiting' => $this->waiting,
        ];
    }
}
