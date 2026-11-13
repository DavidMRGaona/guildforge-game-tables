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

final class CampaignRosterRemovedNotification extends Notification implements HasMailCategory, HasMailSubject, HasSiteNotification, ShouldQueue
{
    use Queueable;
    use ShowsOnSite;

    public function __construct(
        private readonly string $userName,
        private readonly string $campaignTitle,
        private readonly string $campaignSlug,
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
            __('game-tables::notices.sign_ups.roster_removed', ['campaign' => $this->campaignTitle]),
            icon: 'ticket',
        );
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('game-tables::emails.campaign_roster_removed.subject', ['campaignTitle' => $this->campaignTitle]))
            ->greeting(__('game-tables::emails.campaign_roster_removed.greeting', ['name' => $this->userName]))
            ->line(__('game-tables::emails.campaign_roster_removed.intro', ['campaignTitle' => $this->campaignTitle]))
            ->action(__('game-tables::emails.campaign_roster_removed.view_campaign'), route('campaigns.show', $this->campaignSlug));
    }
}
