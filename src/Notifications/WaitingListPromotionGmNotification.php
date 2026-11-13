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
use Modules\GameTables\Notifications\Concerns\ShowsOnSite;

final class WaitingListPromotionGmNotification extends Notification implements HasMailCategory, HasMailSubject, HasSiteNotification, ShouldQueue
{
    use Queueable;
    use ShowsOnSite;

    public function __construct(
        private readonly string $participantName,
        private readonly string $tableId,
        private readonly string $tableTitle,
        private readonly ?string $tableDate,
        private readonly ?string $tableLocation,
        // False on tables with manual confirmation: the game master has to confirm it
        private readonly bool $confirmed = true,
        // A pending promotion on a table that delegates its registrations, for its linked
        // directors: they approve or reject it on «Gestionar inscritos», not in the panel
        private readonly ?string $manageUrl = null,
        // The participant's account (null for a guest), so anonymizing it forgets the notice
        public readonly ?string $participantUserId = null,
    ) {}

    public function mailCategory(): string
    {
        return GameTablesMailCategories::DIRECTION;
    }

    public function mailSubject(): ?MailSubjectDTO
    {
        return GameTablesMailSubjects::table($this->tableId, $this->tableTitle);
    }

    public function toSiteNotification(object $notifiable): ?SiteNotificationDTO
    {
        $account = $this->account($notifiable);
        $awaitsApproval = ! $this->confirmed && $this->manageUrl !== null;
        $name = $this->participantName !== '' ? $this->participantName : __('game-tables::notices.direction.someone');

        return $account instanceof UserModel ? $this->siteNotice(
            $this->targets()->directedTable($account, $this->tableId, $awaitsApproval),
            __($this->confirmed ? 'game-tables::notices.direction.promoted' : 'game-tables::notices.direction.promoted_pending', [
                'name' => $name,
                'table' => $this->tableTitle,
            ]),
            $this->details($this->tableDate, $this->tableLocation),
            'users',
            $this->directionGroup('table', $this->tableId),
            $this->participantUserId ?? null,
            $this->directionGroupedTitle($name, $this->tableTitle),
        ) : null;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject(__('game-tables::emails.waiting_list_promotion_gm.subject', ['tableTitle' => $this->tableTitle]))
            ->greeting(__('game-tables::emails.waiting_list_promotion_gm.greeting'))
            ->line(__('game-tables::emails.waiting_list_promotion_gm.intro', [
                'name' => $this->participantName,
                'tableTitle' => $this->tableTitle,
            ]));

        $manageUrl = $this->confirmed ? null : $this->manageUrl;

        if (! $this->confirmed) {
            $message->line(__($manageUrl !== null
                ? 'game-tables::emails.waiting_list_promotion_gm.pending_manage'
                : 'game-tables::emails.waiting_list_promotion_gm.pending'));
        }

        $message->line('**'.__('game-tables::emails.waiting_list_promotion_gm.table_title').':** '.$this->tableTitle);

        if ($this->tableDate !== null) {
            $message->line('**'.__('game-tables::emails.waiting_list_promotion_gm.table_date').':** '.$this->tableDate);
        }

        if ($this->tableLocation !== null) {
            $message->line('**'.__('game-tables::emails.waiting_list_promotion_gm.table_location').':** '.$this->tableLocation);
        }

        if ($manageUrl !== null) {
            return $message->action(__('game-tables::emails.waiting_list_promotion_gm.manage'), $manageUrl);
        }

        $tableUrl = url("/mesas/{$this->tableId}");

        return $message
            ->action(__('game-tables::emails.waiting_list_promotion_gm.view_table'), $tableUrl);
    }
}
