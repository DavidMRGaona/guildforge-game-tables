<?php

declare(strict_types=1);

namespace Modules\GameTables\Infrastructure\Listeners;

use Closure;
use Illuminate\Notifications\Notification as BaseNotification;
use Modules\GameTables\Application\DTOs\MailRecipientDTO;
use Modules\GameTables\Domain\Entities\GameTable;
use Modules\GameTables\Infrastructure\Mail\MailRecipientNotifier;

/**
 * Sends a registration email (a new registration, a promotion from the waiting list) to the
 * organisation. When the table delegates its registrations and the registration awaits approval,
 * the linked directors get a link to «Gestionar inscritos»; everyone else gets the plain email. An
 * address never gets two copies. Game masters with an account get it as the account, so their
 * mail choices apply; the table's mailbox and external game masters by address.
 */
trait NotifiesOrganisation
{
    /**
     * @param  Closure(?string): BaseNotification  $makeNotification  given the manage URL, or null
     */
    private function notifyOrganisation(GameTable $gameTable, bool $awaitsApproval, Closure $makeNotification): void
    {
        $gameTableId = $gameTable->id->value;

        /** @var array<string, array{MailRecipientDTO, ?string}> $recipients */
        $recipients = [];

        if ($gameTable->delegatesRegistrations && $awaitsApproval) {
            $manageUrl = url("/mesas/mis-mesas/{$gameTable->slug}/inscritos");

            foreach ($this->recipientResolver->getDirectorRecipients($gameTableId) as $recipient) {
                $recipients[mb_strtolower($recipient->email)] ??= [$recipient, $manageUrl];
            }
        }

        $others = $this->recipientResolver->getGameMasterRecipients($gameTableId);
        $tableEmail = $this->recipientResolver->getTableNotificationEmail($gameTableId);

        if ($tableEmail !== null && $tableEmail !== '') {
            $others[] = MailRecipientDTO::address($tableEmail);
        }

        foreach ($others as $recipient) {
            $recipients[mb_strtolower($recipient->email)] ??= [$recipient, null];
        }

        $notifier = app(MailRecipientNotifier::class);

        foreach ($recipients as [$recipient, $manageUrl]) {
            $notifier->send($recipient, $makeNotification($manageUrl));
        }
    }
}
