<?php

declare(strict_types=1);

namespace Modules\GameTables\Infrastructure\Listeners;

use Modules\GameTables\Application\Services\NotificationRecipientResolverInterface;
use Modules\GameTables\Domain\Entities\GameTable;
use Modules\GameTables\Domain\Events\GuestRegistered;
use Modules\GameTables\Domain\Repositories\GameTableRepositoryInterface;
use Modules\GameTables\Domain\ValueObjects\GameTableId;
use Modules\GameTables\Infrastructure\Services\GameTableSettingsReader;
use Modules\GameTables\Notifications\ParticipantRegisteredNotification;

final readonly class NotifyOnGuestRegistration
{
    use NotifiesRegistrationRecipients;

    public function __construct(
        private GameTableSettingsReader $settingsReader,
        private NotificationRecipientResolverInterface $recipientResolver,
        private GameTableRepositoryInterface $gameTableRepository,
    ) {}

    public function handle(GuestRegistered $event): void
    {
        if (! $event->notifyOrganization || ! $this->settingsReader->isNotifyOnRegistrationEnabled()) {
            return;
        }

        $gameTable = $this->gameTableRepository->find(new GameTableId($event->gameTableId));

        if (! $gameTable instanceof GameTable) {
            return;
        }

        $tableDate = $gameTable->timeSlot->startsAt->format('d/m/Y H:i');
        $tableLocation = $gameTable->location;

        $this->notifyRecipients($gameTable, $event->status, fn (?string $manageUrl): ParticipantRegisteredNotification => new ParticipantRegisteredNotification(
            participantName: $event->firstName,
            tableId: $event->gameTableId,
            tableTitle: $gameTable->title,
            tableDate: $tableDate,
            tableLocation: $tableLocation,
            role: $event->role,
            manageUrl: $manageUrl,
        ));
    }
}
