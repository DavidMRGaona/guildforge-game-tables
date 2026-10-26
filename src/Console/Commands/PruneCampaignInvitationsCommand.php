<?php

declare(strict_types=1);

namespace Modules\GameTables\Console\Commands;

use App\Application\Services\TimezoneServiceInterface;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Modules\GameTables\Application\Services\CampaignInvitationSendLogInterface;
use Modules\GameTables\Domain\Repositories\CampaignInvitationRepositoryInterface;

final class PruneCampaignInvitationsCommand extends Command
{
    protected $signature = 'game-tables:prune-campaign-invitations';

    protected $description = 'Delete expired campaign invitations, those of campaigns no longer open and send records older than 30 days';

    public function handle(
        CampaignInvitationRepositoryInterface $invitations,
        CampaignInvitationSendLogInterface $sendLog,
    ): int {
        $now = CarbonImmutable::now()->toDateTimeImmutable();

        $expired = $invitations->deleteExpired($now);
        $closed = $invitations->deleteForCampaignsNotOpen();
        // Thirty days on the site's calendar
        $timezone = app(TimezoneServiceInterface::class);
        $records = $sendLog->pruneBefore($timezone->toDisplay($now)->modify('-30 days'));

        $this->info(sprintf(
            'Pruned %d expired invitation(s), %d of closed campaigns, %d send record(s).',
            $expired,
            $closed,
            $records,
        ));

        return self::SUCCESS;
    }
}
