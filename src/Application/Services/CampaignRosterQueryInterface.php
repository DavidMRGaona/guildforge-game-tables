<?php

declare(strict_types=1);

namespace Modules\GameTables\Application\Services;

use DateTimeImmutable;
use Modules\GameTables\Application\DTOs\CampaignSessionBoxDTO;
use Modules\GameTables\Application\DTOs\JoinBoxDTO;
use Modules\GameTables\Application\DTOs\RosterPageDTO;
use Modules\GameTables\Domain\Exceptions\CampaignNotFoundException;

/**
 * What the pages show of a campaign roster: the direction's roster page, the join box of the
 * campaign page and the roster box of a session page. Anonymized accounts never show.
 */
interface CampaignRosterQueryInterface
{
    /**
     * The roster page of the direction: settings, pending requests, the upcoming sessions
     * (published, not started, not over) and each player's state in them.
     *
     * @throws CampaignNotFoundException
     */
    public function rosterPage(string $campaignId, DateTimeImmutable $now): RosterPageDTO;

    /**
     * What the viewer of the campaign page can do with the roster.
     *
     * @throws CampaignNotFoundException
     */
    public function joinBox(string $campaignId, ?string $userId): JoinBoxDTO;

    /**
     * The campaign side of a session page; null when the table has no campaign.
     */
    public function sessionBox(string $gameTableId, ?string $userId, DateTimeImmutable $now): ?CampaignSessionBoxDTO;
}
