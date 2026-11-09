<?php

declare(strict_types=1);

namespace Modules\GameTables\Listeners;

use Modules\GameTables\Domain\Events\CampaignModerationRejected;
use Modules\GameTables\Infrastructure\Mail\MailRecipientNotifier;
use Modules\GameTables\Infrastructure\Services\NotificationRecipientResolver;
use Modules\GameTables\Notifications\CampaignModerationRejectedNotification;

final readonly class NotifyUserOnCampaignModerationRejected
{
    public function __construct(
        private NotificationRecipientResolver $recipientResolver,
        private MailRecipientNotifier $notifier,
    ) {}

    public function handle(CampaignModerationRejected $event): void
    {
        $creator = $this->recipientResolver->getCreatorRecipient($event->createdBy);

        if ($creator === null) {
            return;
        }

        $this->notifier->send($creator, new CampaignModerationRejectedNotification(
            campaignId: $event->campaignId,
            campaignTitle: $event->title,
            userName: $this->recipientResolver->getCreatorDisplayName($event->createdBy),
            reason: $event->reason,
        ));
    }
}
