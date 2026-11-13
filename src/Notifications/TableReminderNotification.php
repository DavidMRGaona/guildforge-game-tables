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

final class TableReminderNotification extends Notification implements HasMailCategory, HasMailSubject, HasSiteNotification, ShouldQueue
{
    use Queueable;
    use ShowsOnSite;

    public function __construct(
        private readonly string $participantName,
        private readonly string $tableTitle,
        private readonly string $tableSlug,
        private readonly string $tableDate,
        private readonly ?string $tableLocation,
        private readonly ?string $cancellationToken = null,
        // Null in a notification queued before it carried the table: mailed, never shown on the site
        public readonly ?string $tableId = null,
    ) {}

    public function mailCategory(): string
    {
        return GameTablesMailCategories::REMINDERS;
    }

    public function mailSubject(): ?MailSubjectDTO
    {
        return GameTablesMailSubjects::table($this->tableId ?? null, $this->tableTitle);
    }

    public function toSiteNotification(object $notifiable): ?SiteNotificationDTO
    {
        return $this->siteNotice(
            $this->targets()->table($this->tableId ?? null),
            __('game-tables::notices.reminder', ['table' => $this->tableTitle, 'date' => $this->tableDate]),
            $this->tableLocation,
            'calendar-check',
        );
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject(__('game-tables::emails.table_reminder.subject', [
                'tableTitle' => $this->tableTitle,
                'date' => $this->tableDate,
            ]))
            ->greeting(trim($this->participantName) !== ''
                ? __('game-tables::emails.table_reminder.greeting', ['name' => $this->participantName])
                : __('game-tables::emails.table_reminder.greeting_anonymous'))
            ->line(__('game-tables::emails.table_reminder.intro', [
                'tableTitle' => $this->tableTitle,
                'date' => $this->tableDate,
            ]));

        if ($this->tableLocation !== null && $this->tableLocation !== '') {
            $message->line('**'.__('game-tables::emails.table_reminder.location').':** '.$this->tableLocation);
        }

        $message->action(__('game-tables::emails.table_reminder.view_table'), route('gametables.show', $this->tableSlug));

        $message->line($this->cancellationToken !== null
            ? __('game-tables::emails.table_reminder.cancel_guest', [
                'url' => route('gametables.cancel-confirmation', ['token' => $this->cancellationToken]),
            ])
            : __('game-tables::emails.table_reminder.cancel'));

        return $message;
    }
}
