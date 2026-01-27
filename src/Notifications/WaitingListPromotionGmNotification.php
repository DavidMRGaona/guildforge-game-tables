<?php

declare(strict_types=1);

namespace Modules\GameTables\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class WaitingListPromotionGmNotification extends Notification
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
            ->subject(__('game-tables::emails.waiting_list_promotion_gm.subject', ['tableTitle' => $this->tableTitle]))
            ->greeting(__('game-tables::emails.waiting_list_promotion_gm.greeting'))
            ->line(__('game-tables::emails.waiting_list_promotion_gm.intro', [
                'name' => $this->participantName,
                'tableTitle' => $this->tableTitle,
            ]));

        $message->line('**' . __('game-tables::emails.waiting_list_promotion_gm.table_title') . ':** ' . $this->tableTitle);

        if ($this->tableDate !== null) {
            $message->line('**' . __('game-tables::emails.waiting_list_promotion_gm.table_date') . ':** ' . $this->tableDate);
        }

        if ($this->tableLocation !== null) {
            $message->line('**' . __('game-tables::emails.waiting_list_promotion_gm.table_location') . ':** ' . $this->tableLocation);
        }

        return $message;
    }
}
