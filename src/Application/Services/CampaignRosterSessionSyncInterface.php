<?php

declare(strict_types=1);

namespace Modules\GameTables\Application\Services;

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
}
