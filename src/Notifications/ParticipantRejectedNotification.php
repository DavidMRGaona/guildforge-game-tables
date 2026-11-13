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
use Modules\GameTables\Notifications\Concerns\EscapesUserText;
use Modules\GameTables\Notifications\Concerns\ShowsOnSite;

final class ParticipantRejectedNotification extends Notification implements HasMailCategory, HasMailSubject, HasSiteNotification, ShouldQueue
{
    use EscapesUserText;
    use Queueable;
    use ShowsOnSite;

    public function __construct(
        private readonly string $participantName,
        private readonly string $tableTitle,
        private readonly ?string $tableDate,
        private readonly ?string $tableLocation,
        public readonly ?string $reason = null,
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
            __('game-tables::notices.sign_ups.rejected', ['table' => $this->tableTitle]),
            $this->reason !== null && $this->reason !== '' ? $this->reason : $this->details($this->tableDate, $this->tableLocation),
            'ticket',
        );
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject(__('game-tables::emails.rejection.subject', ['tableTitle' => $this->tableTitle]))
            ->greeting(__('game-tables::emails.rejection.greeting', ['name' => $this->participantName]))
            ->line(__('game-tables::emails.rejection.intro', ['tableTitle' => $this->tableTitle]));

        $message->line('**'.__('game-tables::emails.rejection.table_title').':** '.$this->tableTitle);

        if ($this->tableDate !== null) {
            $message->line('**'.__('game-tables::emails.rejection.table_date').':** '.$this->tableDate);
        }

        if ($this->tableLocation !== null) {
            $message->line('**'.__('game-tables::emails.rejection.table_location').':** '.$this->tableLocation);
        }

        if ($this->reason !== null && $this->reason !== '') {
            $message->line($this->plainLine(__('game-tables::emails.rejection.reason', ['reason' => $this->reason])));
        }

        return $message
            ->line('')
            ->line(__('game-tables::emails.rejection.outro'));
    }
}
