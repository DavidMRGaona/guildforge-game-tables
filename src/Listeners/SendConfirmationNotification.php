<?php

declare(strict_types=1);

namespace Modules\GameTables\Listeners;

use Illuminate\Support\Facades\Notification;
use Modules\GameTables\Domain\Events\ParticipantConfirmed;
use Modules\GameTables\Domain\Repositories\GameTableRepositoryInterface;
use Modules\GameTables\Domain\ValueObjects\GameTableId;
use Modules\GameTables\Infrastructure\Services\NotificationRecipientResolver;
use Modules\GameTables\Notifications\ParticipantConfirmedNotification;

final readonly class SendConfirmationNotification
{
    public function __construct(
        private GameTableRepositoryInterface $gameTableRepository,
        private NotificationRecipientResolver $recipientResolver,
    ) {}

    public function handle(ParticipantConfirmed $event): void
    {
        $gameTable = $this->gameTableRepository->find(new GameTableId($event->gameTableId));

        if ($gameTable === null) {
            return;
        }

        $participantEmail = $this->recipientResolver->getParticipantEmail($event->participantId);

        if ($participantEmail === null) {
            return;
        }

        $participantName = $this->recipientResolver->getParticipantDisplayName($event->participantId);
        $tableDate = $gameTable->timeSlot->startsAt->format('d/m/Y H:i');
        $tableLocation = $gameTable->location;

        Notification::route('mail', $participantEmail)
            ->notify(new ParticipantConfirmedNotification(
                participantName: $participantName,
                tableId: $event->gameTableId,
                tableTitle: $gameTable->title,
                tableDate: $tableDate,
                tableLocation: $tableLocation,
            ));
    }
}
