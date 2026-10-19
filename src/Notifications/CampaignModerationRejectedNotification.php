<?php

declare(strict_types=1);

namespace Modules\GameTables\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class CampaignModerationRejectedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly string $campaignId,
        private readonly string $campaignTitle,
        private readonly string $userName,
        private readonly string $reason,
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
        return (new MailMessage)
            ->subject(__('game-tables::emails.campaign_moderation_rejected.subject', ['campaignTitle' => $this->campaignTitle]))
            ->greeting(__('game-tables::emails.campaign_moderation_rejected.greeting', ['name' => $this->userName]))
            ->line(__('game-tables::emails.campaign_moderation_rejected.intro', ['campaignTitle' => $this->campaignTitle]))
            ->line('**'.__('game-tables::emails.campaign_moderation_rejected.reason_label').':** '.$this->reason)
            ->action(__('game-tables::emails.campaign_moderation_rejected.edit_campaign'), route('campaigns.frontend-edit', $this->campaignId))
            ->line(__('game-tables::emails.campaign_moderation_rejected.outro'));
    }
}
