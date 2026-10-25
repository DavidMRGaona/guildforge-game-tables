<?php

declare(strict_types=1);

namespace Modules\GameTables\Notifications;

use App\Application\Legal\Services\LegalMailNoticeServiceInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\HtmlString;
use Modules\GameTables\Notifications\Concerns\EscapesUserText;

/**
 * The invitation to a campaign, sent to an address that may have no account. It carries the
 * information required by art. 14 GDPR, because the address was given to us by a third party.
 */
final class CampaignInvitationNotification extends Notification implements ShouldQueue
{
    public const string NOTICE_KEY = 'game-tables.campaign_invitation';

    public const int EXPIRY_DAYS = 14;

    use EscapesUserText;
    use Queueable;

    public function __construct(
        private readonly string $inviterName,
        private readonly string $campaignTitle,
        private readonly string $campaignSlug,
        private readonly string $email,
        private readonly string $token,
        private readonly string $expiresAt,
        private readonly ?string $legalText = null,
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
        $params = ['inviter' => $this->inviterName, 'campaignTitle' => $this->campaignTitle];

        $message = (new MailMessage)
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
            ->line(__('game-tables::emails.campaign_invitation.privacy_title'));

        foreach ($this->controllerLines() as $line) {
            $message->line($line);
        }

        foreach ($this->legalParagraphs() as $paragraph) {
            $message->line($this->plainLine($paragraph));
        }

        return $message->line(__('game-tables::emails.campaign_invitation.privacy_policy', ['url' => route('legal.show', ['slug' => 'politica-de-privacidad'])]));
    }

    /**
     * The controller and its contacts: always the legal entity, never part of the editable text.
     *
     * @return list<HtmlString>
     */
    private function controllerLines(): array
    {
        $entity = $this->noticeService()?->entity();
        $lines = [$this->plainLine((string) __('game-tables::emails.campaign_invitation.privacy_controller', [
            'name' => $entity->name ?? (string) config('app.name'),
        ]))];

        if ($entity?->email !== null) {
            $lines[] = $this->plainLine((string) __('game-tables::emails.campaign_invitation.privacy_contact', ['email' => $entity->email]));
        }

        if ($entity?->dpoEmail !== null) {
            $lines[] = $this->plainLine((string) __('game-tables::emails.campaign_invitation.privacy_dpo', ['dpo' => $entity->dpoEmail]));
        }

        return $lines;
    }

    /**
     * The legal paragraphs as plain text, values not yet escaped (plainLine() does it once).
     *
     * @return list<string>
     */
    private function legalParagraphs(): array
    {
        $service = $this->noticeService();
        $replacements = [
            ':inviter' => $this->inviterName,
            ':campaign' => $this->campaignTitle,
            ':site' => $service?->entity()->name ?? (string) config('app.name'),
            ':days' => (string) self::EXPIRY_DAYS,
        ];

        if ($this->legalText === null && $service instanceof LegalMailNoticeServiceInterface) {
            return $service->paragraphs(self::NOTICE_KEY, $replacements);
        }

        $text = $this->legalText ?? (string) __('game-tables::emails.campaign_invitation.privacy_default');
        $paragraphs = [];

        foreach (preg_split('/\n\s*\n/', str_replace(["\r\n", "\r"], "\n", $text)) ?: [] as $paragraph) {
            if (trim($paragraph) !== '') {
                $paragraphs[] = strtr(trim($paragraph), $replacements);
            }
        }

        return $paragraphs;
    }

    /**
     * Absent in a core without editable legal notices; the email then keeps the default lines.
     */
    private function noticeService(): ?LegalMailNoticeServiceInterface
    {
        if (! interface_exists(LegalMailNoticeServiceInterface::class) || ! app()->bound(LegalMailNoticeServiceInterface::class)) {
            return null;
        }

        return app(LegalMailNoticeServiceInterface::class);
    }
}
