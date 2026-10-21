<?php

declare(strict_types=1);

namespace Modules\GameTables\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Modules\GameTables\Notifications\Concerns\EscapesUserText;

final class CampaignInvitationAcceptedNotification extends Notification implements ShouldQueue
{
    use EscapesUserText;
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
        $params = ['player' => $this->playerName, 'campaignTitle' => $this->campaignTitle];

        return (new MailMessage)
            ->subject(__('game-tables::emails.campaign_invitation_accepted.subject', $params))
            ->greeting(__('game-tables::emails.campaign_invitation_accepted.greeting', ['name' => $this->userName]))
            ->line($this->plainLine(__('game-tables::emails.campaign_invitation_accepted.intro', $params)))
            ->action(
                __('game-tables::emails.campaign_invitation_accepted.view_roster'),
                url("/campanas/mis-campanas/{$this->campaignId}/plantilla"),
            );
    }
}
