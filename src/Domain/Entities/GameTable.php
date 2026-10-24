<?php

declare(strict_types=1);

namespace Modules\GameTables\Domain\Entities;

use DateTimeImmutable;
use Modules\GameTables\Domain\Enums\CharacterCreation;
use Modules\GameTables\Domain\Enums\ExperienceLevel;
use Modules\GameTables\Domain\Enums\FrontendCreationStatus;
use Modules\GameTables\Domain\Enums\Genre;
use Modules\GameTables\Domain\Enums\RegistrationType;
use Modules\GameTables\Domain\Enums\SafetyTool;
use Modules\GameTables\Domain\Enums\TableFormat;
use Modules\GameTables\Domain\Enums\TableStatus;
use Modules\GameTables\Domain\Enums\TableType;
use Modules\GameTables\Domain\Enums\Tone;
use Modules\GameTables\Domain\Exceptions\InvalidStatusTransitionException;
use Modules\GameTables\Domain\ValueObjects\CampaignId;
use Modules\GameTables\Domain\ValueObjects\GameSystemId;
use Modules\GameTables\Domain\ValueObjects\GameTableId;
use Modules\GameTables\Domain\ValueObjects\TimeSlot;

final class GameTable
{
    /**
     * @param array<Genre>|null $genres
     * @param array<SafetyTool>|null $safetyTools
     * @param array<string>|null $contentWarningIds
     * @param array<string>|null $customWarnings
     */
    public function __construct(
        public readonly GameTableId $id,
        public readonly GameSystemId $gameSystemId,
        public readonly string $createdBy,
        public string $title,
        public string $slug,
        public TimeSlot $timeSlot,
        public TableType $tableType,
        public TableFormat $tableFormat,
        public TableStatus $status,
        public int $minPlayers,
        public int $maxPlayers,
        public ?CampaignId $campaignId = null,
        public ?string $eventId = null,
        public int $maxSpectators = 0,
        public ?string $synopsis = null,
        public ?string $location = null,
        public ?string $onlineUrl = null,
        public ?int $minimumAge = null,
        public string $language = 'es',
        public ?array $genres = null,
        public ?Tone $tone = null,
        public ?ExperienceLevel $experienceLevel = null,
        public ?CharacterCreation $characterCreation = null,
        public ?array $safetyTools = null,
        public ?array $contentWarningIds = null,
        public ?array $customWarnings = null,
        public RegistrationType $registrationType = RegistrationType::Everyone,
        public int $membersEarlyAccessDays = 0,
        public ?DateTimeImmutable $registrationOpensAt = null,
        public ?DateTimeImmutable $registrationClosesAt = null,
        public bool $autoConfirm = true,
        public bool $acceptsRegistrationsInProgress = false,
        public bool $delegatesRegistrations = false,
        public bool $isPublished = false,
        public ?DateTimeImmutable $publishedAt = null,
        public ?string $notes = null,
        public ?string $imagePublicId = null,
        public ?FrontendCreationStatus $frontendCreationStatus = null,
        public ?string $moderationNotes = null,
        public ?DateTimeImmutable $createdAt = null,
        public ?DateTimeImmutable $updatedAt = null,
    ) {
    }

    public function publish(): void
    {
        $this->status = TableStatus::Scheduled;
        $this->isPublished = true;
        $this->publishedAt = new DateTimeImmutable();
    }

    public function unpublish(): void
    {
        $this->status = TableStatus::Draft;
        $this->isPublished = false;
        $this->publishedAt = null;
    }

    public function markAsFull(): void
    {
        $this->status = TableStatus::Full;
    }

    /**
     * @throws InvalidStatusTransitionException unless the table is scheduled or full
     */
    public function start(): void
    {
        $this->transitionTo(TableStatus::InProgress);
    }

    /**
     * @throws InvalidStatusTransitionException unless the table is in progress
     */
    public function complete(): void
    {
        $this->transitionTo(TableStatus::Completed);
    }

