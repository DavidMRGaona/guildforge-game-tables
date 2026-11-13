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

final class CampaignJoinRequestRejectedNotification extends Notification implements HasMailCategory, HasMailSubject, HasSiteNotification, ShouldQueue
{
    use EscapesUserText;
    use Queueable;
    use ShowsOnSite;

    public function __construct(
        private readonly string $userName,
        private readonly string $campaignTitle,
        private readonly string $campaignSlug,
        private readonly ?string $reason,
        // Null in a notification queued before it carried the campaign: mailed, never shown on the site
        public readonly ?string $campaignId = null,
    ) {}

    public function mailCategory(): string
    {
        return GameTablesMailCategories::SIGN_UPS;
    }

    public function mailSubject(): ?MailSubjectDTO
    {
        return GameTablesMailSubjects::campaign($this->campaignId ?? null, $this->campaignTitle);
    }

    public function toSiteNotification(object $notifiable): ?SiteNotificationDTO
    {
        return $this->siteNotice(
            $this->targets()->campaign($this->campaignId ?? null),
            __('game-tables::notices.sign_ups.join_request_rejected', ['campaign' => $this->campaignTitle]),
            $this->reason,
            'ticket',
        );
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject(__('game-tables::emails.campaign_join_request_rejected.subject', ['campaignTitle' => $this->campaignTitle]))
            ->greeting(__('game-tables::emails.campaign_join_request_rejected.greeting', ['name' => $this->userName]))
            ->line(__('game-tables::emails.campaign_join_request_rejected.intro', ['campaignTitle' => $this->campaignTitle]));

        if ($this->reason !== null && $this->reason !== '') {
            $mail->line($this->plainLine(__('game-tables::emails.campaign_join_request_rejected.reason', ['reason' => $this->reason])));
        }

        return $mail->action(
            __('game-tables::emails.campaign_join_request_rejected.view_campaign'),
            route('campaigns.show', $this->campaignSlug),
        );
    }
}
