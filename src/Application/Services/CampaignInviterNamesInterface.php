<?php

declare(strict_types=1);

namespace Modules\GameTables\Application\Services;

use Modules\GameTables\Domain\Entities\CampaignInvitation;

/**
 * The name an invitation shows for whoever sent it, worked out when it is read, so a later change of
 * name or of «Nombre visible públicamente» reaches the invitations still pending:
 *
 * - a director of the campaign goes by their public name only when they chose to show it, otherwise by
 *   the title or role the campaign page shows for them;
 * - anybody else by the account's public name, or the account name when it has none;
 * - an account that no longer exists by the name stored when inviting.
 */
interface CampaignInviterNamesInterface
{
    /**
     * The name to store with a new invitation: the same rule, kept for when the account is gone.
     */
    public function forNewInvitation(string $campaignId, string $inviterId): string;

    /**
     * @param  list<CampaignInvitation>  $invitations
     * @return array<string, string> The name for each invitation, keyed by its id
     */
    public function namesOf(array $invitations): array;

    public function nameOf(CampaignInvitation $invitation): string;
}
