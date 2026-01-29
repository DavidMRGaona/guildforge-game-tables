<?php

declare(strict_types=1);

namespace Modules\GameTables\Domain\Entities;

use DateTimeImmutable;
use Modules\GameTables\Domain\Enums\CharacterCreation;
use Modules\GameTables\Domain\Enums\ExperienceLevel;
use Modules\GameTables\Domain\Enums\Genre;
use Modules\GameTables\Domain\Enums\RegistrationType;
use Modules\GameTables\Domain\Enums\SafetyTool;
use Modules\GameTables\Domain\Enums\TableFormat;
use Modules\GameTables\Domain\Enums\TableStatus;
use Modules\GameTables\Domain\Enums\TableType;
use Modules\GameTables\Domain\Enums\Tone;
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
        public bool $isPublished = false,
        public ?DateTimeImmutable $publishedAt = null,
        public ?string $notes = null,
        public ?string $imagePublicId = null,
        public ?DateTimeImmutable $createdAt = null,
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

    public function start(): void
    {
        $this->status = TableStatus::InProgress;
    }

    public function complete(): void
    {
        $this->status = TableStatus::Completed;
    }

    public function cancel(): void
    {
        $this->status = TableStatus::Cancelled;
    }

    public function changeStatus(TableStatus $status): void
    {
        $this->status = $status;
    }

    /**
     * Checks if registration is currently open based on publication state, status, and dates.
     */
    public function isRegistrationOpen(DateTimeImmutable $now): bool
    {
        if (! $this->isPublished || ! $this->status->isRegistrable()) {
            return false;
        }

        $opensAt = $this->registrationOpensAt ?? $this->timeSlot->startsAt;
        $closesAt = $this->registrationClosesAt ?? $this->timeSlot->startsAt;

        return $now >= $opensAt && $now <= $closesAt;
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
     * Checks if the table is in a state that allows registration (published and registrable status).
     */
    public function canRegister(): bool
    {
        return $this->isPublished && $this->status->isRegistrable();
    }

    public function requiresInvitation(): bool
    {
        return $this->registrationType->requiresInvitation();
    }

    public function hasCapacity(int $confirmedPlayers): bool
    {
        return $confirmedPlayers < $this->maxPlayers;
    }

    public function availablePlayerSlots(int $confirmedPlayers): int
    {
        return max(0, $this->maxPlayers - $confirmedPlayers);
    }

    public function availableSpectatorSlots(int $confirmedSpectators): int
    {
        return max(0, $this->maxSpectators - $confirmedSpectators);
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

    public function isPublicRegistrationOpen(DateTimeImmutable $now): bool
    {
        if ($this->registrationOpensAt !== null && $now < $this->registrationOpensAt) {
            return false;
        }

        if ($this->registrationClosesAt !== null && $now > $this->registrationClosesAt) {
            return false;
        }

        return true;
    }

    public function canUserRegister(bool $isMember, DateTimeImmutable $now): bool
    {
        // Check publication and registrable status
        if (! $this->isPublished || ! $this->status->isRegistrable()) {
            return false;
        }

        if ($this->requiresInvitation()) {
            return false;
        }

        if ($this->requiresMembership() && ! $isMember) {
            return false;
        }

        // Early access for members
        if ($isMember && $this->isMemberEarlyAccessActive($now)) {
            return true;
        }

        return $this->isRegistrationOpen($now);
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
}
