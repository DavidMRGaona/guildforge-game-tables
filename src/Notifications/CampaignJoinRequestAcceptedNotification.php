<?php

declare(strict_types=1);

namespace Modules\GameTables\Notifications;

use App\Application\Mail\Contracts\HasMailCategory;
use App\Application\Mail\Contracts\HasMailSubject;
use App\Application\Mail\DTOs\MailSubjectDTO;
use App\Application\SiteNotifications\Contracts\HasSiteNotification;
use App\Application\SiteNotifications\DTOs\SiteNotificationDTO;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Modules\GameTables\Infrastructure\Mail\GameTablesMailCategories;
use Modules\GameTables\Infrastructure\Mail\GameTablesMailSubjects;
use Modules\GameTables\Notifications\Concerns\ListsRosterSessions;
use Modules\GameTables\Notifications\Concerns\ShowsOnSite;

final class CampaignJoinRequestAcceptedNotification extends Notification implements HasMailCategory, HasMailSubject, HasSiteNotification, ShouldQueue
{
    use ListsRosterSessions;
    use Queueable;
    use ShowsOnSite;

    public function __construct(
        private readonly string $userName,
        private readonly string $campaignTitle,
        private readonly string $campaignSlug,
        /** @var list<array{title: string, date: string, waiting: bool}> */
        private readonly array $sessions,
        // Null in a notification queued before it carried the campaign: mailed, never shown on the site
        public readonly ?string $campaignId = null,
    ) {}

    public function mailCategory(): string
    {
        return GameTablesMailCategories::SIGN_UPS;
    }

    public function mailSubject(): ?MailSubjectDTO
    {
        return GameTablesMailSubjects::campaign($this->campaignId ?? null, $this->campaignTitle);
    }

    public function toSiteNotification(object $notifiable): ?SiteNotificationDTO
    {
        return $this->siteNotice(
            $this->targets()->campaign($this->campaignId ?? null),
            __('game-tables::notices.sign_ups.join_request_accepted', ['campaign' => $this->campaignTitle]),
            icon: 'ticket',
        );
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject(__('game-tables::emails.campaign_join_request_accepted.subject', ['campaignTitle' => $this->campaignTitle]))
            ->greeting(__('game-tables::emails.campaign_join_request_accepted.greeting', ['name' => $this->userName]))
            ->line(__('game-tables::emails.campaign_join_request_accepted.intro', ['campaignTitle' => $this->campaignTitle]));

        if ($this->sessions !== []) {
            $mail->line(__('game-tables::emails.campaign_sessions.intro'));
        }

        foreach ($this->sessionLines($this->sessions) as $line) {
            $mail->line($line);
        }

        return $mail
            ->line(__('game-tables::emails.campaign_sessions.absence_hint'))
            ->action(__('game-tables::emails.campaign_join_request_accepted.view_campaign'), route('campaigns.show', $this->campaignSlug));
    }
}
