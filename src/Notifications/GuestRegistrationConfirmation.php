<?php

declare(strict_types=1);

namespace Modules\GameTables\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class GuestRegistrationConfirmation extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $firstName,
        private readonly string $tableId,
        private readonly string $tableTitle,
        private readonly ?string $tableDate,
        private readonly ?string $tableLocation,
        private readonly string $cancellationToken,
        private readonly string $role,
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $tableUrl = url("/mesas/{$this->tableId}");
        $cancelUrl = route('gametables.cancel-confirmation', ['token' => $this->cancellationToken]);
        $roleLabel = $this->role === 'player'
            ? __('game-tables::emails.guest_confirmation.role_player')
            : __('game-tables::emails.guest_confirmation.role_spectator');

        $message = (new MailMessage())
            ->subject(__('game-tables::emails.guest_confirmation.subject', ['tableTitle' => $this->tableTitle]))
            ->greeting(__('game-tables::emails.guest_confirmation.greeting', ['name' => $this->firstName]))
            ->line(__('game-tables::emails.guest_confirmation.intro', ['role' => $roleLabel]))
            ->line(__('game-tables::emails.guest_confirmation.details'));

        // Add table details
        $message->line('**' . __('game-tables::emails.guest_confirmation.table_title') . ':** ' . $this->tableTitle);

        if ($this->tableDate !== null) {
            $message->line('**' . __('game-tables::emails.guest_confirmation.table_date') . ':** ' . $this->tableDate);
        }

        if ($this->tableLocation !== null) {
            $message->line('**' . __('game-tables::emails.guest_confirmation.table_location') . ':** ' . $this->tableLocation);
        }

        return $message
            ->action(__('game-tables::emails.guest_confirmation.view_table'), $tableUrl)
            ->line('')
            ->line(__('game-tables::emails.guest_confirmation.cancel_intro'))
            ->line('[' . __('game-tables::emails.guest_confirmation.cancel_button') . '](' . $cancelUrl . ')')
            ->line('')
            ->line(__('game-tables::emails.guest_confirmation.gdpr_notice'));
    }
}
