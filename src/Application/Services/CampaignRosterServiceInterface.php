<?php

declare(strict_types=1);

namespace Modules\GameTables\Application\Services;

use Modules\GameTables\Domain\Exceptions\CampaignNotFoundException;
use Modules\GameTables\Domain\Exceptions\RosterActionRefusedException;

/**
 * Who plays a campaign: requests to join, their decision and the direction adding players. Every
 * change runs under the campaign lock; a new member is then placed in the open sessions and the
 * events go out once the lock is released.
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
     * The same refusal for an unknown, anonymized or deleted account: nothing tells them apart.
     *
     * @throws CampaignNotFoundException
     * @throws RosterActionRefusedException account_not_found, or any refusal of addUser()
     */
    public function addByEmail(string $campaignId, string $email, string $actorUserId): void;

    /**
     * Allowed while the campaign is closed to new players or on hold; a pending request of the
     * user is accepted with the actor as decider.
     *
     * @throws CampaignNotFoundException
     * @throws RosterActionRefusedException unavailable, finished, is_director, account_already_member
     *                                      or roster_full
     */
    public function addUser(string $campaignId, string $userId, string $actorUserId): void;
}
