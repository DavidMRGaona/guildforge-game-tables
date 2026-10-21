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
}
