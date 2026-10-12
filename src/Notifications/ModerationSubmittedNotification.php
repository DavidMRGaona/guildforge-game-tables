<?php

declare(strict_types=1);

namespace Modules\GameTables\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Modules\GameTables\Filament\Resources\GameTableResource;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameTableModel;

final class ModerationSubmittedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly string $tableId,
        private readonly string $tableTitle,
        private readonly string $creatorName,
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
        // The panel finds game tables by slug; without one, the list of tables to review
        $slug = GameTableModel::query()->whereKey($this->tableId)->value('slug');
        $reviewUrl = is_string($slug) && $slug !== ''
            ? GameTableResource::getUrl('edit', ['record' => $slug])
            : GameTableResource::getUrl('index');

        return (new MailMessage)
            ->subject(__('game-tables::emails.moderation_submitted.subject', ['tableTitle' => $this->tableTitle]))
            ->greeting(__('game-tables::emails.moderation_submitted.greeting'))
            ->line(__('game-tables::emails.moderation_submitted.intro'))
            ->line('**'.__('game-tables::emails.moderation_submitted.table_title').':** '.$this->tableTitle)
            ->line('**'.__('game-tables::emails.moderation_submitted.created_by').':** '.$this->creatorName)
            ->action(__('game-tables::emails.moderation_submitted.review_table'), $reviewUrl)
            ->line('')
            ->line(__('game-tables::emails.moderation_submitted.outro'));
    }
}