    /**
     * Drafts, cancelled and completed tables never start nor complete, whoever asks (the panel or
     * the automatic update).
     *
     * @throws InvalidStatusTransitionException
     */
    private function transitionTo(TableStatus $status): void
    {
        if (! $this->status->canTransitionTo($status)) {
            throw InvalidStatusTransitionException::fromTo($this->status->value, $status->value);
        }

        $this->status = $status;
    }

    public function cancel(): void
    {
        $this->status = TableStatus::Cancelled;
    }

    public function changeStatus(TableStatus $status): void
    {
        $this->status = $status;
    }

    public function isActive(): bool
    {
        return $this->status->isActive();
    }

    public function isFinal(): bool
    {
        return $this->status->isFinal();
    }

    public function requiresMembership(): bool
    {
        return $this->registrationType->requiresMembership();
    }

    /**
     * Whether the status and publication admit registrations; the dates are checked apart. A table
     * that admits registrations in play may also be in progress: its window is decided by time
     * (isPastRegistrationDeadline()), not by when the director marks it started.
     */
    public function canRegister(): bool
    {
        if (! $this->isPublished) {
            return false;
        }

        // A table in play admits registrations only if it says so
        return $this->status->isRegistrable()
            || ($this->status === TableStatus::InProgress && $this->acceptsRegistrationsInProgress);
    }

    /**
     * Whether the time of the table alone closes registrations: from its start or, when it admits
     * registrations in play, from its end. A configured closing date can only close them earlier
     * (isPublicRegistrationOpen()).
     */
    public function isPastRegistrationDeadline(DateTimeImmutable $now): bool
    {
        return $this->acceptsRegistrationsInProgress
            ? $this->timeSlot->hasEnded($now)
            : $this->timeSlot->hasStarted($now);
    }

    /**
     * Participants (users, and guests with their link) cancel their own registration only before
     * the table starts, also on tables that admit registrations in play. The panel has no limit.
     */
    public function canParticipantWithdraw(DateTimeImmutable $now): bool
    {
        return ! $this->timeSlot->hasStarted($now);
    }

    public function requiresInvitation(): bool
    {
        return $this->registrationType->requiresInvitation();
    }

    public function hasCapacity(int $confirmedPlayers): bool
    {
        return $confirmedPlayers < $this->maxPlayers;
    }

    /**
     * With the waiting list on, a table with manual confirmation counts pending players and pending
     * spectators as taken seats: newcomers queue behind the list (players) or are refused
     * (spectators) instead of piling up as pending registrations. Otherwise only confirmed
     * registrations take seats.
     */
    public function pendingParticipantsHoldSeats(bool $waitingListEnabled): bool
    {
        return self::pendingHoldSeatsWhen($waitingListEnabled, $this->autoConfirm);
    }

    /**
     * The same rule for callers that only hold the table's confirmation mode (table lists).
     */
    public static function pendingHoldSeatsWhen(bool $waitingListEnabled, bool $autoConfirm): bool
    {
        return $waitingListEnabled && ! $autoConfirm;
    }

    public function availablePlayerSlots(int $takenPlayerSeats): int
    {
        return max(0, $this->maxPlayers - $takenPlayerSeats);
    }

    public function availableSpectatorSlots(int $takenSpectatorSeats): int
    {
        return max(0, $this->maxSpectators - $takenSpectatorSeats);
    }

    public function isOnline(): bool
    {
        return $this->tableFormat === TableFormat::Online || $this->tableFormat === TableFormat::Hybrid;
    }

    public function isInPerson(): bool
    {
        return $this->tableFormat === TableFormat::InPerson || $this->tableFormat === TableFormat::Hybrid;
    }

    public function isMemberEarlyAccessActive(DateTimeImmutable $now): bool
    {
        if ($this->membersEarlyAccessDays <= 0 || $this->registrationOpensAt === null) {
            return false;
        }

        $memberAccessStart = $this->registrationOpensAt->modify("-{$this->membersEarlyAccessDays} days");

        return $now >= $memberAccessStart && $now < $this->registrationOpensAt;
    }

