<?php

declare(strict_types=1);

namespace Modules\GameTables\Infrastructure\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Modules\GameTables\Application\DTOs\EnrolledSessionDTO;
use Modules\GameTables\Application\Services\CampaignRosterSessionSyncInterface;
use Modules\GameTables\Application\Services\RegistrationServiceInterface;
use Modules\GameTables\Domain\Entities\GameTable;
use Modules\GameTables\Domain\Enums\ParticipantStatus;
use Modules\GameTables\Domain\Events\RosterMemberEnrolledInSession;
use Modules\GameTables\Domain\Exceptions\CannotCancelException;
use Modules\GameTables\Domain\Exceptions\ParticipantNotFoundException;
use Modules\GameTables\Domain\Exceptions\RosterActionRefusedException;
use Modules\GameTables\Domain\Repositories\CampaignRosterRepositoryInterface;
use Modules\GameTables\Domain\Repositories\GameTableRepositoryInterface;
use Modules\GameTables\Domain\Repositories\ParticipantRepositoryInterface;
use Modules\GameTables\Domain\ValueObjects\CampaignId;
use Modules\GameTables\Domain\ValueObjects\GameTableId;

final readonly class CampaignRosterSessionSync implements CampaignRosterSessionSyncInterface
{
    public function __construct(
        private RegistrationServiceInterface $registrations,
        private GameTableRepositoryInterface $gameTables,
        private CampaignRosterRepositoryInterface $roster,
        private ParticipantRepositoryInterface $participants,
    ) {}

    public function onSessionPublished(string $gameTableId): int
    {
        // Under the table lock; the events go out once it is released
        $placed = $this->registrations->enrollRoster($gameTableId);

        foreach ($placed as $participant) {
            if ($participant->userId === null) {
                continue;
            }

            RosterMemberEnrolledInSession::dispatch(
                $participant->id,
                $participant->gameTableId,
                $participant->userId,
                $participant->status === ParticipantStatus::WaitingList,
            );
        }

        return count($placed);
    }

    public function onMemberJoined(string $campaignId, string $userId): array
    {
        $now = CarbonImmutable::now();
        $enrolled = [];

        // By start; enrollRoster checks the window again under each table lock
        foreach ($this->gameTables->getByCampaign(new CampaignId($campaignId)) as $session) {
            if (! $session->admitsRosterEnrollment($now)) {
                continue;
            }

            foreach ($this->registrations->enrollRoster($session->id->value, $userId) as $participant) {
                $enrolled[] = new EnrolledSessionDTO(
                    $participant->gameTableId,
                    $participant->status === ParticipantStatus::WaitingList,
                );
            }
        }

        return $enrolled;
    }

    public function onMemberLeft(string $campaignId, string $userId): int
    {
        $now = CarbonImmutable::now();
        $cancelled = 0;

        // Published or not: a session published again later must not keep someone who left
        foreach ($this->gameTables->getByCampaign(new CampaignId($campaignId)) as $session) {
            if ($session->isFinal() || $session->timeSlot->hasStarted($now)) {
                continue;
            }

            if ($this->registrations->withdrawRosterMember($session->id->value, $userId)) {
                $cancelled++;
            }
        }

        return $cancelled;
    }

    public function markAbsence(string $campaignId, string $gameTableId, string $userId): void
    {
        $session = $this->sessionOf($campaignId, $gameTableId);
        $this->ensureMember($campaignId, $userId);

        if ($session->timeSlot->hasStarted(CarbonImmutable::now())) {
            throw RosterActionRefusedException::because('session_started');
        }

        $row = $this->participants->findByTableAndUser($session->id, $userId);

        if ($row === null || ! $row->fromRoster || ! $row->canBeCancelled()) {
            throw RosterActionRefusedException::because('nothing_to_change');
        }

        // Cancelled as the organisation's: a roster row cancelled so is an absence (session_absence)
        try {
            $this->registrations->cancelByAdmin($row->id->value);
        } catch (CannotCancelException) {
            // Cancelled meanwhile
            throw RosterActionRefusedException::because('nothing_to_change');
        }
    }

    public function clearAbsence(string $campaignId, string $gameTableId, string $userId): void
    {
        $this->sessionOf($campaignId, $gameTableId);
        $this->ensureMember($campaignId, $userId);

        try {
            $this->registrations->restoreAbsence($gameTableId, $userId);
        } catch (ParticipantNotFoundException) {
            throw RosterActionRefusedException::because('nothing_to_change');
        }
    }

    private function sessionOf(string $campaignId, string $gameTableId): GameTable
    {
        $session = Str::isUuid($gameTableId) ? $this->gameTables->find(new GameTableId($gameTableId)) : null;

        if ($session === null || $session->campaignId?->value !== $campaignId) {
            throw RosterActionRefusedException::because('session_not_in_campaign');
        }

        return $session;
    }

    private function ensureMember(string $campaignId, string $userId): void
    {
        if (! $this->roster->contains(new CampaignId($campaignId), $userId)) {
            throw RosterActionRefusedException::because('not_member');
        }
    }
}
