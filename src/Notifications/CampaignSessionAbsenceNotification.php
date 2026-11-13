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

/**
 * Sent to a roster member whose seat in one session the organisation cancelled (the direction
 * marking an absence, or the panel): they miss this session only and stay in the campaign.
 */
final class CampaignSessionAbsenceNotification extends Notification implements HasMailCategory, HasMailSubject, HasSiteNotification, ShouldQueue
{
    use EscapesUserText;
    use Queueable;
    use ShowsOnSite;

    public function __construct(
        private readonly string $userName,
        private readonly ?string $campaignTitle,
        private readonly string $tableId,
        private readonly string $tableTitle,
        private readonly ?string $tableDate,
        private readonly ?string $tableLocation,
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
            __('game-tables::notices.sign_ups.absence', ['table' => $this->tableTitle]),
            $this->details($this->tableDate, $this->tableLocation),
            'ticket',
        );
    }

    public function toMail(object $notifiable): MailMessage
    {
        // The campaign title, the session title and the location are the directors' text: shown literally
        $intro = $this->campaignTitle !== null
            ? __('game-tables::emails.campaign_session_absence.intro', ['campaignTitle' => $this->campaignTitle])
            : __('game-tables::emails.campaign_session_absence.intro_no_campaign');

        $mail = (new MailMessage)
            ->subject(__('game-tables::emails.campaign_session_absence.subject', ['tableTitle' => $this->tableTitle]))
            ->greeting(__('game-tables::emails.campaign_session_absence.greeting', ['name' => $this->userName]))
            ->line($this->plainLine($intro))
            ->line($this->plainLabelledLine(__('game-tables::emails.campaign_session_absence.table_title'), $this->tableTitle));

        if ($this->tableDate !== null) {
            $mail->line('**'.__('game-tables::emails.campaign_session_absence.table_date').':** '.$this->tableDate);
        }

        if ($this->tableLocation !== null) {
            $mail->line($this->plainLabelledLine(__('game-tables::emails.campaign_session_absence.table_location'), $this->tableLocation));
        }

        return $mail
            ->line(__('game-tables::emails.campaign_session_absence.rejoin_hint'))
            ->action(__('game-tables::emails.campaign_session_absence.view_session'), url("/mesas/{$this->tableId}"));
    }
}
