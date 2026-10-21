<?php

declare(strict_types=1);

namespace Modules\GameTables\Application\Services;

use Modules\GameTables\Domain\Enums\RosterAccess;
use Modules\GameTables\Domain\Exceptions\CampaignNotFoundException;
use Modules\GameTables\Domain\Exceptions\RosterActionRefusedException;

/**
 * Who plays a campaign: requests to join, their decision, the direction adding or removing players,
 * players leaving and the roster settings of a published campaign. Every change runs under the
 * campaign lock; a member joining or leaving is then placed in, or withdrawn from, the sessions and
 * the events go out once the lock is released.
 */
interface CampaignRosterServiceInterface
{
    /**
     * @throws CampaignNotFoundException
     * @throws RosterActionRefusedException unavailable, finished, is_director, already_member,
     *                                      request_pending, not_accepting, roster_full or members_only
     */
    public function requestToJoin(string $campaignId, string $userId, ?string $message): void;

    /**
     * @throws CampaignNotFoundException
     * @throws RosterActionRefusedException not_pending when the user has no pending request
     */
    public function withdrawRequest(string $campaignId, string $userId): void;

    /**
     * Allowed while the campaign is closed to new players or on hold.
     *
     * @throws CampaignNotFoundException
     * @throws RosterActionRefusedException not_pending, unavailable, finished, is_director,
     *                                      already_member or roster_full
     */
    public function acceptRequest(string $requestId, string $actorUserId): void;

    /**
     * @throws CampaignNotFoundException
     * @throws RosterActionRefusedException not_pending
     */
    public function rejectRequest(string $requestId, string $actorUserId, ?string $reason): void;

    /**
     * Allowed while the campaign is closed to new players or on hold; a pending request of the
     * user is accepted with the actor as decider.
     *
     * @throws CampaignNotFoundException
     * @throws RosterActionRefusedException unavailable, finished, account_not_found (unknown or
     *                                      anonymized account), is_director, account_already_member
     *                                      or roster_full
     */
    public function addUser(string $campaignId, string $userId, string $actorUserId): void;

    /**
     * The direction removes a player, also once the campaign has finished. Their roster rows in
     * the sessions not started yet are cancelled (left_roster), and the removal is announced with
     * CampaignRosterMemberRemoved so the player can be told.
     *
     * @throws CampaignNotFoundException
     * @throws RosterActionRefusedException not_member
     */
    public function removeMember(string $campaignId, string $userId, string $actorUserId): void;

    /**
     * The player leaves the roster: the same as a removal, announced with CampaignRosterMemberLeft;
     * nothing is sent to the player.
     *
     * @throws CampaignNotFoundException
     * @throws RosterActionRefusedException not_member
     */
    public function leave(string $campaignId, string $userId): void;

    /**
     * The three roster settings the direction may change on a published campaign. Publication and
     * moderation stay as they are: the campaign does not go back to review.
     *
     * @throws CampaignNotFoundException
     * @throws RosterActionRefusedException unavailable, finished or max_below_roster
     */
    public function updateSettings(string $campaignId, bool $acceptsNewPlayers, RosterAccess $access, ?int $maxPlayers): void;
}
