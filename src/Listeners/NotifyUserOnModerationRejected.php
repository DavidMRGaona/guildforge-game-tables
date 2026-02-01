<?php

declare(strict_types=1);

namespace Modules\GameTables\Listeners;

use Illuminate\Support\Facades\Notification;
use Modules\GameTables\Domain\Events\GameTableModerationRejected;
use Modules\GameTables\Infrastructure\Services\NotificationRecipientResolver;
use Modules\GameTables\Notifications\ModerationRejectedNotification;

final readonly class NotifyUserOnModerationRejected
{
    public function __construct(
        private NotificationRecipientResolver $recipientResolver,
    ) {}

    public function handle(GameTableModerationRejected $event): void
    {
        $creatorEmail = $this->recipientResolver->getCreatorEmail($event->createdBy);

        if ($creatorEmail === null) {
            return;
        }

        $creatorName = $this->recipientResolver->getCreatorDisplayName($event->createdBy);

        $notification = new ModerationRejectedNotification(
            tableId: $event->gameTableId,
            tableTitle: $event->title,
            userName: $creatorName,
            reason: $event->reason,
        );

        Notification::route('mail', $creatorEmail)->notify($notification);
    }
}
