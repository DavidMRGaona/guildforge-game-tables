<?php

declare(strict_types=1);

namespace Modules\GameTables\Notifications;

use App\Application\Mail\Contracts\HasMailCategory;
use App\Application\Mail\Contracts\HasMailSubject;
use App\Application\Mail\DTOs\MailSubjectDTO;
use App\Application\SiteNotifications\Contracts\HasSiteNotification;
use App\Application\SiteNotifications\DTOs\SiteNotificationDTO;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Modules\GameTables\Infrastructure\Mail\GameTablesMailCategories;
use Modules\GameTables\Infrastructure\Mail\GameTablesMailSubjects;
use Modules\GameTables\Notifications\Concerns\ShowsOnSite;

final class ModerationApprovedNotification extends Notification implements HasMailCategory, HasMailSubject, HasSiteNotification, ShouldQueue
{
    use Queueable;
    use ShowsOnSite;

    public function __construct(
        private readonly string $tableId,
        private readonly string $tableTitle,
        private readonly string $userName,
        private readonly ?string $notes = null,
    ) {}

    public function mailCategory(): string
    {
        return GameTablesMailCategories::PROPOSALS;
    }

    public function mailSubject(): ?MailSubjectDTO
    {
        return GameTablesMailSubjects::table($this->tableId, $this->tableTitle);
    }

    public function toSiteNotification(object $notifiable): ?SiteNotificationDTO
    {
        return $this->siteNotice(
            $this->targets()->table($this->tableId),
            __('game-tables::notices.proposals.table_approved', ['table' => $this->tableTitle]),
            $this->notes,
            'clipboard-list',
        );
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
