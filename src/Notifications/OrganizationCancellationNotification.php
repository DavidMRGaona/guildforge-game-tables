<?php

declare(strict_types=1);

namespace Modules\GameTables\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to a participant (registered user or guest) whose registration the organisation
 * cancelled from the panel.
 */
final class OrganizationCancellationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly string $participantName,
        private readonly string $tableId,
        private readonly string $tableTitle,
        private readonly ?string $tableDate,
        private readonly ?string $tableLocation,
        private readonly bool $guest = false,
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
        $tableUrl = url("/mesas/{$this->tableId}");

        $message = (new MailMessage())
            ->subject(__('game-tables::emails.organization_cancellation.subject', ['tableTitle' => $this->tableTitle]))
            ->greeting(__('game-tables::emails.organization_cancellation.greeting', ['name' => $this->participantName]))
            ->line(__('game-tables::emails.organization_cancellation.intro', ['tableTitle' => $this->tableTitle]))
            ->line(__('game-tables::emails.organization_cancellation.details'));

        $message->line('**' . __('game-tables::emails.organization_cancellation.table_title') . ':** ' . $this->tableTitle);

        if ($this->tableDate !== null) {
            $message->line('**' . __('game-tables::emails.organization_cancellation.table_date') . ':** ' . $this->tableDate);
        }

        if ($this->tableLocation !== null) {
            $message->line('**' . __('game-tables::emails.organization_cancellation.table_location') . ':** ' . $this->tableLocation);
        }

        $message
            ->action(__('game-tables::emails.organization_cancellation.view_table'), $tableUrl)
            ->line(__('game-tables::emails.organization_cancellation.outro'));

        if ($this->guest) {
            $message->line(__('game-tables::emails.organization_cancellation.guest_gdpr_notice'));
        }

        return $message;
    }
}
