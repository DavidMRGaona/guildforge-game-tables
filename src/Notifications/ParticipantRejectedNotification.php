<?php

declare(strict_types=1);

namespace Modules\GameTables\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Modules\GameTables\Notifications\Concerns\EscapesUserText;

final class ParticipantRejectedNotification extends Notification implements ShouldQueue
{
    use EscapesUserText;
    use Queueable;

    public function __construct(
        private readonly string $participantName,
        private readonly string $tableTitle,
        private readonly ?string $tableDate,
        private readonly ?string $tableLocation,
        public readonly ?string $reason = null,
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
            ->subject(__('game-tables::emails.rejection.subject', ['tableTitle' => $this->tableTitle]))
            ->greeting(__('game-tables::emails.rejection.greeting', ['name' => $this->participantName]))
            ->line(__('game-tables::emails.rejection.intro', ['tableTitle' => $this->tableTitle]));

        $message->line('**'.__('game-tables::emails.rejection.table_title').':** '.$this->tableTitle);

        if ($this->tableDate !== null) {
            $message->line('**'.__('game-tables::emails.rejection.table_date').':** '.$this->tableDate);
        }

        if ($this->tableLocation !== null) {
            $message->line('**'.__('game-tables::emails.rejection.table_location').':** '.$this->tableLocation);
        }

        if ($this->reason !== null && $this->reason !== '') {
            $message->line($this->plainLine(__('game-tables::emails.rejection.reason', ['reason' => $this->reason])));
        }

        return $message
            ->line('')
            ->line(__('game-tables::emails.rejection.outro'));
    }
}
