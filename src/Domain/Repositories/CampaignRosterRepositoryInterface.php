<?php

declare(strict_types=1);

namespace Modules\GameTables\Domain\Repositories;

use DateTimeImmutable;
use Modules\GameTables\Domain\ValueObjects\CampaignId;

/**
 * The players of a campaign. Anonymized accounts are ignored by every read.
 */
interface CampaignRosterRepositoryInterface
{
    public function contains(CampaignId $campaignId, string $userId): bool;

    /**
     * Does nothing when the user is already in the roster.
     */
    public function add(CampaignId $campaignId, string $userId, DateTimeImmutable $now): void;

    /**
     * Whether the user was in the roster.
     */
    public function remove(CampaignId $campaignId, string $userId): bool;

    /**
     * @return list<string> user ids, in joining order
     */
    public function memberIds(CampaignId $campaignId): array;

    public function count(CampaignId $campaignId): int;

    /**
     * @param  list<CampaignId>  $campaignIds
     * @return array<string, int> roster size by campaign id (0 when empty)
     */
    public function countsFor(array $campaignIds): array;
}
