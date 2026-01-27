<?php

declare(strict_types=1);

namespace Modules\GameTables\Infrastructure\Listeners;

use Illuminate\Support\Facades\Notification;
use Modules\GameTables\Domain\Events\ParticipantRegistered;
use Modules\GameTables\Domain\Repositories\GameTableRepositoryInterface;
use Modules\GameTables\Domain\ValueObjects\GameTableId;
use Modules\GameTables\Infrastructure\Services\GameTableSettingsReader;
use Modules\GameTables\Infrastructure\Services\NotificationRecipientResolver;
use Modules\GameTables\Notifications\ParticipantRegisteredNotification;

final readonly class NotifyOnRegistration
{
    public function __construct(
        private GameTableSettingsReader $settingsReader,
        private NotificationRecipientResolver $recipientResolver,
        private GameTableRepositoryInterface $gameTableRepository,
    ) {}

    public function handle(ParticipantRegistered $event): void
    {
        if (! $this->settingsReader->isNotifyOnRegistrationEnabled()) {
            return;
        }

        $gameTable = $this->gameTableRepository->find(new GameTableId($event->gameTableId));

        if ($gameTable === null) {
            return;
        }

        $participantName = $this->recipientResolver->getParticipantDisplayName($event->participantId);
        $tableDate = $gameTable->timeSlot->startsAt->format('d/m/Y H:i');
        $tableLocation = $gameTable->location;

        $notification = new ParticipantRegisteredNotification(
            participantName: $participantName,
            tableTitle: $gameTable->title,
            tableDate: $tableDate,
            tableLocation: $tableLocation,
            role: $event->role,
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
