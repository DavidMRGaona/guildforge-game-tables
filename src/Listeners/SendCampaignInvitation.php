<?php

declare(strict_types=1);

namespace Modules\GameTables\Listeners;

use Illuminate\Support\Facades\Notification;
use Modules\GameTables\Domain\Events\CampaignInvitationSent;
use Modules\GameTables\Domain\Repositories\CampaignInvitationRepositoryInterface;
use Modules\GameTables\Domain\Repositories\CampaignRepositoryInterface;
use Modules\GameTables\Domain\ValueObjects\CampaignId;
use Modules\GameTables\Domain\ValueObjects\CampaignInvitationId;
use Modules\GameTables\Notifications\CampaignInvitationNotification;

/**
 * Mails the invitation. It does not depend on the notice settings: it is the mechanism itself.
 */
final readonly class SendCampaignInvitation
{
    public function __construct(
        private CampaignInvitationRepositoryInterface $invitations,
        private CampaignRepositoryInterface $campaigns,
    ) {}

    public function handle(CampaignInvitationSent $event): void
    {
        $invitation = $this->invitations->find(new CampaignInvitationId($event->invitationId));

        // Gone (declined, cancelled, expired) or replaced by a newer resend: this token is dead.
        if (!$invitation instanceof \Modules\GameTables\Domain\Entities\CampaignInvitation || ! hash_equals($invitation->tokenHash, hash('sha256', $event->token))) {
            return;
        }

        $campaign = $this->campaigns->find(new CampaignId($event->campaignId));

        if (!$campaign instanceof \Modules\GameTables\Domain\Entities\Campaign) {
            return;
        }

        Notification::route('mail', $event->email)->notify(new CampaignInvitationNotification(
            inviterName: $invitation->inviterName,
            campaignTitle: $campaign->title,
            campaignSlug: $campaign->slug,
            email: $event->email,
            token: $event->token,
            expiresAt: $invitation->expiresAt
                ->setTimezone(new \DateTimeZone((string) config('app.timezone')))
                ->format('d/m/Y'),
        ));
    }
}
