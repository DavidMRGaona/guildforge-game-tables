<?php

declare(strict_types=1);

namespace Modules\GameTables\Infrastructure\Listeners;

use App\Application\Services\TimezoneServiceInterface;
use Illuminate\Support\Facades\Notification;
use Modules\GameTables\Application\Services\NotificationRecipientResolverInterface;
use Modules\GameTables\Domain\Entities\GameTable;
use Modules\GameTables\Domain\Events\ParticipantPromotedFromWaitingList;
use Modules\GameTables\Domain\Repositories\GameTableRepositoryInterface;
use Modules\GameTables\Domain\ValueObjects\GameTableId;
use Modules\GameTables\Infrastructure\Services\GameTableSettingsReader;
use Modules\GameTables\Notifications\WaitingListPromotionGmNotification;
use Modules\GameTables\Notifications\WaitingListPromotionNotification;

final readonly class NotifyOnWaitingListPromotion
{
    public function __construct(
        private GameTableSettingsReader $settingsReader,
        private NotificationRecipientResolverInterface $recipientResolver,
        private GameTableRepositoryInterface $gameTableRepository,
    ) {}

    public function handle(ParticipantPromotedFromWaitingList $event): void
    {
        $gameTable = $this->gameTableRepository->find(new GameTableId($event->gameTableId));

        if (! $gameTable instanceof GameTable) {
            return;
        }

        $participantName = $this->recipientResolver->getParticipantDisplayName($event->participantId);
        $participantEmail = $this->recipientResolver->getParticipantEmail($event->participantId);
        $tableDate = app(TimezoneServiceInterface::class)->format($gameTable->timeSlot->startsAt);
        $tableLocation = $gameTable->location;

        // Notify the promoted participant
        if ($participantEmail !== null) {
            Notification::route('mail', $participantEmail)
                ->notify(new WaitingListPromotionNotification(
                    participantName: $participantName,
                    tableId: $event->gameTableId,
                    tableTitle: $gameTable->title,
                    tableDate: $tableDate,
                    tableLocation: $tableLocation,
                    confirmed: $event->confirmed,
                ));
        }

        // The setting only covers the directors' copy, like the registration and cancellation ones
        if (! $this->settingsReader->isNotifyWaitingListPromotionEnabled()) {
            return;
        }

        // Notify GMs and table notification email
        $gmNotification = new WaitingListPromotionGmNotification(
            participantName: $participantName,
            tableId: $event->gameTableId,
            tableTitle: $gameTable->title,
            tableDate: $tableDate,
            tableLocation: $tableLocation,
            confirmed: $event->confirmed,
        );

        $emails = $this->recipientResolver->getGameMasterEmails($event->gameTableId);
        $tableEmail = $this->recipientResolver->getTableNotificationEmail($event->gameTableId);

        if ($tableEmail !== null) {
            $emails[] = $tableEmail;
        }

        $emails = array_unique($emails);

        foreach ($emails as $email) {
            Notification::route('mail', $email)->notify($gmNotification);
        }
    }
}
