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

final class TableCancelledNotification extends Notification implements HasMailCategory, HasMailSubject, HasSiteNotification, ShouldQueue
{
    use Queueable;
    use ShowsOnSite;

    public function __construct(
        private readonly string $participantName,
        private readonly string $tableTitle,
        private readonly string $tableDate,
        private readonly ?string $tableLocation,
        // Null in a notification queued before it carried the table: mailed, never shown on the site
        public readonly ?string $tableId = null,
    ) {}

    public function mailCategory(): string
    {
        return GameTablesMailCategories::SIGN_UPS;
    }

    public function mailSubject(): ?MailSubjectDTO
    {
        return GameTablesMailSubjects::table($this->tableId ?? null, $this->tableTitle);
    }

    public function toSiteNotification(object $notifiable): ?SiteNotificationDTO
    {
        return $this->siteNotice(
            $this->targets()->table($this->tableId ?? null),
            __('game-tables::notices.sign_ups.table_cancelled', ['table' => $this->tableTitle]),
            $this->details($this->tableDate, $this->tableLocation),
            'ticket',
        );
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject(__('game-tables::emails.table_cancelled.subject', ['tableTitle' => $this->tableTitle]))
            ->greeting(__('game-tables::emails.table_cancelled.greeting', ['name' => $this->participantName]))
            ->line(__('game-tables::emails.table_cancelled.intro', ['tableTitle' => $this->tableTitle]))
            ->line(__('game-tables::emails.table_cancelled.details'));

        $message->line('**'.__('game-tables::emails.table_cancelled.table_title').':** '.$this->tableTitle);
        $message->line('**'.__('game-tables::emails.table_cancelled.table_date').':** '.$this->tableDate);

        if ($this->tableLocation !== null) {
            $message->line('**'.__('game-tables::emails.table_cancelled.table_location').':** '.$this->tableLocation);
        }

        return $message->line(__('game-tables::emails.table_cancelled.outro'));
    }
}
