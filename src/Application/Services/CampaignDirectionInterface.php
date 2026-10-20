<?php

declare(strict_types=1);

namespace Modules\GameTables\Application\Services;

use Modules\GameTables\Application\DTOs\MailRecipientDTO;

/**
 * Who directs a campaign: the person who created it from the web plus its game masters that have an account.
 */
interface CampaignDirectionInterface
{
    public function isDirector(string $campaignId, string $userId): bool;

    /**
     * Unique ids of the active accounts that direct the campaign.
     *
     * @return list<string>
     */
    public function directorUserIds(string $campaignId): array;

    /**
     * @return list<string>
     */
    public function campaignIdsDirectedBy(string $userId): array;

    /**
     * Directors to email: the web creator always, game masters with an account only when they ask for notices.
     *
     * @return list<MailRecipientDTO>
     */
    public function recipients(string $campaignId): array;
}
