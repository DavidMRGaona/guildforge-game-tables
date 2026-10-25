<?php

declare(strict_types=1);

namespace Modules\GameTables\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class ModerationRejectedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly string $tableId,
        private readonly string $tableTitle,
        private readonly string $userName,
        private readonly string $reason,
        private readonly ?string $tableSlug = null,
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
        // The slug is the canonical form of the link; the edit page also accepts the id. ?? and not
        // a null check: a notification queued before this property existed comes back without it
        // initialised, and reading it directly would throw
        $slug = $this->tableSlug ?? '';
        $key = $slug !== '' ? $slug : $this->tableId;
        $editUrl = url("/mesas/mis-mesas/{$key}/editar");

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