    /**
     * Whether the campaign roster may be enrolled now: the opening date and members' early
     * access do not delay it, only the effective closing does.
     */
    public function admitsRosterEnrollment(DateTimeImmutable $now): bool
    {
        return $this->canRegister() && $now < $this->effectiveRegistrationClosesAt();
    }

    /**
     * The moment registrations really close: the configured closing date or the limit of the
     * table (its start, or its end when it admits registrations in play), whichever comes first.
     */
    public function effectiveRegistrationClosesAt(): DateTimeImmutable
    {
        return self::effectiveClosingFor(
            $this->timeSlot->startsAt,
            $this->timeSlot->durationMinutes,
            $this->acceptsRegistrationsInProgress,
            $this->registrationClosesAt,
        );
    }

    public static function effectiveClosingFor(
        \DateTimeInterface $startsAt,
        int $durationMinutes,
        bool $acceptsRegistrationsInProgress,
        ?\DateTimeInterface $configuredClosesAt,
    ): DateTimeImmutable {
        $slot = new TimeSlot(DateTimeImmutable::createFromInterface($startsAt), $durationMinutes);
        $limit = $acceptsRegistrationsInProgress ? $slot->endsAt() : $slot->startsAt;

        if ($configuredClosesAt === null) {
            return $limit;
        }

        $configured = DateTimeImmutable::createFromInterface($configuredClosesAt);

        return $configured < $limit ? $configured : $limit;
    }

    public function isPublicRegistrationOpen(DateTimeImmutable $now): bool
    {
        if ($this->isPastRegistrationDeadline($now)) {
            return false;
        }

        if ($this->registrationOpensAt !== null && $now < $this->registrationOpensAt) {
            return false;
        }

        if ($this->registrationClosesAt !== null && $now > $this->registrationClosesAt) {
            return false;
        }

        return true;
    }

    /**
     * The waiting list closes with the registrations: when the table stops admitting them
     * (status, publication), its closing date has passed or it has started, also on tables that
     * admit registrations in play. The opening date does not matter: early registrations may
     * already wait for a seat.
     */
    public function isWaitingListOpen(DateTimeImmutable $now): bool
    {
        if (! $this->canRegister() || $this->timeSlot->hasStarted($now)) {
            return false;
        }

        return $this->registrationClosesAt === null || $now <= $this->registrationClosesAt;
    }

    public function canUserRegister(bool $isMember, DateTimeImmutable $now, bool $membersEarlyAccess = true): bool
    {
        // Check publication and registrable status
        if (! $this->canRegister()) {
            return false;
        }

        // The time of the table closes every registration, members' early access included
        if ($this->isPastRegistrationDeadline($now)) {
            return false;
        }

        if ($this->requiresInvitation()) {
            return false;
        }

        if ($this->requiresMembership() && ! $isMember) {
            return false;
        }

        // Early access for members
        if ($membersEarlyAccess && $isMember && $this->isMemberEarlyAccessActive($now)) {
            return true;
        }

        return $this->isPublicRegistrationOpen($now);
    }

    public function meetsAgeRequirement(?int $userAge): bool
    {
        if ($this->minimumAge === null) {
            return true;
        }

        if ($userAge === null) {
            return false;
        }

        return $userAge >= $this->minimumAge;
    }

