<?php

declare(strict_types=1);

namespace Modules\GameTables\Listeners;

use Illuminate\Support\Facades\Notification;
use Modules\GameTables\Domain\Events\GameTableModerationApproved;
use Modules\GameTables\Infrastructure\Services\NotificationRecipientResolver;
use Modules\GameTables\Notifications\ModerationApprovedNotification;

final readonly class NotifyUserOnModerationApproved
{
    public function __construct(
        private NotificationRecipientResolver $recipientResolver,
    ) {}

    public function handle(GameTableModerationApproved $event): void
    {
        $creatorEmail = $this->recipientResolver->getCreatorEmail($event->createdBy);

        if ($creatorEmail === null) {
            return;
        }

        $creatorName = $this->recipientResolver->getCreatorDisplayName($event->createdBy);

        $notification = new ModerationApprovedNotification(
            tableId: $event->gameTableId,
            tableTitle: $event->title,
            userName: $creatorName,
            notes: $event->notes,
        );

        Notification::route('mail', $creatorEmail)->notify($notification);
    }
}
