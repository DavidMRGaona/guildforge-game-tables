<?php

declare(strict_types=1);

namespace Modules\GameTables\Notifications;

use App\Application\Mail\Contracts\HasMailCategory;
use App\Application\Mail\Contracts\HasMailSubject;
use App\Application\Mail\DTOs\MailSubjectDTO;
use App\Application\SiteNotifications\Contracts\HasSiteNotification;
use App\Application\SiteNotifications\DTOs\SiteNotificationDTO;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Modules\GameTables\Infrastructure\Mail\GameTablesMailCategories;
use Modules\GameTables\Infrastructure\Mail\GameTablesMailSubjects;
use Modules\GameTables\Notifications\Concerns\EscapesUserText;
use Modules\GameTables\Notifications\Concerns\ShowsOnSite;

final class CampaignPlayerLeftNotification extends Notification implements HasMailCategory, HasMailSubject, HasSiteNotification, ShouldQueue
{
    use EscapesUserText;
    use Queueable;
    use ShowsOnSite;

    public function __construct(
        private readonly string $userName,
        private readonly string $campaignId,
        private readonly string $campaignTitle,
        private readonly string $playerName,
        // The player's account, so anonymizing it forgets the notice
        public readonly ?string $playerId = null,
    ) {}

    public function mailCategory(): string
    {
        return GameTablesMailCategories::DIRECTION;
    }

    public function mailSubject(): ?MailSubjectDTO
    {
        return GameTablesMailSubjects::campaign($this->campaignId, $this->campaignTitle);
    }

    public function toSiteNotification(object $notifiable): ?SiteNotificationDTO
    {
        $account = $this->account($notifiable);

        return $account instanceof UserModel ? $this->siteNotice(
            $this->targets()->roster($account, $this->campaignId),
            __('game-tables::notices.direction.player_left', ['name' => $this->playerName, 'campaign' => $this->campaignTitle]),
            icon: 'users',
            groupKey: $this->directionGroup('campaign', $this->campaignId),
            actorId: $this->playerId ?? null,
            groupedTitle: $this->directionGroupedTitle($this->playerName, $this->campaignTitle),
        ) : null;
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('game-tables::emails.campaign_player_left.subject', ['player' => $this->playerName, 'campaignTitle' => $this->campaignTitle]))
            ->greeting(__('game-tables::emails.campaign_player_left.greeting', ['name' => $this->userName]))
            ->line($this->plainLine(__('game-tables::emails.campaign_player_left.intro', ['player' => $this->playerName, 'campaignTitle' => $this->campaignTitle])))
            ->action(__('game-tables::emails.campaign_player_left.view_roster'), url("/campanas/mis-campanas/{$this->campaignId}/plantilla"));
    }
}
