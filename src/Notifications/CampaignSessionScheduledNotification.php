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

final class CampaignSessionScheduledNotification extends Notification implements HasMailCategory, HasMailSubject, HasSiteNotification, ShouldQueue
{
    use EscapesUserText;
    use Queueable;
    use ShowsOnSite;

    public function __construct(
        private readonly string $userName,
        private readonly string $campaignTitle,
        private readonly string $tableId,
        private readonly string $tableTitle,
        private readonly string $tableDate,
        private readonly ?string $tableLocation,
        private readonly bool $waiting,
        // Null in a notification queued before it carried the campaign: no subject
        public readonly ?string $campaignId = null,
    ) {}

    public function mailCategory(): string
    {
        return GameTablesMailCategories::CAMPAIGN_NEWS;
    }

    public function mailSubject(): ?MailSubjectDTO
    {
        return GameTablesMailSubjects::campaign($this->campaignId ?? null, $this->campaignTitle);
    }

    public function toSiteNotification(object $notifiable): ?SiteNotificationDTO
    {
        return $this->siteNotice(
            $this->targets()->table($this->tableId),
            __($this->waiting ? 'game-tables::notices.campaign_news.session_waiting' : 'game-tables::notices.campaign_news.session', [
                'campaign' => $this->campaignTitle,
                'date' => $this->tableDate,
            ]),
            $this->details($this->tableTitle, $this->tableLocation),
            'calendar-days',
        );
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject(__('game-tables::emails.campaign_session_scheduled.subject', ['campaignTitle' => $this->campaignTitle, 'date' => $this->tableDate]))
            ->greeting(__('game-tables::emails.campaign_session_scheduled.greeting', ['name' => $this->userName]))
            ->line(__($this->waiting
                ? 'game-tables::emails.campaign_session_scheduled.intro_waiting'
                : 'game-tables::emails.campaign_session_scheduled.intro', ['campaignTitle' => $this->campaignTitle]))
            // The title and the location are the directors' text: shown literally
            ->line($this->plainLabelledLine(__('game-tables::emails.campaign_session_scheduled.table_title'), $this->tableTitle))
            ->line('**'.__('game-tables::emails.campaign_session_scheduled.table_date').':** '.$this->tableDate);

        if ($this->tableLocation !== null) {
            $mail->line($this->plainLabelledLine(__('game-tables::emails.campaign_session_scheduled.table_location'), $this->tableLocation));
        }

        return $mail
            ->line(__('game-tables::emails.campaign_session_scheduled.absence_hint'))
            ->action(__('game-tables::emails.campaign_session_scheduled.view_session'), url("/mesas/{$this->tableId}"));
    }
}
