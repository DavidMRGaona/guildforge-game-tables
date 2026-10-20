<?php

declare(strict_types=1);

namespace Modules\GameTables\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Modules\GameTables\Notifications\Concerns\ListsRosterSessions;

final class CampaignRosterAddedNotification extends Notification implements ShouldQueue
{
    use ListsRosterSessions;
    use Queueable;

    public function __construct(
        private readonly string $userName,
        private readonly string $campaignTitle,
        private readonly string $campaignSlug,
        /** @var list<array{title: string, date: string, waiting: bool}> */
        private readonly array $sessions,
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
            ->subject(__('game-tables::emails.campaign_roster_added.subject', ['campaignTitle' => $this->campaignTitle]))
            ->greeting(__('game-tables::emails.campaign_roster_added.greeting', ['name' => $this->userName]))
            ->line(__('game-tables::emails.campaign_roster_added.intro', ['campaignTitle' => $this->campaignTitle]));

        if ($this->sessions !== []) {
            $mail->line(__('game-tables::emails.campaign_sessions.intro'));
        }

        foreach ($this->sessionLines($this->sessions) as $line) {
            $mail->line($line);
        }

        return $mail
            ->line(__('game-tables::emails.campaign_sessions.absence_hint'))
            ->action(__('game-tables::emails.campaign_roster_added.view_campaign'), route('campaigns.show', $this->campaignSlug));
    }
}
