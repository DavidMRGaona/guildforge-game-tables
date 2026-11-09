<?php

declare(strict_types=1);

namespace Modules\GameTables\Listeners;

use Modules\GameTables\Domain\Events\GameTableModerationApproved;
use Modules\GameTables\Infrastructure\Mail\MailRecipientNotifier;
use Modules\GameTables\Infrastructure\Services\NotificationRecipientResolver;
use Modules\GameTables\Notifications\ModerationApprovedNotification;

final readonly class NotifyUserOnModerationApproved
{
    public function __construct(
        private NotificationRecipientResolver $recipientResolver,
        private MailRecipientNotifier $notifier,
    ) {}

    public function handle(GameTableModerationApproved $event): void
    {
        $creator = $this->recipientResolver->getCreatorRecipient($event->createdBy);

        if ($creator === null) {
            return;
        }

        $creatorName = $this->recipientResolver->getCreatorDisplayName($event->createdBy);

        $notification = new ModerationApprovedNotification(
            tableId: $event->gameTableId,
            tableTitle: $event->title,
            userName: $creatorName,
            notes: $event->notes,
        );

        $this->notifier->send($creator, $notification);
    }
}
