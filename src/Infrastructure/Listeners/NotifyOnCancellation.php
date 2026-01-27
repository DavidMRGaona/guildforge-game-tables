<?php

declare(strict_types=1);

namespace Modules\GameTables\Infrastructure\Listeners;

use Illuminate\Support\Facades\Notification;
use Modules\GameTables\Domain\Events\ParticipantCancelled;
use Modules\GameTables\Domain\Repositories\GameTableRepositoryInterface;
use Modules\GameTables\Domain\ValueObjects\GameTableId;
use Modules\GameTables\Infrastructure\Services\GameTableSettingsReader;
use Modules\GameTables\Infrastructure\Services\NotificationRecipientResolver;
use Modules\GameTables\Notifications\ParticipantCancelledNotification;

final readonly class NotifyOnCancellation
{
    public function __construct(
        private GameTableSettingsReader $settingsReader,
        private NotificationRecipientResolver $recipientResolver,
        private GameTableRepositoryInterface $gameTableRepository,
    ) {}

    public function handle(ParticipantCancelled $event): void
    {
        if (! $this->settingsReader->isNotifyOnCancellationEnabled()) {
            return;
        }

        $gameTable = $this->gameTableRepository->find(new GameTableId($event->gameTableId));

        if ($gameTable === null) {
            return;
        }

        $participantName = $this->recipientResolver->getParticipantDisplayName($event->participantId);
        $tableDate = $gameTable->timeSlot->startsAt->format('d/m/Y H:i');
        $tableLocation = $gameTable->location;

        $notification = new ParticipantCancelledNotification(
            participantName: $participantName,
            tableTitle: $gameTable->title,
            tableDate: $tableDate,
            tableLocation: $tableLocation,
        );

        $emails = $this->recipientResolver->getGameMasterEmails($event->gameTableId);
        $tableEmail = $this->recipientResolver->getTableNotificationEmail($event->gameTableId);

        if ($tableEmail !== null) {
            $emails[] = $tableEmail;
        }

        $emails = array_unique($emails);

        foreach ($emails as $email) {
            Notification::route('mail', $email)->notify($notification);
        }
    }
}
