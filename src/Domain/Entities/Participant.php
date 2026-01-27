<?php

declare(strict_types=1);

namespace Modules\GameTables\Domain\Entities;

use DateTimeImmutable;
use Modules\GameTables\Domain\Enums\ParticipantRole;
use Modules\GameTables\Domain\Enums\ParticipantStatus;
use Modules\GameTables\Domain\ValueObjects\GameTableId;
use Modules\GameTables\Domain\ValueObjects\ParticipantId;

final class Participant
{
    public function __construct(
        public readonly ParticipantId $id,
        public readonly GameTableId $gameTableId,
        public readonly ?string $userId,
        public ParticipantRole $role,
        public ParticipantStatus $status,
        public ?int $waitingListPosition = null,
        public ?string $notes = null,
        public ?DateTimeImmutable $confirmedAt = null,
        public ?DateTimeImmutable $cancelledAt = null,
        public ?DateTimeImmutable $createdAt = null,
        public ?string $firstName = null,
        public ?string $lastName = null,
        public ?string $email = null,
        public ?string $phone = null,
        public ?string $cancellationToken = null,
    ) {}

    public function confirm(): void
    {
        $this->status = ParticipantStatus::Confirmed;
        $this->confirmedAt = new DateTimeImmutable();
    }

    public function cancel(): void
    {
        $this->status = ParticipantStatus::Cancelled;
        $this->cancelledAt = new DateTimeImmutable();
    }

    public function reject(): void
    {
        $this->status = ParticipantStatus::Rejected;
    }

    public function addToWaitingList(int $position): void
    {
        $this->status = ParticipantStatus::WaitingList;
        $this->waitingListPosition = $position;
    }

    public function promoteFromWaitingList(): void
    {
        $this->status = ParticipantStatus::Confirmed;
        $this->waitingListPosition = null;
        $this->confirmedAt = new DateTimeImmutable();
    }

    public function markAsNoShow(): void
    {
        $this->status = ParticipantStatus::NoShow;
    }

    public function updateNotes(?string $notes): void
    {
        $this->notes = $notes;
    }

    public function changeRole(ParticipantRole $role): void
    {
        $this->role = $role;
    }

    public function isGameMaster(): bool
    {
        return $this->role === ParticipantRole::GameMaster || $this->role === ParticipantRole::CoGm;
    }

    public function isPlayer(): bool
    {
        return $this->role === ParticipantRole::Player;
    }

    public function isSpectator(): bool
    {
        return $this->role === ParticipantRole::Spectator;
    }

    public function isPending(): bool
    {
        return $this->status === ParticipantStatus::Pending;
    }

    public function isConfirmed(): bool
    {
        return $this->status === ParticipantStatus::Confirmed;
    }

    public function isCancelled(): bool
    {
        return $this->status === ParticipantStatus::Cancelled;
    }

    public function isRejected(): bool
    {
        return $this->status === ParticipantStatus::Rejected;
    }

    public function isOnWaitingList(): bool
    {
        return $this->status === ParticipantStatus::WaitingList;
    }

    public function isNoShow(): bool
    {
        return $this->status === ParticipantStatus::NoShow;
    }

    public function isActive(): bool
    {
        return $this->status->isActive();
    }

    public function canBeCancelled(): bool
    {
        return $this->status->canBeCancelled();
    }

    public function isGuest(): bool
    {
        return $this->userId === null;
    }

    public function getDisplayName(): string
    {
        if ($this->firstName !== null) {
            return trim($this->firstName . ' ' . ($this->lastName ?? ''));
        }

        return '';
    }

    public function setCancellationToken(string $token): void
    {
        $this->cancellationToken = $token;
    }
}
