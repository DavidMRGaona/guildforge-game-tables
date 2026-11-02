<?php

declare(strict_types=1);

namespace Modules\GameTables\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class WaitingListPromotionGmNotification extends Notification implements ShouldQueue
{
    use Queueable;

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
