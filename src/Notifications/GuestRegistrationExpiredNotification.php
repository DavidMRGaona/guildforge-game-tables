<?php

declare(strict_types=1);

namespace Modules\GameTables\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells a guest that their unconfirmed registration lapsed and the place was released.
 */
final class GuestRegistrationExpiredNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly string $firstName,
        private readonly string $tableTitle,
        private readonly string $tableUrl,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('game-tables::emails.guest_registration_expired.subject', ['tableTitle' => $this->tableTitle]))
            ->greeting(__('game-tables::emails.guest_registration_expired.greeting', ['name' => $this->firstName]))
            ->line(__('game-tables::emails.guest_registration_expired.intro', ['tableTitle' => $this->tableTitle]))
            ->line(__('game-tables::emails.guest_registration_expired.retry'))
            ->action(__('game-tables::emails.guest_registration_expired.button'), $this->tableUrl);
    }
}
