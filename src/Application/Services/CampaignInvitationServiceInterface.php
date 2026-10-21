<?php

declare(strict_types=1);

namespace Modules\GameTables\Application\Services;

use DateTimeImmutable;
use Modules\GameTables\Application\DTOs\CampaignInvitationViewDTO;
use Modules\GameTables\Domain\Exceptions\CampaignNotFoundException;
use Modules\GameTables\Domain\Exceptions\RosterActionRefusedException;

interface CampaignInvitationServiceInterface
{
    /**
     * Invites an email address to the campaign; a pending invitation for it is resent instead.
     *
     * @throws CampaignNotFoundException
     * @throws RosterActionRefusedException
     */
    public function invite(string $campaignId, string $email, string $actorUserId): void;

    /**
     * @throws CampaignNotFoundException
     * @throws RosterActionRefusedException
     */
    public function resend(string $campaignId, string $invitationId, string $actorUserId): void;

    /**
     * @throws RosterActionRefusedException when the invitation does not exist or belongs to another campaign
     */
    public function cancel(string $campaignId, string $invitationId): void;

    /**
     * The campaign's pending invitations that have not expired, oldest first.
     *
     * @return list<CampaignInvitationViewDTO>
     *
     * @throws CampaignNotFoundException
     */
    public function pendingFor(string $campaignId, DateTimeImmutable $now): array;

    /**
     * The invitation behind an emailed token, or null when the token is unknown or has expired.
     */
    public function viewByToken(string $token, DateTimeImmutable $now): ?CampaignInvitationViewDTO;

    /**
     * Joins the campaign with the emailed token. The account must hold the invited email.
     *
     * @throws RosterActionRefusedException invitation_not_found, invitation_other_account, or the roster's own refusals
     */
    public function accept(string $token, string $userId): void;

    /**
     * Declines with the emailed token, which needs no account: the email is blocked for 30 days.
     *
     * @throws RosterActionRefusedException invitation_not_found
     */
    public function reject(string $token): void;

    /**
     * Like accept() for an invitation picked from the profile: the account email must be verified.
     * Another account's invitation answers invitation_not_found.
     *
     * @throws RosterActionRefusedException email_not_verified, invitation_not_found, or the roster's own refusals
     */
    public function acceptById(string $invitationId, string $userId): void;

    /**
     * @throws RosterActionRefusedException email_not_verified, invitation_not_found
     */
    public function rejectById(string $invitationId, string $userId): void;

    /**
     * The invitations addressed to an email (compared without case or surrounding spaces) that have
     * not expired, oldest first.
     *
     * @return list<CampaignInvitationViewDTO>
     */
    public function pendingForEmail(string $email, DateTimeImmutable $now): array;
}
