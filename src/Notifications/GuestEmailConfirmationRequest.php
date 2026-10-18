<?php

declare(strict_types=1);

namespace Modules\GameTables\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Asks a guest to confirm the registration they sent from the public form.
 */
final class GuestEmailConfirmationRequest extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly string $firstName,
        private readonly string $tableTitle,
        private readonly ?string $tableDate,
        private readonly string $confirmationUrl,
        private readonly bool $holdsPlace,
        private readonly ?string $deadline,
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
        $message = (new MailMessage)
            ->subject(__('game-tables::emails.guest_email_confirmation.subject', ['tableTitle' => $this->tableTitle]))
            ->greeting(__('game-tables::emails.guest_email_confirmation.greeting', ['name' => $this->firstName]))
            ->line(__('game-tables::emails.guest_email_confirmation.intro', ['tableTitle' => $this->tableTitle]));

        if ($this->tableDate !== null) {
            $message->line('**'.__('game-tables::emails.guest_confirmation.table_date').':** '.$this->tableDate);
        }

        $message->line($this->holdsPlace && $this->deadline !== null
            ? __('game-tables::emails.guest_email_confirmation.deadline', ['deadline' => $this->deadline])
            : __('game-tables::emails.guest_email_confirmation.unguaranteed'));

        return $message
            ->action(__('game-tables::emails.guest_email_confirmation.button'), $this->confirmationUrl)
            ->line(__('game-tables::emails.guest_email_confirmation.ignore'));
    }
}
