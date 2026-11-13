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

final class WaitingListPromotionNotification extends Notification implements HasMailCategory, HasMailSubject, HasSiteNotification, ShouldQueue
{
    use Queueable;
    use ShowsOnSite;

    public function __construct(
        private readonly string $participantName,
        private readonly string $tableId,
        private readonly string $tableTitle,
        private readonly ?string $tableDate,
        private readonly ?string $tableLocation,
        // False on tables with manual confirmation: pending until the game master confirms
        private readonly bool $confirmed = true,
    ) {}

    public function mailCategory(): string
    {
        return GameTablesMailCategories::SIGN_UPS;
    }

    public function mailSubject(): ?MailSubjectDTO
    {
        return GameTablesMailSubjects::table($this->tableId, $this->tableTitle);
    }

    public function toSiteNotification(object $notifiable): ?SiteNotificationDTO
    {
        return $this->siteNotice(
            $this->targets()->table($this->tableId),
            __($this->confirmed ? 'game-tables::notices.sign_ups.promoted' : 'game-tables::notices.sign_ups.promoted_pending', ['table' => $this->tableTitle]),
            $this->details($this->tableDate, $this->tableLocation),
            'ticket',
        );
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject(__('game-tables::emails.waiting_list_promotion.subject', ['tableTitle' => $this->tableTitle]))
            ->greeting(__('game-tables::emails.waiting_list_promotion.greeting', ['name' => $this->participantName]))
            ->line(__('game-tables::emails.waiting_list_promotion.intro', ['tableTitle' => $this->tableTitle]));

        $message->line('**'.__('game-tables::emails.waiting_list_promotion.table_title').':** '.$this->tableTitle);

        if ($this->tableDate !== null) {
            $message->line('**'.__('game-tables::emails.waiting_list_promotion.table_date').':** '.$this->tableDate);
        }

        if ($this->tableLocation !== null) {
            $message->line('**'.__('game-tables::emails.waiting_list_promotion.table_location').':** '.$this->tableLocation);
        }

        $tableUrl = url("/mesas/{$this->tableId}");

        return $message
            ->action(__('game-tables::emails.waiting_list_promotion.view_table'), $tableUrl)
            ->line('')
            ->line($this->confirmed
                ? __('game-tables::emails.waiting_list_promotion.outro')
                : __('game-tables::emails.waiting_list_promotion.outro_pending'));
    }
}
