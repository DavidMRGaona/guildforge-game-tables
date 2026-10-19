<?php

declare(strict_types=1);

namespace Modules\GameTables\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class CampaignModerationApprovedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly string $campaignTitle,
        private readonly string $campaignSlug,
        private readonly string $userName,
        private readonly ?string $notes = null,
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
