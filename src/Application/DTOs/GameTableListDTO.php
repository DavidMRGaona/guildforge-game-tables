<?php

declare(strict_types=1);

namespace Modules\GameTables\Application\DTOs;

use DateTimeInterface;
use Modules\GameTables\Domain\Entities\GameTable;
use Modules\GameTables\Domain\Enums\TableFormat;
use Modules\GameTables\Domain\Enums\TableStatus;
use Modules\GameTables\Domain\Enums\TableType;

final readonly class GameTableListDTO
{
    public function __construct(
        public string $id,
        public string $title,
        public string $gameSystemName,
        public ?DateTimeInterface $startsAt,
        public int $durationMinutes,
        public TableFormat $tableFormat,
        public TableType $tableType,
        public TableStatus $status,
        public ?string $location,
        public ?string $onlineUrl,
        public int $minPlayers,
        public int $maxPlayers,
        public int $currentPlayers,
        public bool $isPublished,
        public string $creatorName,
        public string $mainGameMasterName,
        public ?string $eventId,
        public ?string $eventTitle,
    ) {}

    public static function fromEntity(
        GameTable $gameTable,
        int $confirmedPlayers = 0,
        string $gameSystemName = '',
        string $creatorName = '',
        string $mainGameMasterName = '',
        ?string $eventTitle = null,
    ): self {
        return new self(
            id: $gameTable->id->value,
            title: $gameTable->title,
            gameSystemName: $gameSystemName,
            startsAt: $gameTable->timeSlot->startsAt,
            durationMinutes: $gameTable->timeSlot->durationMinutes,
            tableFormat: $gameTable->tableFormat,
            tableType: $gameTable->tableType,
            status: $gameTable->status,
            location: $gameTable->location,
            onlineUrl: $gameTable->onlineUrl,
            minPlayers: $gameTable->minPlayers,
            maxPlayers: $gameTable->maxPlayers,
            currentPlayers: $confirmedPlayers,
            isPublished: $gameTable->isPublished,
            creatorName: $creatorName,
            mainGameMasterName: $mainGameMasterName !== '' ? $mainGameMasterName : $creatorName,
            eventId: $gameTable->eventId,
            eventTitle: $eventTitle,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'game_system_name' => $this->gameSystemName,
            'starts_at' => $this->startsAt?->format('c'),
            'duration_minutes' => $this->durationMinutes,
            'table_format' => $this->tableFormat->value,
            'table_format_label' => $this->tableFormat->label(),
            'table_format_color' => $this->tableFormat->color(),
            'table_type' => $this->tableType->value,
            'table_type_label' => $this->tableType->label(),
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'status_color' => $this->status->color(),
            'location' => $this->location,
            'online_url' => $this->onlineUrl,
            'min_players' => $this->minPlayers,
            'max_players' => $this->maxPlayers,
            'current_players' => $this->currentPlayers,
            'spots_available' => max(0, $this->maxPlayers - $this->currentPlayers),
            'is_full' => $this->currentPlayers >= $this->maxPlayers,
            'is_published' => $this->isPublished,
            'creator_name' => $this->creatorName,
            'main_game_master_name' => $this->mainGameMasterName,
            'event_id' => $this->eventId,
            'event_title' => $this->eventTitle,
        ];
    }
}
