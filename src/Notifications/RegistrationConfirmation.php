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
use Modules\GameTables\Domain\Enums\ParticipantStatus;
use Modules\GameTables\Infrastructure\Mail\GameTablesMailCategories;
use Modules\GameTables\Infrastructure\Mail\GameTablesMailSubjects;
use Modules\GameTables\Notifications\Concerns\DescribesRegistrationStatus;
use Modules\GameTables\Notifications\Concerns\ShowsOnSite;

final class RegistrationConfirmation extends Notification implements HasMailCategory, HasMailSubject, HasSiteNotification, ShouldQueue
{
    use DescribesRegistrationStatus;
    use Queueable;
    use ShowsOnSite;

    public function __construct(
        private readonly string $participantName,
        private readonly string $tableId,
        private readonly string $tableTitle,
        private readonly ?string $tableDate,
        private readonly ?string $tableLocation,
        private readonly string $role,
        private readonly ParticipantStatus $status,
        private readonly ?int $waitingListPosition = null,
    ) {}

    public function mailCategory(): string
    {
        return GameTablesMailCategories::SIGN_UPS;
    }

    public function mailSubject(): ?MailSubjectDTO
    {
        return GameTablesMailSubjects::table($this->tableId, $this->tableTitle);
    }

    public function toSiteNotification(object $notifiable): ?SiteNotificationDTO
    {
        $key = match ($this->status) {
            ParticipantStatus::Confirmed => 'registration_confirmed',
            ParticipantStatus::WaitingList => 'registration_waiting_list',
            default => 'registration_pending',
        };

        return $this->siteNotice(
            $this->targets()->table($this->tableId),
            __("game-tables::notices.sign_ups.{$key}", ['table' => $this->tableTitle]),
            $this->details($this->tableDate, $this->tableLocation),
            'ticket',
        );
    }

    public function toMail(object $notifiable): MailMessage
    {
        $tableUrl = url("/mesas/{$this->tableId}");
        $roleLabel = $this->role === 'player'
            ? __('game-tables::emails.guest_confirmation.role_player')
            : __('game-tables::emails.guest_confirmation.role_spectator');

        $message = (new MailMessage)
            ->subject($this->registrationStatusSubject($this->status, $this->tableTitle))
            ->greeting(__('game-tables::emails.user_confirmation.greeting', ['name' => $this->participantName]))
            ->line(__('game-tables::emails.user_confirmation.intro', ['role' => $roleLabel]))
            ->line($this->registrationStatusLine($this->status, $this->waitingListPosition))
            ->line(__('game-tables::emails.user_confirmation.details'));

        $message->line('**'.__('game-tables::emails.user_confirmation.table_title').':** '.$this->tableTitle);

        if ($this->tableDate !== null) {
            $message->line('**'.__('game-tables::emails.user_confirmation.table_date').':** '.$this->tableDate);
        }

        if ($this->tableLocation !== null) {
            $message->line('**'.__('game-tables::emails.user_confirmation.table_location').':** '.$this->tableLocation);
        }

        $message->action(__('game-tables::emails.user_confirmation.view_table'), $tableUrl);

        if ($this->status === ParticipantStatus::Confirmed) {
            $message->line(__('game-tables::emails.user_confirmation.outro'));
        }

        return $message;
    }
}
