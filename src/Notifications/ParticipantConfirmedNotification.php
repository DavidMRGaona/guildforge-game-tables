<?php

declare(strict_types=1);

namespace Modules\GameTables\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class ParticipantConfirmedNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $participantName,
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
        $message = (new MailMessage)
            ->subject(__('game-tables::emails.confirmation.subject', ['tableTitle' => $this->tableTitle]))
            ->greeting(__('game-tables::emails.confirmation.greeting', ['name' => $this->participantName]))
            ->line(__('game-tables::emails.confirmation.intro', ['tableTitle' => $this->tableTitle]));

        $message->line('**'.__('game-tables::emails.confirmation.table_title').':** '.$this->tableTitle);

        if ($this->tableDate !== null) {
            $message->line('**'.__('game-tables::emails.confirmation.table_date').':** '.$this->tableDate);
        }

        if ($this->tableLocation !== null) {
            $message->line('**'.__('game-tables::emails.confirmation.table_location').':** '.$this->tableLocation);
        }

        $tableUrl = url("/mesas/{$this->tableId}");

        return $message
            ->action(__('game-tables::emails.confirmation.view_table'), $tableUrl)
            ->line('')
            ->line(__('game-tables::emails.confirmation.outro'));
    }
}
