<?php

declare(strict_types=1);

namespace Modules\GameTables\Notifications;

use App\Application\Mail\Contracts\HasMailCategory;
use App\Application\Mail\Contracts\HasMailSubject;
use App\Application\Mail\DTOs\MailSubjectDTO;
use App\Application\SiteNotifications\Contracts\HasSiteNotification;
use App\Application\SiteNotifications\DTOs\SiteNotificationDTO;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Modules\GameTables\Infrastructure\Mail\GameTablesMailCategories;
use Modules\GameTables\Infrastructure\Mail\GameTablesMailSubjects;
use Modules\GameTables\Notifications\Concerns\ShowsOnSite;

final class ModerationRejectedNotification extends Notification implements HasMailCategory, HasMailSubject, HasSiteNotification, ShouldQueue
{
    use Queueable;
    use ShowsOnSite;

    public function __construct(
        private readonly string $tableId,
        private readonly string $tableTitle,
        private readonly string $userName,
        private readonly string $reason,
        private readonly ?string $tableSlug = null,
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
        $account = $this->account($notifiable);

        return $account instanceof UserModel ? $this->siteNotice(
            $this->targets()->ownTableEdit($account, $this->tableId),
            __('game-tables::notices.proposals.table_rejected', ['table' => $this->tableTitle]),
            $this->reason,
            'clipboard-list',
        ) : null;
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
