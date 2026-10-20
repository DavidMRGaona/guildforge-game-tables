<?php

declare(strict_types=1);

namespace Modules\GameTables\Application\Services;

use Modules\GameTables\Application\DTOs\EnrolledSessionDTO;
use Modules\GameTables\Domain\Exceptions\RegistrationNotAllowedException;
use Modules\GameTables\Domain\Exceptions\RosterActionRefusedException;
use Modules\GameTables\Domain\Exceptions\TableFullException;

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

    /**
     * The member is no longer in the roster: their roster row is cancelled (reason left_roster) in
     * every session of the campaign that has not started and is not over, published or not, so a
     * session published again later never keeps them. Call it once the campaign lock is released:
     * each session takes its own table lock. No cancellation is announced; the seats freed promote
     * the waiting list as usual.
     *
     * @return int the rows cancelled
     */
    public function onMemberLeft(string $campaignId, string $userId): int;

    /**
     * The direction marks a member absent from one session of the campaign: their roster row is
     * cancelled as the organisation's (reason session_absence) and the member is told. The member
     * stays in the roster and is never enrolled in that session again unless the absence is
     * cleared.
     *
     * @throws RosterActionRefusedException session_not_in_campaign, not_member, session_started or
     *                                      nothing_to_change (no active roster row)
     */
    public function markAbsence(string $campaignId, string $gameTableId, string $userId): void;

    /**
     * The direction clears a member's absence from one session: the member is placed again and
     * gets the usual registration confirmation; the organisation is not told.
     *
     * @throws RosterActionRefusedException session_not_in_campaign, not_member or nothing_to_change
     *                                      (no absence in the session)
     * @throws RegistrationNotAllowedException when the session no longer admits roster enrolment
     * @throws TableFullException when no seat is free
     */
    public function clearAbsence(string $campaignId, string $gameTableId, string $userId): void;
}
