<?php

declare(strict_types=1);

namespace Modules\GameTables\Listeners;

use App\Application\Services\TimezoneServiceInterface;
use Modules\GameTables\Application\DTOs\MailRecipientDTO;
use Modules\GameTables\Domain\Entities\GameTable;
use Modules\GameTables\Domain\Events\ParticipantRejected;
use Modules\GameTables\Domain\Repositories\GameTableRepositoryInterface;
use Modules\GameTables\Domain\ValueObjects\GameTableId;
use Modules\GameTables\Infrastructure\Mail\MailRecipientNotifier;
use Modules\GameTables\Infrastructure\Services\NotificationRecipientResolver;
use Modules\GameTables\Notifications\ParticipantRejectedNotification;

final readonly class SendRejectionNotification
{
    public function __construct(
        private GameTableRepositoryInterface $gameTableRepository,
        private NotificationRecipientResolver $recipientResolver,
        private MailRecipientNotifier $notifier,
    ) {}

    public function handle(ParticipantRejected $event): void
    {
        $gameTable = $this->gameTableRepository->find(new GameTableId($event->gameTableId));

        if (! $gameTable instanceof GameTable) {
            return;
        }

        $recipient = $this->recipientResolver->getParticipantRecipient($event->participantId);

        if (! $recipient instanceof MailRecipientDTO) {
            return;
        }

        $participantName = $this->recipientResolver->getParticipantDisplayName($event->participantId);
        $tableDate = app(TimezoneServiceInterface::class)->format($gameTable->timeSlot->startsAt);
        $tableLocation = $gameTable->location;

        $this->notifier->send($recipient, new ParticipantRejectedNotification(
            participantName: $participantName,
            tableTitle: $gameTable->title,
            tableDate: $tableDate,
            tableLocation: $tableLocation,
            reason: $event->reason,
        ));
    }
}
