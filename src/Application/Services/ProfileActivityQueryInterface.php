<?php

declare(strict_types=1);

namespace Modules\GameTables\Application\Services;

use Modules\GameTables\Application\DTOs\ProfileCampaignItemDTO;
use Modules\GameTables\Application\DTOs\ProfileTableItemDTO;

/**
 * The tables and campaigns of a public profile (game-tables#282), for the person viewing: only what
 * the tables and campaigns pages already show them.
 *
 * - Directing: a listed table or campaign (published, neither a draft nor cancelled) whose game master
 *   linked to the owner has a public name, the only way the pages tie it to them.
 * - Playing: only where the site shows that viewer the players: the owner themselves, the directors of
 *   a table that delegates its registrations (GameTablePolicy::manageRegistrations) and the directors
 *   of a campaign whose roster holds the owner (CampaignPolicy::manageRoster). Staff permissions never
 *   widen it, as they do not open those pages.
 */
interface ProfileActivityQueryInterface
{
    /**
     * The owner's next listed tables they direct under a public name, soonest first.
     *
     * @return list<ProfileTableItemDTO>
     */
    public function upcomingTablesRunBy(string $ownerId, int $limit): array;

    /**
     * The owner's next listed tables where they hold a confirmed player seat, soonest first, as far
     * as the viewer may see the players.
     *
     * @return list<ProfileTableItemDTO>
     */
    public function upcomingTablesPlayedBy(string $ownerId, ?string $viewerId, int $limit): array;

    /**
     * Every listed table, past ones included, the owner directs under a public name.
     */
    public function countTablesRunBy(string $ownerId): int;

    /**
     * The owner's listed campaigns still going on (recruiting, active, on hold), by title.
     *
     * @return list<ProfileCampaignItemDTO>
     */
    public function activeCampaignsOf(string $ownerId, ?string $viewerId, int $limit): array;

    /**
     * Every listed campaign, finished ones included, the owner directs or plays in as far as the
     * viewer may see it.
     */
    public function countCampaignsOf(string $ownerId, ?string $viewerId): int;
}
