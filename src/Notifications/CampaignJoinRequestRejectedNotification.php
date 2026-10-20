<?php

declare(strict_types=1);

namespace Modules\GameTables\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class CampaignJoinRequestRejectedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly string $userName,
        private readonly string $campaignTitle,
        private readonly string $campaignSlug,
        private readonly ?string $reason,
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
            ->subject(__('game-tables::emails.campaign_join_request_rejected.subject', ['campaignTitle' => $this->campaignTitle]))
            ->greeting(__('game-tables::emails.campaign_join_request_rejected.greeting', ['name' => $this->userName]))
            ->line(__('game-tables::emails.campaign_join_request_rejected.intro', ['campaignTitle' => $this->campaignTitle]));

        if ($this->reason !== null && $this->reason !== '') {
            $mail->line(__('game-tables::emails.campaign_join_request_rejected.reason', ['reason' => $this->reason]));
        }

        return $mail->action(
            __('game-tables::emails.campaign_join_request_rejected.view_campaign'),
            route('campaigns.show', $this->campaignSlug),
        );
    }
}
