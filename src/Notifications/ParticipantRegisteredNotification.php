<?php

declare(strict_types=1);

namespace Modules\GameTables\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class ParticipantRegisteredNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $participantName,
        private readonly string $tableId,
        private readonly string $tableTitle,
        private readonly ?string $tableDate,
        private readonly ?string $tableLocation,
        private readonly string $role,
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
        $roleLabel = $this->role === 'player'
            ? __('game-tables::emails.guest_confirmation.role_player')
            : __('game-tables::emails.guest_confirmation.role_spectator');

        $message = (new MailMessage())
            ->subject(__('game-tables::emails.registration.subject', ['tableTitle' => $this->tableTitle]))
            ->greeting(__('game-tables::emails.registration.greeting'))
            ->line(__('game-tables::emails.registration.intro', [
                'name' => $this->participantName,
                'role' => $roleLabel,
                'tableTitle' => $this->tableTitle,
            ]));

        $message->line('**' . __('game-tables::emails.registration.table_title') . ':** ' . $this->tableTitle);

        if ($this->tableDate !== null) {
            $message->line('**' . __('game-tables::emails.registration.table_date') . ':** ' . $this->tableDate);
        }

        if ($this->tableLocation !== null) {
            $message->line('**' . __('game-tables::emails.registration.table_location') . ':** ' . $this->tableLocation);
        }

        $tableUrl = url("/mesas/{$this->tableId}");

        return $message
            ->action(__('game-tables::emails.registration.view_table'), $tableUrl);
    }
}