    /**
     * @param array<Genre>|null $genres
     * @param array<SafetyTool>|null $safetyTools
     */
    public function updateDetails(
        string $title,
        ?string $synopsis,
        TimeSlot $timeSlot,
        TableType $tableType,
        TableFormat $tableFormat,
        ?string $location,
        ?string $onlineUrl,
        int $minPlayers,
        int $maxPlayers,
        int $maxSpectators,
        ?int $minimumAge,
        string $language,
        ?array $genres,
        ?Tone $tone,
        ?ExperienceLevel $experienceLevel,
        ?CharacterCreation $characterCreation,
        ?array $safetyTools,
    ): void {
        $this->title = $title;
        $this->synopsis = $synopsis;
        $this->timeSlot = $timeSlot;
        $this->tableType = $tableType;
        $this->tableFormat = $tableFormat;
        $this->location = $location;
        $this->onlineUrl = $onlineUrl;
        $this->minPlayers = $minPlayers;
        $this->maxPlayers = $maxPlayers;
        $this->maxSpectators = $maxSpectators;
        $this->minimumAge = $minimumAge;
        $this->language = $language;
        $this->genres = $genres;
        $this->tone = $tone;
        $this->experienceLevel = $experienceLevel;
        $this->characterCreation = $characterCreation;
        $this->safetyTools = $safetyTools;
    }

    /**
     * @param array<string>|null $contentWarningIds
     * @param array<string>|null $customWarnings
     */
    public function updateContentWarnings(?array $contentWarningIds, ?array $customWarnings): void
    {
        $this->contentWarningIds = $contentWarningIds;
        $this->customWarnings = $customWarnings;
    }

    public function updateRegistrationSettings(
        RegistrationType $registrationType,
        int $membersEarlyAccessDays,
        ?DateTimeImmutable $registrationOpensAt,
        ?DateTimeImmutable $registrationClosesAt,
        bool $autoConfirm,
        bool $acceptsRegistrationsInProgress = false,
    ): void {
        $this->registrationType = $registrationType;
        $this->membersEarlyAccessDays = $membersEarlyAccessDays;
        $this->registrationOpensAt = $registrationOpensAt;
        $this->registrationClosesAt = $registrationClosesAt;
        $this->autoConfirm = $autoConfirm;
        $this->acceptsRegistrationsInProgress = $acceptsRegistrationsInProgress;
    }

    public function linkToEvent(?string $eventId): void
    {
        $this->eventId = $eventId;
    }

    public function linkToCampaign(?CampaignId $campaignId): void
    {
        $this->campaignId = $campaignId;
    }

    public function setFrontendCreationStatus(FrontendCreationStatus $status, ?string $notes = null): void
    {
        $this->frontendCreationStatus = $status;
        if ($notes !== null) {
            $this->moderationNotes = $notes;
        }
    }

    public function approveFrontendCreation(?string $notes = null): void
    {
        $this->frontendCreationStatus = FrontendCreationStatus::Approved;
        if ($notes !== null) {
            $this->moderationNotes = $notes;
        }
    }

    /**
     * Back to the author as an unpublished draft that keeps the reason until it is sent again.
     */
    public function rejectFrontendCreation(string $reason): void
    {
        if ($this->isPublished) {
            $this->unpublish();
        }

        $this->frontendCreationStatus = FrontendCreationStatus::Draft;
        $this->moderationNotes = $reason;
    }

    /**
     * Sent to review: the reason of an earlier rejection no longer applies.
     */
    public function submitForModeration(): void
    {
        $this->frontendCreationStatus = FrontendCreationStatus::PendingReview;
        $this->moderationNotes = null;
    }

    public function isCreatedFromFrontend(): bool
    {
        return $this->frontendCreationStatus !== null;
    }

    public function isPendingModeration(): bool
    {
        return $this->frontendCreationStatus !== null && $this->frontendCreationStatus->isPending();
    }

    /**
     * Whether its author may edit it, submit it for review or delete it from the web.
     */
    public function canBeChangedByAuthor(): bool
    {
        return FrontendCreationStatus::allowsAuthorChanges($this->frontendCreationStatus, $this->isPublished);
    }

    /**
     * Why the table was rejected, while it is back in the author's hands; null otherwise. Only a
     * rejection leaves notes on a draft (submitting clears them, approval notes go with Approved).
     */
    public function rejectionReason(): ?string
    {
        if (! $this->canBeChangedByAuthor() || $this->moderationNotes === null || trim($this->moderationNotes) === '') {
            return null;
        }

        return $this->moderationNotes;
    }
}
