<?php

declare(strict_types=1);

namespace Modules\GameTables\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class TableCancelledNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $participantName,
        private readonly string $tableTitle,
        private readonly string $tableDate,
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
        $message = (new MailMessage())
            ->subject(__('game-tables::emails.table_cancelled.subject', ['tableTitle' => $this->tableTitle]))
            ->greeting(__('game-tables::emails.table_cancelled.greeting', ['name' => $this->participantName]))
            ->line(__('game-tables::emails.table_cancelled.intro', ['tableTitle' => $this->tableTitle]))
            ->line(__('game-tables::emails.table_cancelled.details'));

        $message->line('**' . __('game-tables::emails.table_cancelled.table_title') . ':** ' . $this->tableTitle);
        $message->line('**' . __('game-tables::emails.table_cancelled.table_date') . ':** ' . $this->tableDate);

        if ($this->tableLocation !== null) {
            $message->line('**' . __('game-tables::emails.table_cancelled.table_location') . ':** ' . $this->tableLocation);
        }

        return $message->line(__('game-tables::emails.table_cancelled.outro'));
    }
}
