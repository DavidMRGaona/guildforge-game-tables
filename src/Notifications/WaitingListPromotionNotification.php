<?php

declare(strict_types=1);

namespace Modules\GameTables\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class WaitingListPromotionNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $participantName,
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
        $message = (new MailMessage())
            ->subject(__('game-tables::emails.waiting_list_promotion.subject', ['tableTitle' => $this->tableTitle]))
            ->greeting(__('game-tables::emails.waiting_list_promotion.greeting', ['name' => $this->participantName]))
            ->line(__('game-tables::emails.waiting_list_promotion.intro', ['tableTitle' => $this->tableTitle]));

        $message->line('**' . __('game-tables::emails.waiting_list_promotion.table_title') . ':** ' . $this->tableTitle);

        if ($this->tableDate !== null) {
            $message->line('**' . __('game-tables::emails.waiting_list_promotion.table_date') . ':** ' . $this->tableDate);
        }

        if ($this->tableLocation !== null) {
            $message->line('**' . __('game-tables::emails.waiting_list_promotion.table_location') . ':** ' . $this->tableLocation);
        }

        return $message
            ->line('')
            ->line(__('game-tables::emails.waiting_list_promotion.outro'));
    }
}
