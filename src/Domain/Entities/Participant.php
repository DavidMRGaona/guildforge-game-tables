<?php

declare(strict_types=1);

namespace Modules\GameTables\Domain\Entities;

use Carbon\CarbonImmutable;
use DateInterval;
use DateTimeImmutable;
use Modules\GameTables\Domain\Enums\GuestConfirmationMode;
use Modules\GameTables\Domain\Enums\ParticipantCancellationReason;
use Modules\GameTables\Domain\Enums\ParticipantRole;
use Modules\GameTables\Domain\Enums\ParticipantStatus;
use Modules\GameTables\Domain\Exceptions\InvalidStatusTransitionException;
use Modules\GameTables\Domain\ValueObjects\GameTableId;
use Modules\GameTables\Domain\ValueObjects\ParticipantId;

final class Participant
{
    /** Minutes that must pass before the confirmation email is sent again. */
    public const int RESEND_MARGIN_MINUTES = 5;

    public function __construct(
        public readonly ParticipantId $id,
        public readonly GameTableId $gameTableId,
        public private(set) ?string $userId,
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
        public ?DateTimeImmutable $emailConfirmedAt = null,
        public ?DateTimeImmutable $confirmationExpiresAt = null,
        public ?string $confirmationToken = null,
        public ?DateTimeImmutable $confirmationSentAt = null,
        public ?GuestConfirmationMode $confirmationMode = null,
        public ?string $fingerprint = null,
        public bool $fromRoster = false,
        public ?ParticipantCancellationReason $cancellationReason = null,
    ) {}

    public function confirm(): void
    {
        $this->status = ParticipantStatus::Confirmed;
        $this->confirmedAt = CarbonImmutable::now()->toDateTimeImmutable();
    }

    public function cancel(?ParticipantCancellationReason $reason = null): void
    {
        $this->cancellationReason = $reason;
        $this->status = ParticipantStatus::Cancelled;
        $this->cancelledAt = CarbonImmutable::now()->toDateTimeImmutable();
        $this->confirmationExpiresAt = null;
        $this->confirmationToken = null;
    }

    public function reactivate(): void
    {
        $this->status = ParticipantStatus::Pending;
        $this->cancelledAt = null;
        $this->confirmedAt = null;
        $this->waitingListPosition = null;
        $this->emailConfirmedAt = null;
        $this->confirmationExpiresAt = null;
        $this->confirmationToken = null;
        $this->confirmationSentAt = null;
        $this->confirmationMode = null;
        $this->fingerprint = null;
        $this->cancellationReason = null;
    }

    public function markFromRoster(): void
    {
        $this->fromRoster = true;
    }

    /**
     * A registration made outside the roster path (a loose one, the panel's, a spectator seat):
     * a reused row must not keep the roster's priority or its confirmed promotion.
     */
    public function unmarkFromRoster(): void
    {
        $this->fromRoster = false;
    }

    /**
     * Missed this session on purpose. A cancelled roster row with no reason is an absence too:
     * only an explicit "left the roster" lets the member be enrolled again.
     */
    public function isSessionAbsence(): bool
    {
        if (! $this->isCancelled()) {
            return false;
        }

        return $this->cancellationReason === ParticipantCancellationReason::SessionAbsence
            || ($this->fromRoster && !$this->cancellationReason instanceof \Modules\GameTables\Domain\Enums\ParticipantCancellationReason);
    }

    public function leftRoster(): bool
    {
        return $this->isCancelled() && $this->cancellationReason === ParticipantCancellationReason::LeftRoster;
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

    /**
     * Leaves the waiting list: confirmed, or pending when the table confirms by hand.
     */
    public function promoteFromWaitingList(bool $confirm = true): void
    {
        $this->waitingListPosition = null;

        if ($confirm) {
            $this->status = ParticipantStatus::Confirmed;
            $this->confirmedAt = CarbonImmutable::now()->toDateTimeImmutable();

            return;
        }

        $this->status = ParticipantStatus::Pending;
        $this->confirmedAt = null;
    }

    /**
     * Only someone who held a confirmed seat can fail to show up.
     *
     * @throws InvalidStatusTransitionException
     */
    public function markAsNoShow(): void
    {
        if (! $this->isConfirmed()) {
            throw InvalidStatusTransitionException::fromTo($this->status->value, ParticipantStatus::NoShow->value);
        }

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

    /**
     * Leaves the guest's email unconfirmed until they follow the link sent to it.
     */
    public function requireEmailConfirmation(
        GuestConfirmationMode $mode,
        string $token,
        ?DateTimeImmutable $expiresAt,
        DateTimeImmutable $now,
    ): void {
        $this->emailConfirmedAt = null;
        $this->confirmationMode = $mode;
        $this->confirmationToken = $token;
        $this->confirmationExpiresAt = $expiresAt;
        $this->confirmationSentAt = $now;
    }

    /**
     * The token stays, so following the link again finds a confirmed registration.
     */
    public function confirmEmail(DateTimeImmutable $now): void
    {
        $this->emailConfirmedAt = $now;
        $this->confirmationExpiresAt = null;
    }

    public function isAwaitingEmailConfirmation(): bool
    {
        return $this->isGuest() && !$this->emailConfirmedAt instanceof \DateTimeImmutable && $this->isActive();
    }

    public function holdsPlaceWhileUnconfirmed(): bool
    {
        return $this->isAwaitingEmailConfirmation() && $this->confirmationMode === GuestConfirmationMode::Hold;
    }

    public function isConfirmationOverdue(DateTimeImmutable $now): bool
    {
        return $this->holdsPlaceWhileUnconfirmed()
            && $this->confirmationExpiresAt instanceof \DateTimeImmutable
            && $this->confirmationExpiresAt <= $now;
    }

    public function canResendConfirmation(DateTimeImmutable $now): bool
    {
        return !$this->confirmationSentAt instanceof \DateTimeImmutable
            || $this->confirmationSentAt <= $now->sub(new DateInterval('PT'.self::RESEND_MARGIN_MINUTES.'M'));
    }

    public function markConfirmationSent(DateTimeImmutable $now): void
    {
        $this->confirmationSentAt = $now;
    }

    /**
     * The unconfirmed registration lapses: it holds nothing and leaves the waiting list.
     */
    public function expire(DateTimeImmutable $now): void
    {
        $this->status = ParticipantStatus::Expired;
        $this->waitingListPosition = null;
        $this->cancelledAt = $now;
        $this->confirmationExpiresAt = null;
        $this->confirmationToken = null;
    }

    /**
     * A signed-in user takes over the guest row: it is theirs now, and the email is proven by the
     * account. The guest's personal data goes: the account holds it, and anonymizing the account
     * would not reach it here. The fingerprint stays, cleared once the table is over.
     */
    public function attachToAccount(string $userId, DateTimeImmutable $now): void
    {
        $awaiting = $this->isAwaitingEmailConfirmation();

        $this->userId = $userId;
        $this->email = null;
        $this->firstName = null;
        $this->lastName = null;
        $this->phone = null;
        $this->cancellationToken = null;
        $this->confirmationToken = null;

        if ($awaiting) {
            $this->confirmEmail($now);
        }
    }

    public function recordFingerprint(?string $fingerprint): void
    {
        $this->fingerprint = $fingerprint;
    }
}
