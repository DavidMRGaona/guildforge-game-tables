<?php

declare(strict_types=1);

namespace Modules\GameTables\Domain\Repositories;

use DateTimeImmutable;
use Modules\GameTables\Domain\Entities\CampaignInvitation;
use Modules\GameTables\Domain\Exceptions\RosterActionRefusedException;
use Modules\GameTables\Domain\ValueObjects\CampaignId;
use Modules\GameTables\Domain\ValueObjects\CampaignInvitationId;

interface CampaignInvitationRepositoryInterface
{
    /**
     * @throws RosterActionRefusedException when the email already has an invitation in the campaign
     */
    public function save(CampaignInvitation $invitation): void;

    public function find(CampaignInvitationId $id): ?CampaignInvitation;

    public function findByTokenHash(string $hash): ?CampaignInvitation;

    /**
     * The email is compared as given: callers normalise it.
     */
    public function findPending(CampaignId $campaignId, string $email): ?CampaignInvitation;

    /**
     * Oldest first.
     *
     * @return list<CampaignInvitation>
     */
    public function pendingFor(CampaignId $campaignId): array;

    /**
     * Not expired at $now. The email is compared as given: callers normalise it.
     *
     * @return list<CampaignInvitation>
     */
    public function pendingForEmail(string $email, DateTimeImmutable $now): array;

    public function delete(CampaignInvitationId $id): void;

    /**
     * @return int the number of invitations deleted
     */
    public function deleteExpired(DateTimeImmutable $now): int;

    /**
     * Deletes the invitations of finished campaigns and of campaigns that are neither approved nor published.
     *
     * @return int the number of invitations deleted
     */
    public function deleteForCampaignsNotOpen(): int;
}
