<?php

declare(strict_types=1);

namespace Modules\GameTables\Application\DTOs;

use DateTimeInterface;
use Modules\GameTables\Domain\Entities\Participant;
use Modules\GameTables\Domain\Enums\GuestConfirmationMode;
use Modules\GameTables\Domain\Enums\ParticipantRole;
use Modules\GameTables\Domain\Enums\ParticipantStatus;

final readonly class ParticipantResponseDTO
{
    public function __construct(
        public string $id,
        public string $gameTableId,
        public ?string $userId,
        public string $userName,
        public ParticipantRole $role,
        public ParticipantStatus $status,
        public ?int $waitingListPosition,
        public ?string $notes,
        public ?DateTimeInterface $confirmedAt,
        public ?DateTimeInterface $createdAt,
        public bool $isGuest = false,
        public ?string $firstName = null,
        public ?string $email = null,
        // The place among those waiting for the same role (1-based); the stored position may have gaps
        public ?int $waitingListRank = null,
        // A guest whose email is not confirmed yet: hold (a seat until the deadline) or wait (none)
        public bool $awaitingEmailConfirmation = false,
        public ?GuestConfirmationMode $confirmationMode = null,
        public ?DateTimeInterface $confirmationExpiresAt = null,
        // The guest sent the form again while unconfirmed: the registration is the one they already had
        public bool $confirmationResent = false,
        // A signed-in user took over a guest registration made with their email
        public bool $linkedFromGuest = false,
    ) {}

    public static function fromEntity(
        Participant $participant,
        string $userName = '',
        ?int $waitingListRank = null,
        bool $confirmationResent = false,
        bool $linkedFromGuest = false,
    ): self {
        $displayName = $userName;
        if ($participant->isGuest()) {
            $displayName = $participant->getDisplayName();
        }

        return new self(
            id: $participant->id->value,
            gameTableId: $participant->gameTableId->value,
            userId: $participant->userId,
            userName: $displayName,
            role: $participant->role,
            status: $participant->status,
            waitingListPosition: $participant->waitingListPosition,
            notes: $participant->notes,
            confirmedAt: $participant->confirmedAt,
            createdAt: $participant->createdAt,
            isGuest: $participant->isGuest(),
            firstName: $participant->firstName,
            email: $participant->email,
            waitingListRank: $waitingListRank,
            awaitingEmailConfirmation: $participant->isAwaitingEmailConfirmation(),
            confirmationMode: $participant->confirmationMode,
            confirmationExpiresAt: $participant->confirmationExpiresAt,
            confirmationResent: $confirmationResent,
            linkedFromGuest: $linkedFromGuest,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'game_table_id' => $this->gameTableId,
            'user_id' => $this->userId,
            'user_name' => $this->userName,
            'role' => $this->role->value,
            'role_label' => $this->role->label(),
            'role_color' => $this->role->color(),
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'status_color' => $this->status->color(),
            'waiting_list_position' => $this->waitingListPosition,
            'waiting_list_rank' => $this->waitingListRank,
            'notes' => $this->notes,
            'confirmed_at' => $this->confirmedAt?->format('c'),
            'created_at' => $this->createdAt?->format('c'),
            'is_guest' => $this->isGuest,
            'first_name' => $this->firstName,
            'email' => $this->email,
            'awaiting_email_confirmation' => $this->awaitingEmailConfirmation,
            'confirmation_expires_at' => $this->confirmationExpiresAt?->format('c'),
        ];
    }
}
