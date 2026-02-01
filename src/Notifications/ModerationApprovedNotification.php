<?php

declare(strict_types=1);

namespace Modules\GameTables\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class ModerationApprovedNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $tableId,
        private readonly string $tableTitle,
        private readonly string $userName,
        private readonly ?string $notes = null,
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

        $message = (new MailMessage)
            ->subject(__('game-tables::emails.moderation_approved.subject', ['tableTitle' => $this->tableTitle]))
            ->greeting(__('game-tables::emails.moderation_approved.greeting', ['name' => $this->userName]))
            ->line(__('game-tables::emails.moderation_approved.intro', ['tableTitle' => $this->tableTitle]))
            ->line('**'.__('game-tables::emails.moderation_approved.table_title').':** '.$this->tableTitle);

        if ($this->notes !== null && $this->notes !== '') {
            $message->line('**'.__('game-tables::emails.moderation_approved.notes_label').':** '.$this->notes);
        }

        return $message
            ->action(__('game-tables::emails.moderation_approved.view_table'), $tableUrl)
            ->line('')
            ->line(__('game-tables::emails.moderation_approved.outro'));
    }
}
