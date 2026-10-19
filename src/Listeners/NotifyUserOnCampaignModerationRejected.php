<?php

declare(strict_types=1);

namespace Modules\GameTables\Listeners;

use Illuminate\Support\Facades\Notification;
use Modules\GameTables\Domain\Events\CampaignModerationRejected;
use Modules\GameTables\Infrastructure\Services\NotificationRecipientResolver;
use Modules\GameTables\Notifications\CampaignModerationRejectedNotification;

final readonly class NotifyUserOnCampaignModerationRejected
{
    public function __construct(
        private NotificationRecipientResolver $recipientResolver,
    ) {}

    public function handle(CampaignModerationRejected $event): void
    {
        $creatorEmail = $this->recipientResolver->getCreatorEmail($event->createdBy);

        if ($creatorEmail === null) {
            return;
        }

        Notification::route('mail', $creatorEmail)->notify(new CampaignModerationRejectedNotification(
            campaignId: $event->campaignId,
            campaignTitle: $event->title,
            userName: $this->recipientResolver->getCreatorDisplayName($event->createdBy),
            reason: $event->reason,
        ));
    }
}
