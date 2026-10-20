<?php

declare(strict_types=1);

namespace Modules\GameTables\Domain\Repositories;

use Modules\GameTables\Domain\Entities\CampaignJoinRequest;
use Modules\GameTables\Domain\Exceptions\RosterActionRefusedException;
use Modules\GameTables\Domain\ValueObjects\CampaignId;
use Modules\GameTables\Domain\ValueObjects\JoinRequestId;

interface CampaignJoinRequestRepositoryInterface
{
    /**
     * @throws RosterActionRefusedException when the user already has a pending request in the campaign
     */
    public function save(CampaignJoinRequest $request): void;

    public function find(JoinRequestId $id): ?CampaignJoinRequest;

    public function findPending(CampaignId $campaignId, string $userId): ?CampaignJoinRequest;

    /**
     * Oldest first; requests of anonymized accounts are left out.
     *
     * @return list<CampaignJoinRequest>
     */
    public function pendingFor(CampaignId $campaignId): array;

    /**
     * @param  list<CampaignId>  $campaignIds
     * @return array<string, int> pending requests by campaign id (0 when none)
     */
    public function pendingCountsFor(array $campaignIds): array;
}
