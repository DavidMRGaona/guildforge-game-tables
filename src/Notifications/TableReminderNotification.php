<?php

declare(strict_types=1);

namespace Modules\GameTables\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class TableReminderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly string $participantName,
        private readonly string $tableTitle,
        private readonly string $tableSlug,
        private readonly string $tableDate,
        private readonly ?string $tableLocation,
        private readonly ?string $cancellationToken = null,
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
            ->subject(__('game-tables::emails.table_reminder.subject', [
                'tableTitle' => $this->tableTitle,
                'date' => $this->tableDate,
            ]))
            ->greeting(trim($this->participantName) !== ''
                ? __('game-tables::emails.table_reminder.greeting', ['name' => $this->participantName])
                : __('game-tables::emails.table_reminder.greeting_anonymous'))
            ->line(__('game-tables::emails.table_reminder.intro', [
                'tableTitle' => $this->tableTitle,
                'date' => $this->tableDate,
            ]));

        if ($this->tableLocation !== null && $this->tableLocation !== '') {
            $message->line('**'.__('game-tables::emails.table_reminder.location').':** '.$this->tableLocation);
        }

        $message->action(__('game-tables::emails.table_reminder.view_table'), route('gametables.show', $this->tableSlug));

        $message->line($this->cancellationToken !== null
            ? __('game-tables::emails.table_reminder.cancel_guest', [
                'url' => route('gametables.cancel-confirmation', ['token' => $this->cancellationToken]),
            ])
            : __('game-tables::emails.table_reminder.cancel'));

        return $message;
    }
}
