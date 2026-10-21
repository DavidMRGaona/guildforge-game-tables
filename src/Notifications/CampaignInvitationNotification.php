<?php

declare(strict_types=1);

namespace Modules\GameTables\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Modules\GameTables\Notifications\Concerns\EscapesUserText;

/**
 * The invitation to a campaign, sent to an address that may have no account. It carries the
 * information required by art. 14 GDPR, because the address was given to us by a third party.
 */
final class CampaignInvitationNotification extends Notification implements ShouldQueue
{
    use EscapesUserText;
    use Queueable;

    public function __construct(
        private readonly string $inviterName,
        private readonly string $campaignTitle,
        private readonly string $campaignSlug,
        private readonly string $email,
        private readonly string $token,
        private readonly string $expiresAt,
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
        $siteName = (string) config('app.name');
        $params = ['inviter' => $this->inviterName, 'campaignTitle' => $this->campaignTitle];

        return (new MailMessage)
            ->subject(__('game-tables::emails.campaign_invitation.subject', $params))
            ->greeting(__('game-tables::emails.campaign_invitation.greeting'))
            ->line($this->plainLine(__('game-tables::emails.campaign_invitation.intro', $params)))
            ->line(__('game-tables::emails.campaign_invitation.view_campaign', ['url' => route('campaigns.show', $this->campaignSlug)]))
            ->action(
                __('game-tables::emails.campaign_invitation.view_invitation'),
                url('/campanas/invitaciones/'.$this->token),
            )
            ->line(__('game-tables::emails.campaign_invitation.reject'))
            ->line(__('game-tables::emails.campaign_invitation.expires', ['date' => $this->expiresAt]))
            ->line(__('game-tables::emails.campaign_invitation.privacy_title'))
            ->line($this->plainLine(__('game-tables::emails.campaign_invitation.privacy_controller', ['site' => $siteName])))
            ->line(__('game-tables::emails.campaign_invitation.privacy_purpose'))
            ->line($this->plainLine(__('game-tables::emails.campaign_invitation.privacy_origin', ['inviter' => $this->inviterName])))
            ->line(__('game-tables::emails.campaign_invitation.privacy_retention'))
            ->line(__('game-tables::emails.campaign_invitation.privacy_rights', ['url' => url('/politica-de-privacidad')]));
    }
}
