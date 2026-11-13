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

final class CampaignModerationRejectedNotification extends Notification implements HasMailCategory, HasMailSubject, HasSiteNotification, ShouldQueue
{
    use Queueable;
    use ShowsOnSite;

    public function __construct(
        private readonly string $campaignId,
        private readonly string $campaignTitle,
        private readonly string $userName,
        private readonly string $reason,
    ) {}

    public function mailCategory(): string
    {
        return GameTablesMailCategories::PROPOSALS;
    }

    public function mailSubject(): ?MailSubjectDTO
    {
        return GameTablesMailSubjects::campaign($this->campaignId, $this->campaignTitle);
    }

    public function toSiteNotification(object $notifiable): ?SiteNotificationDTO
    {
        $account = $this->account($notifiable);

        return $account instanceof UserModel ? $this->siteNotice(
            $this->targets()->ownCampaignEdit($account, $this->campaignId),
            __('game-tables::notices.proposals.campaign_rejected', ['campaign' => $this->campaignTitle]),
            $this->reason,
            'clipboard-list',
        ) : null;
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('game-tables::emails.campaign_moderation_rejected.subject', ['campaignTitle' => $this->campaignTitle]))
            ->greeting(__('game-tables::emails.campaign_moderation_rejected.greeting', ['name' => $this->userName]))
            ->line(__('game-tables::emails.campaign_moderation_rejected.intro', ['campaignTitle' => $this->campaignTitle]))
            ->line('**'.__('game-tables::emails.campaign_moderation_rejected.reason_label').':** '.$this->reason)
            ->action(__('game-tables::emails.campaign_moderation_rejected.edit_campaign'), route('campaigns.frontend-edit', $this->campaignId))
            ->line(__('game-tables::emails.campaign_moderation_rejected.outro'));
    }
}
