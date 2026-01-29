<?php

declare(strict_types=1);

namespace Modules\GameTables\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class GuestCancellationConfirmation extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $firstName,
        private readonly string $tableId,
        private readonly string $tableTitle,
        private readonly ?string $tableDate,
        private readonly ?string $tableLocation,
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
        $tableUrl = url("/mesas/{$this->tableId}");

        $message = (new MailMessage())
            ->subject(__('game-tables::emails.cancellation_confirmation.subject', ['tableTitle' => $this->tableTitle]))
            ->greeting(__('game-tables::emails.cancellation_confirmation.greeting', ['name' => $this->firstName]))
            ->line(__('game-tables::emails.cancellation_confirmation.intro'))
            ->line(__('game-tables::emails.cancellation_confirmation.details'));

        $message->line('**' . __('game-tables::emails.cancellation_confirmation.table_title') . ':** ' . $this->tableTitle);

        if ($this->tableDate !== null) {
            $message->line('**' . __('game-tables::emails.cancellation_confirmation.table_date') . ':** ' . $this->tableDate);
        }

        if ($this->tableLocation !== null) {
            $message->line('**' . __('game-tables::emails.cancellation_confirmation.table_location') . ':** ' . $this->tableLocation);
        }

        return $message
            ->action(__('game-tables::emails.cancellation_confirmation.view_table'), $tableUrl)
            ->line(__('game-tables::emails.cancellation_confirmation.guest_gdpr_notice'));
    }
}
