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
use Modules\GameTables\Filament\Resources\CampaignResource;
use Modules\GameTables\Infrastructure\Mail\GameTablesMailCategories;
use Modules\GameTables\Infrastructure\Mail\GameTablesMailSubjects;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\CampaignModel;
use Modules\GameTables\Notifications\Concerns\ShowsOnSite;

final class CampaignModerationSubmittedNotification extends Notification implements HasMailCategory, HasMailSubject, HasSiteNotification, ShouldQueue
{
    use Queueable;
    use ShowsOnSite;

    public function __construct(
        private readonly string $campaignId,
        private readonly string $campaignTitle,
        private readonly string $creatorName,
        // The author's account, so a moderator never gets their own proposal and anonymizing
        // the author forgets the notice
        public readonly ?string $creatorId = null,
    ) {}

    public function mailCategory(): string
    {
        return GameTablesMailCategories::MODERATION;
    }

    public function mailSubject(): ?MailSubjectDTO
    {
        return GameTablesMailSubjects::campaign($this->campaignId, $this->campaignTitle);
    }

    public function toSiteNotification(object $notifiable): ?SiteNotificationDTO
    {
        $account = $this->account($notifiable);

        return $account instanceof UserModel ? $this->siteNotice(
            $this->targets()->campaignReview($account, $this->campaignId),
            __('game-tables::notices.moderation.campaign', ['campaign' => $this->campaignTitle]),
            $this->creatorName !== '' ? __('game-tables::notices.moderation.proposed_by', ['name' => $this->creatorName]) : null,
            'shield-check',
            self::MODERATION_GROUP,
            $this->creatorId ?? null,
            $this->moderationGroupedTitle(),
        ) : null;
    }

    public function toMail(object $notifiable): MailMessage
    {
        // Without the campaign (deleted meanwhile), the list of campaigns
        $reviewUrl = CampaignModel::query()->whereKey($this->campaignId)->exists()
            ? CampaignResource::getUrl('edit', ['record' => $this->campaignId])
            : CampaignResource::getUrl('index');

        return (new MailMessage)
            ->subject(__('game-tables::emails.campaign_moderation_submitted.subject', ['campaignTitle' => $this->campaignTitle]))
            ->greeting(__('game-tables::emails.campaign_moderation_submitted.greeting'))
            ->line(__('game-tables::emails.campaign_moderation_submitted.intro'))
            ->line('**'.__('game-tables::emails.campaign_moderation_submitted.campaign_title').':** '.$this->campaignTitle)
            ->line('**'.__('game-tables::emails.campaign_moderation_submitted.created_by').':** '.$this->creatorName)
            ->action(__('game-tables::emails.campaign_moderation_submitted.review_campaign'), $reviewUrl)
            ->line('')
            ->line(__('game-tables::emails.campaign_moderation_submitted.outro'));
    }
}
