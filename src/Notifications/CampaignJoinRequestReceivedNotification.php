<?php

declare(strict_types=1);

namespace Modules\GameTables\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Modules\GameTables\Notifications\Concerns\EscapesUserText;

final class CampaignJoinRequestReceivedNotification extends Notification implements ShouldQueue
{
    use EscapesUserText;
    use Queueable;

    public function __construct(
        private readonly string $userName,
        private readonly string $campaignId,
        private readonly string $campaignTitle,
        private readonly string $playerName,
        private readonly ?string $message,
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
        $mail = (new MailMessage)
            ->subject(__('game-tables::emails.campaign_join_request_received.subject', ['campaignTitle' => $this->campaignTitle]))
            ->greeting(__('game-tables::emails.campaign_join_request_received.greeting', ['name' => $this->userName]))
            ->line($this->plainLine(__('game-tables::emails.campaign_join_request_received.intro', ['player' => $this->playerName, 'campaignTitle' => $this->campaignTitle])));

        if ($this->message !== null && $this->message !== '') {
            $mail->line($this->plainLine(__('game-tables::emails.campaign_join_request_received.message', ['message' => $this->message])));
        }

        return $mail->action(
            __('game-tables::emails.campaign_join_request_received.review_requests'),
            url("/campanas/mis-campanas/{$this->campaignId}/plantilla"),
        );
    }
}
