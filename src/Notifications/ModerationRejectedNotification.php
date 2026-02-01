<?php

declare(strict_types=1);

namespace Modules\GameTables\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class ModerationRejectedNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $tableId,
        private readonly string $tableTitle,
        private readonly string $userName,
        private readonly string $reason,
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
        $editUrl = url("/perfil?tab=gametables-created");

        return (new MailMessage)
            ->subject(__('game-tables::emails.moderation_rejected.subject', ['tableTitle' => $this->tableTitle]))
            ->greeting(__('game-tables::emails.moderation_rejected.greeting', ['name' => $this->userName]))
            ->line(__('game-tables::emails.moderation_rejected.intro', ['tableTitle' => $this->tableTitle]))
            ->line('**'.__('game-tables::emails.moderation_rejected.table_title').':** '.$this->tableTitle)
            ->line('**'.__('game-tables::emails.moderation_rejected.reason_label').':** '.$this->reason)
            ->action(__('game-tables::emails.moderation_rejected.edit_table'), $editUrl)
            ->line('')
            ->line(__('game-tables::emails.moderation_rejected.outro'));
    }
}
