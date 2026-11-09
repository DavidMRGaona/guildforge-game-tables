<?php

declare(strict_types=1);

namespace Modules\GameTables\Infrastructure\Listeners;

use App\Application\Services\TimezoneServiceInterface;
use Modules\GameTables\Application\DTOs\MailRecipientDTO;
use Modules\GameTables\Domain\Events\GameTableCancelled;
use Modules\GameTables\Domain\Repositories\GameTableRepositoryInterface;
use Modules\GameTables\Domain\Repositories\ParticipantRepositoryInterface;
use Modules\GameTables\Domain\ValueObjects\GameTableId;
use Modules\GameTables\Infrastructure\Mail\MailRecipientNotifier;
use Modules\GameTables\Infrastructure\Services\NotificationRecipientResolver;
use Modules\GameTables\Notifications\TableCancelledNotification;

final readonly class CancelParticipantsOnTableCancellation
{
    public function __construct(
        private ParticipantRepositoryInterface $participantRepository,
        private GameTableRepositoryInterface $gameTableRepository,
        private NotificationRecipientResolver $recipientResolver,
    ) {}

    public function handle(GameTableCancelled $event): void
    {
        $gameTable = $this->gameTableRepository->find(new GameTableId($event->gameTableId));

        $allParticipants = $this->participantRepository->getByGameTable(new GameTableId($event->gameTableId));
        $participants = array_filter($allParticipants, fn ($participant) => $participant->canBeCancelled());

        foreach ($participants as $participant) {
            $participant->cancel();
            $this->participantRepository->save($participant);

            $recipient = $this->recipientResolver->getParticipantRecipient($participant->id->value);
            $name = $this->recipientResolver->getParticipantDisplayName($participant->id->value);

            if (! $recipient instanceof MailRecipientDTO) {
                continue;
            }

            app(MailRecipientNotifier::class)->send($recipient, new TableCancelledNotification(
                participantName: $name,
                tableTitle: $event->title,
                tableDate: app(TimezoneServiceInterface::class)->format($event->startsAt),
                tableLocation: $gameTable?->location,
            ));
        }
    }
}
