<?php

declare(strict_types=1);

namespace Modules\GameTables\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Modules\GameTables\Filament\Resources\CampaignResource;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\CampaignModel;

final class CampaignModerationSubmittedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly string $campaignId,
        private readonly string $campaignTitle,
        private readonly string $creatorName,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
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
