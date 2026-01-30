<?php

declare(strict_types=1);

namespace Modules\GameTables\Application\DTOs;

use DateTimeImmutable;
use Modules\GameTables\Domain\Entities\GameSystem;
use Modules\GameTables\Domain\Entities\GameTable;
use Modules\GameTables\Domain\Entities\Participant;

final readonly class ProfileParticipationDTO
{
    public function __construct(
        public string $id,
        public string $gameTableId,
        public string $gameTableTitle,
        public string $gameTableSlug,
        public ?DateTimeImmutable $gameTableStartsAt,
        public string $gameSystemName,
        public string $role,
        public string $roleKey,
        public string $roleColor,
        public string $status,
        public string $statusKey,
        public string $statusColor,
        public ?int $waitingListPosition,
        public bool $isUpcoming,
    ) {}

    public static function fromParticipantWithTable(
        Participant $participant,
        GameTable $gameTable,
        GameSystem $gameSystem,
    ): self {
        $now = new DateTimeImmutable();
        $startsAt = $gameTable->timeSlot->startsAt;
        $isUpcoming = $startsAt !== null && $startsAt > $now;

        return new self(
            id: $participant->id->value,
            gameTableId: $gameTable->id->value,
            gameTableTitle: $gameTable->title,
            gameTableSlug: $gameTable->slug,
            gameTableStartsAt: $startsAt,
            gameSystemName: $gameSystem->name,
            role: $participant->role->label(),
            roleKey: $participant->role->value,
            roleColor: $participant->role->color(),
            status: $participant->status->label(),
            statusKey: $participant->status->value,
            statusColor: $participant->status->color(),
            waitingListPosition: $participant->waitingListPosition,
            isUpcoming: $isUpcoming,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'gameTableId' => $this->gameTableId,
            'gameTableTitle' => $this->gameTableTitle,
            'gameTableSlug' => $this->gameTableSlug,
            'gameTableStartsAt' => $this->gameTableStartsAt?->format('c'),
            'gameSystemName' => $this->gameSystemName,
            'role' => $this->role,
            'roleKey' => $this->roleKey,
            'roleColor' => $this->roleColor,
            'status' => $this->status,
            'statusKey' => $this->statusKey,
            'statusColor' => $this->statusColor,
            'waitingListPosition' => $this->waitingListPosition,
            'isUpcoming' => $this->isUpcoming,
        ];
    }
}
