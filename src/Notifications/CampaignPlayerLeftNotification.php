<?php

declare(strict_types=1);

namespace Modules\GameTables\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class CampaignPlayerLeftNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly string $userName,
        private readonly string $campaignId,
        private readonly string $campaignTitle,
        private readonly string $playerName,
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
            ->subject(__('game-tables::emails.campaign_player_left.subject', ['player' => $this->playerName, 'campaignTitle' => $this->campaignTitle]))
            ->greeting(__('game-tables::emails.campaign_player_left.greeting', ['name' => $this->userName]))
            ->line(__('game-tables::emails.campaign_player_left.intro', ['player' => $this->playerName, 'campaignTitle' => $this->campaignTitle]))
            ->action(__('game-tables::emails.campaign_player_left.view_roster'), url("/campanas/mis-campanas/{$this->campaignId}/plantilla"));
    }
}
