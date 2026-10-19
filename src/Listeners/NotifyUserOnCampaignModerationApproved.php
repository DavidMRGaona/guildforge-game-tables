<?php

declare(strict_types=1);

namespace Modules\GameTables\Listeners;

use Illuminate\Support\Facades\Notification;
use Modules\GameTables\Domain\Events\CampaignModerationApproved;
use Modules\GameTables\Infrastructure\Services\NotificationRecipientResolver;
use Modules\GameTables\Notifications\CampaignModerationApprovedNotification;

final readonly class NotifyUserOnCampaignModerationApproved
{
    public function __construct(
        private NotificationRecipientResolver $recipientResolver,
    ) {}

    public function handle(CampaignModerationApproved $event): void
    {
        $creatorEmail = $this->recipientResolver->getCreatorEmail($event->createdBy);

        if ($creatorEmail === null) {
            return;
        }

        Notification::route('mail', $creatorEmail)->notify(new CampaignModerationApprovedNotification(
            campaignTitle: $event->title,
            campaignSlug: $event->slug,
            userName: $this->recipientResolver->getCreatorDisplayName($event->createdBy),
            notes: $event->notes,
        ));
    }
}
