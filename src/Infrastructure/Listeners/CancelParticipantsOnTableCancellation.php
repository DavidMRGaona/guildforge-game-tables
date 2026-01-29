<?php

declare(strict_types=1);

namespace Modules\GameTables\Infrastructure\Listeners;

use Illuminate\Support\Facades\Notification;
use Modules\GameTables\Domain\Events\GameTableCancelled;
use Modules\GameTables\Domain\Repositories\GameTableRepositoryInterface;
use Modules\GameTables\Domain\Repositories\ParticipantRepositoryInterface;
use Modules\GameTables\Domain\ValueObjects\GameTableId;
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

            $email = $this->recipientResolver->getParticipantEmail($participant->id->value);
            $name = $this->recipientResolver->getParticipantDisplayName($participant->id->value);

            if ($email === null) {
                continue;
            }

            Notification::route('mail', $email)->notify(
                new TableCancelledNotification(
                    participantName: $name,
                    tableTitle: $event->title,
                    tableDate: $event->startsAt->format('d/m/Y H:i'),
                    tableLocation: $gameTable?->location,
                )
            );
        }
    }
}
