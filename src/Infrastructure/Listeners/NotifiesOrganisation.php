<?php

declare(strict_types=1);

namespace Modules\GameTables\Infrastructure\Listeners;

use Closure;
use Illuminate\Notifications\Notification as BaseNotification;
use Illuminate\Support\Facades\Notification;
use Modules\GameTables\Domain\Entities\GameTable;

/**
 * Sends a registration email (a new registration, a promotion from the waiting list) to the
 * organisation. When the table delegates its registrations and the registration awaits approval,
 * the linked directors get a link to «Gestionar inscritos»; everyone else gets the plain email. An
 * address never gets two copies.
 */
trait NotifiesOrganisation
{
    /**
     * @param  Closure(?string): BaseNotification  $makeNotification  given the manage URL, or null
     */
    private function notifyOrganisation(GameTable $gameTable, bool $awaitsApproval, Closure $makeNotification): void
    {
        $gameTableId = $gameTable->id->value;

        $emails = [];

        if ($gameTable->delegatesRegistrations && $awaitsApproval) {
            $manageUrl = url("/mesas/mis-mesas/{$gameTable->slug}/inscritos");

            foreach ($this->recipientResolver->getDirectorEmails($gameTableId) as $email) {
                $emails[strtolower($email)] ??= [$email, $manageUrl];
            }
        }

        $others = $this->recipientResolver->getGameMasterEmails($gameTableId);
        $tableEmail = $this->recipientResolver->getTableNotificationEmail($gameTableId);

        if ($tableEmail !== null) {
            $others[] = $tableEmail;
        }

        foreach ($others as $email) {
            $emails[strtolower($email)] ??= [$email, null];
        }

        foreach ($emails as [$email, $manageUrl]) {
            Notification::route('mail', $email)->notify($makeNotification($manageUrl));
        }
    }
}
