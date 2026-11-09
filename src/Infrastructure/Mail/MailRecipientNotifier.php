<?php

declare(strict_types=1);

namespace Modules\GameTables\Infrastructure\Mail;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Notification as Notifications;
use Modules\GameTables\Application\DTOs\MailRecipientDTO;

/**
 * Sends to an account as the account, so the core applies its mail choices, footer and
 * unsubscribe link; only a plain address (guest, external game master, table mailbox) is
 * sent to by address.
 */
final class MailRecipientNotifier
{
    public function send(MailRecipientDTO $recipient, Notification $notification): void
    {
        if ($recipient->userId !== null) {
            // The account is gone: its address is not ours to write to any more
            UserModel::query()->find($recipient->userId)?->notify($notification);

            return;
        }

        Notifications::route('mail', $recipient->email)->notify($notification);
    }

    /**
     * @param  iterable<MailRecipientDTO>  $recipients
     */
    public function sendToEach(iterable $recipients, Notification $notification): void
    {
        foreach ($recipients as $recipient) {
            $this->send($recipient, $notification);
        }
    }
}
