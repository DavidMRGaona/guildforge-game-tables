<?php

declare(strict_types=1);

namespace Modules\GameTables\Infrastructure\Listeners;

use Closure;
use Illuminate\Support\Facades\Notification;
use Modules\GameTables\Domain\Entities\GameTable;
use Modules\GameTables\Domain\Enums\ParticipantStatus;
use Modules\GameTables\Notifications\ParticipantRegisteredNotification;

/**
 * Sends the new-registration email to the organisation. When the table delegates its
 * registrations and the registration awaits approval, the linked directors get a link to
 * «Gestionar inscritos»; everyone else gets the plain email. An address never gets two copies.
 */
trait NotifiesRegistrationRecipients
{
    /**
     * @param  Closure(?string): ParticipantRegisteredNotification  $makeNotification
     */
    private function notifyRecipients(GameTable $gameTable, ParticipantStatus $status, Closure $makeNotification): void
    {
        $gameTableId = $gameTable->id->value;

        $emails = [];

        if ($gameTable->delegatesRegistrations && $status === ParticipantStatus::Pending) {
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
