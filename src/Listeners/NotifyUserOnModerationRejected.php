<?php

declare(strict_types=1);

namespace Modules\GameTables\Listeners;

use Illuminate\Support\Facades\Notification;
use Modules\GameTables\Domain\Events\GameTableModerationRejected;
use Modules\GameTables\Domain\Repositories\GameTableRepositoryInterface;
use Modules\GameTables\Domain\ValueObjects\GameTableId;
use Modules\GameTables\Infrastructure\Services\NotificationRecipientResolver;
use Modules\GameTables\Notifications\ModerationRejectedNotification;

final readonly class NotifyUserOnModerationRejected
{
    public function __construct(
        private NotificationRecipientResolver $recipientResolver,
        private GameTableRepositoryInterface $gameTableRepository,
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
            // The link's canonical form; without it the email links by id, which also works
            tableSlug: $this->gameTableRepository->find(new GameTableId($event->gameTableId))?->slug,
        );

        Notification::route('mail', $creatorEmail)->notify($notification);
    }
}
