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

final class CampaignModerationApprovedNotification extends Notification implements HasMailCategory, HasMailSubject, HasSiteNotification, ShouldQueue
{
    use Queueable;
    use ShowsOnSite;

    public function __construct(
        private readonly string $campaignTitle,
        private readonly string $campaignSlug,
        private readonly string $userName,
        private readonly ?string $notes = null,
        // Null in a notification queued before it carried the campaign: mailed, never shown on the site
        public readonly ?string $campaignId = null,
    ) {}

    public function mailCategory(): string
    {
        return GameTablesMailCategories::PROPOSALS;
    }

    public function mailSubject(): ?MailSubjectDTO
    {
        return GameTablesMailSubjects::campaign($this->campaignId ?? null, $this->campaignTitle);
    }

    public function toSiteNotification(object $notifiable): ?SiteNotificationDTO
    {
        return $this->siteNotice(
            $this->targets()->campaign($this->campaignId ?? null),
            __('game-tables::notices.proposals.campaign_approved', ['campaign' => $this->campaignTitle]),
            $this->notes,
            'clipboard-list',
        );
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject(__('game-tables::emails.campaign_moderation_approved.subject', ['campaignTitle' => $this->campaignTitle]))
            ->greeting(__('game-tables::emails.campaign_moderation_approved.greeting', ['name' => $this->userName]))
            ->line(__('game-tables::emails.campaign_moderation_approved.intro', ['campaignTitle' => $this->campaignTitle]));

        if ($this->notes !== null && $this->notes !== '') {
            $message->line('**'.__('game-tables::emails.campaign_moderation_approved.notes_label').':** '.$this->notes);
        }

        return $message
            ->action(__('game-tables::emails.campaign_moderation_approved.view_campaign'), route('campaigns.show', $this->campaignSlug))
            ->line(__('game-tables::emails.campaign_moderation_approved.outro'));
    }
}
