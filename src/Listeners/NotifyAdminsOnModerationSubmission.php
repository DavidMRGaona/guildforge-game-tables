<?php

declare(strict_types=1);

namespace Modules\GameTables\Listeners;

use Modules\GameTables\Domain\Events\GameTableSubmittedForReview;
use Modules\GameTables\Infrastructure\Mail\MailRecipientNotifier;
use Modules\GameTables\Infrastructure\Services\NotificationRecipientResolver;
use Modules\GameTables\Notifications\ModerationSubmittedNotification;

final readonly class NotifyAdminsOnModerationSubmission
{
    public function __construct(
        private NotificationRecipientResolver $recipientResolver,
        private MailRecipientNotifier $notifier,
    ) {}

    public function handle(GameTableSubmittedForReview $event): void
    {
        $creatorName = $this->recipientResolver->getCreatorDisplayName($event->createdBy);

        $notification = new ModerationSubmittedNotification(
            tableId: $event->gameTableId,
            tableTitle: $event->title,
            creatorName: $creatorName,
        );

        $this->notifier->sendToEach($this->recipientResolver->getTableModeratorRecipients(), $notification);
    }
}
