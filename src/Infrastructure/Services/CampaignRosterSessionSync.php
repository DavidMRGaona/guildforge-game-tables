<?php

declare(strict_types=1);

namespace Modules\GameTables\Infrastructure\Services;

use Modules\GameTables\Application\Services\CampaignRosterSessionSyncInterface;
use Modules\GameTables\Application\Services\RegistrationServiceInterface;
use Modules\GameTables\Domain\Enums\ParticipantStatus;
use Modules\GameTables\Domain\Events\RosterMemberEnrolledInSession;

final readonly class CampaignRosterSessionSync implements CampaignRosterSessionSyncInterface
{
    public function __construct(
        private RegistrationServiceInterface $registrations,
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
}
