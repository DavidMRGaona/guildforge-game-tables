<?php

declare(strict_types=1);

namespace Modules\GameTables\Listeners;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Support\Facades\Notification;
use Modules\GameTables\Domain\Events\ParticipantCancelled;
use Modules\GameTables\Domain\Repositories\GameTableRepositoryInterface;
use Modules\GameTables\Domain\ValueObjects\GameTableId;
use Modules\GameTables\Infrastructure\Services\NotificationRecipientResolver;
use Modules\GameTables\Notifications\CancellationConfirmation;
use Modules\GameTables\Notifications\GuestCancellationConfirmation;
use Modules\GameTables\Notifications\OrganizationCancellationNotification;

final readonly class SendCancellationConfirmation
{
    public function __construct(
        private GameTableRepositoryInterface $gameTableRepository,
        private NotificationRecipientResolver $recipientResolver,
    ) {}

    public function handle(ParticipantCancelled $event): void
    {
        $email = $this->recipientResolver->getParticipantEmail($event->participantId);

        if ($email === null) {
            return;
        }

        $gameTable = $this->gameTableRepository->find(new GameTableId($event->gameTableId));

        if (!$gameTable instanceof \Modules\GameTables\Domain\Entities\GameTable) {
            return;
        }

        $tableDate = $gameTable->timeSlot->startsAt->format('d/m/Y H:i');
        $tableLocation = $gameTable->location;

        // Cancelled by the organisation from the panel: its own notice, to users and guests alike
        if ($event->byOrganization) {
            $participantName = $event->userId !== null
                ? (UserModel::find($event->userId)->name ?? $this->recipientResolver->getParticipantDisplayName($event->participantId))
                : $this->recipientResolver->getParticipantDisplayName($event->participantId);

            Notification::route('mail', $email)
                ->notify(new OrganizationCancellationNotification(
                    participantName: $participantName,
                    tableId: $event->gameTableId,
                    tableTitle: $gameTable->title,
                    tableDate: $tableDate,
                    tableLocation: $tableLocation,
                    guest: $event->userId === null,
                ));

            return;
        }

        // Determine if it's a guest or a registered user
        if ($event->userId !== null) {
            $user = UserModel::find($event->userId);
            $participantName = $user->name ?? $this->recipientResolver->getParticipantDisplayName($event->participantId);

            Notification::route('mail', $email)
                ->notify(new CancellationConfirmation(
                    participantName: $participantName,
                    tableId: $event->gameTableId,
                    tableTitle: $gameTable->title,
                    tableDate: $tableDate,
                    tableLocation: $tableLocation,
                ));
        } else {
            $participantName = $this->recipientResolver->getParticipantDisplayName($event->participantId);

            Notification::route('mail', $email)
                ->notify(new GuestCancellationConfirmation(
                    firstName: $participantName,
                    tableId: $event->gameTableId,
                    tableTitle: $gameTable->title,
                    tableDate: $tableDate,
                    tableLocation: $tableLocation,
                ));
        }
    }
}
