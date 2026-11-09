<?php

declare(strict_types=1);

namespace Modules\GameTables\Listeners;

use Modules\GameTables\Domain\Events\CampaignSubmittedForReview;
use Modules\GameTables\Infrastructure\Mail\MailRecipientNotifier;
use Modules\GameTables\Infrastructure\Services\NotificationRecipientResolver;
use Modules\GameTables\Notifications\CampaignModerationSubmittedNotification;

final readonly class NotifyAdminsOnCampaignModerationSubmission
{
    public function __construct(
        private NotificationRecipientResolver $recipientResolver,
        private MailRecipientNotifier $notifier,
    ) {}

    public function handle(CampaignSubmittedForReview $event): void
    {
        $notification = new CampaignModerationSubmittedNotification(
            campaignId: $event->campaignId,
            campaignTitle: $event->title,
            creatorName: $this->recipientResolver->getCreatorDisplayName($event->createdBy),
        );

        $this->notifier->sendToEach($this->recipientResolver->getCampaignModeratorRecipients(), $notification);
    }
}
