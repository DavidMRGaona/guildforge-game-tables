<?php

declare(strict_types=1);

namespace Modules\GameTables\Infrastructure\Listeners;

use App\Application\Services\TimezoneServiceInterface;
use Modules\GameTables\Application\DTOs\MailRecipientDTO;
use Modules\GameTables\Application\Services\NotificationRecipientResolverInterface;
use Modules\GameTables\Domain\Entities\GameTable;
use Modules\GameTables\Domain\Events\ParticipantCancelled;
use Modules\GameTables\Domain\Repositories\GameTableRepositoryInterface;
use Modules\GameTables\Domain\ValueObjects\GameTableId;
use Modules\GameTables\Infrastructure\Mail\MailRecipientNotifier;
use Modules\GameTables\Infrastructure\Services\GameTableSettingsReader;
use Modules\GameTables\Notifications\ParticipantCancelledNotification;

final readonly class NotifyOnCancellation
{
    public function __construct(
        private GameTableSettingsReader $settingsReader,
        private NotificationRecipientResolverInterface $recipientResolver,
        private GameTableRepositoryInterface $gameTableRepository,
    ) {}

    public function handle(ParticipantCancelled $event): void
    {
        // Cancelled by the organisation from the panel: the directors are not told
        if ($event->byOrganization || ! $this->settingsReader->isNotifyOnCancellationEnabled()) {
            return;
        }

        $gameTable = $this->gameTableRepository->find(new GameTableId($event->gameTableId));

        if (! $gameTable instanceof GameTable) {
            return;
        }

        $participantName = $this->recipientResolver->getParticipantDisplayName($event->participantId);
        $tableDate = app(TimezoneServiceInterface::class)->format($gameTable->timeSlot->startsAt);
        $tableLocation = $gameTable->location;

        $notification = new ParticipantCancelledNotification(
            participantName: $participantName,
            tableId: $event->gameTableId,
            tableTitle: $gameTable->title,
            tableDate: $tableDate,
            tableLocation: $tableLocation,
        );

        $recipients = [];

        foreach ($this->recipientResolver->getGameMasterRecipients($event->gameTableId) as $recipient) {
            $recipients[mb_strtolower($recipient->email)] ??= $recipient;
        }

        $tableEmail = $this->recipientResolver->getTableNotificationEmail($event->gameTableId);

        if ($tableEmail !== null && $tableEmail !== '') {
            $recipients[mb_strtolower($tableEmail)] ??= MailRecipientDTO::address($tableEmail);
        }

        // Game masters with an account as the account, so their mail choices apply
        app(MailRecipientNotifier::class)->sendToEach($recipients, $notification);
    }
}
