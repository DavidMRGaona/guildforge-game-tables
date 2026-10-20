<?php

declare(strict_types=1);

namespace Modules\GameTables\Application\Services;

use Modules\GameTables\Application\DTOs\EnrolledSessionDTO;

/**
 * Keeps the sessions of a campaign in step with its roster.
 */
interface CampaignRosterSessionSyncInterface
{
    /**
     * Enrols the campaign roster in a session that has just been published (or whose campaign
     * changed) and announces each member placed now with RosterMemberEnrolledInSession. Members
     * already there, absences and adopted registrations are not announced again.
     *
     * @return int the members placed now
     */
    public function onSessionPublished(string $gameTableId): int;

    /**
     * Enrols a new roster member in the campaign sessions that still admit roster enrolment, in
     * order of start. Call it once the campaign lock is released: each session takes its own
     * table lock. Nothing is announced here: the caller reports the sessions in its own event.
     *
     * @return list<EnrolledSessionDTO> one per row placed now
     */
    public function onMemberJoined(string $campaignId, string $userId): array;
}
