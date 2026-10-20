<?php

declare(strict_types=1);

namespace Modules\GameTables\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class CampaignSessionScheduledNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly string $userName,
        private readonly string $campaignTitle,
        private readonly string $tableId,
        private readonly string $tableTitle,
        private readonly string $tableDate,
        private readonly ?string $tableLocation,
        private readonly bool $waiting,
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
            ->subject(__('game-tables::emails.campaign_session_scheduled.subject', ['campaignTitle' => $this->campaignTitle, 'date' => $this->tableDate]))
            ->greeting(__('game-tables::emails.campaign_session_scheduled.greeting', ['name' => $this->userName]))
            ->line(__($this->waiting
                ? 'game-tables::emails.campaign_session_scheduled.intro_waiting'
                : 'game-tables::emails.campaign_session_scheduled.intro', ['campaignTitle' => $this->campaignTitle]))
            ->line('**'.__('game-tables::emails.campaign_session_scheduled.table_title').':** '.$this->tableTitle)
            ->line('**'.__('game-tables::emails.campaign_session_scheduled.table_date').':** '.$this->tableDate);

        if ($this->tableLocation !== null) {
            $mail->line('**'.__('game-tables::emails.campaign_session_scheduled.table_location').':** '.$this->tableLocation);
        }

        return $mail
            ->line(__('game-tables::emails.campaign_session_scheduled.absence_hint'))
            ->action(__('game-tables::emails.campaign_session_scheduled.view_session'), url("/mesas/{$this->tableId}"));
    }
}
