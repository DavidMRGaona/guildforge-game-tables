<?php

declare(strict_types=1);

namespace Modules\GameTables\Listeners;

use Illuminate\Support\Facades\Notification;
use Modules\GameTables\Domain\Events\CampaignSubmittedForReview;
use Modules\GameTables\Infrastructure\Services\NotificationRecipientResolver;
use Modules\GameTables\Notifications\CampaignModerationSubmittedNotification;

final readonly class NotifyAdminsOnCampaignModerationSubmission
{
    public function __construct(
        private NotificationRecipientResolver $recipientResolver,
    ) {}

    public function handle(CampaignSubmittedForReview $event): void
    {
        $notification = new CampaignModerationSubmittedNotification(
            campaignId: $event->campaignId,
            campaignTitle: $event->title,
            creatorName: $this->recipientResolver->getCreatorDisplayName($event->createdBy),
        );

        foreach ($this->recipientResolver->getCampaignModeratorEmails() as $email) {
            Notification::route('mail', $email)->notify($notification);
        }
    }
}
