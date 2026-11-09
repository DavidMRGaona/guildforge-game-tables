<?php

declare(strict_types=1);

namespace Modules\GameTables\Listeners;

use Modules\GameTables\Application\DTOs\MailRecipientDTO;
use Modules\GameTables\Domain\Events\CampaignModerationApproved;
use Modules\GameTables\Infrastructure\Mail\MailRecipientNotifier;
use Modules\GameTables\Infrastructure\Services\NotificationRecipientResolver;
use Modules\GameTables\Notifications\CampaignModerationApprovedNotification;

final readonly class NotifyUserOnCampaignModerationApproved
{
    public function __construct(
        private NotificationRecipientResolver $recipientResolver,
        private MailRecipientNotifier $notifier,
    ) {}

    public function handle(CampaignModerationApproved $event): void
    {
        $creator = $this->recipientResolver->getCreatorRecipient($event->createdBy);

        if (! $creator instanceof MailRecipientDTO) {
            return;
        }

        $this->notifier->send($creator, new CampaignModerationApprovedNotification(
            campaignTitle: $event->title,
            campaignSlug: $event->slug,
            userName: $this->recipientResolver->getCreatorDisplayName($event->createdBy),
            notes: $event->notes,
        ));
    }
}
