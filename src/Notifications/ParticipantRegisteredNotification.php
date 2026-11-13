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

final class ParticipantRegisteredNotification extends Notification implements HasMailCategory, HasMailSubject, HasSiteNotification, ShouldQueue
{
    use Queueable;
    use ShowsOnSite;

    public function __construct(
        private readonly string $participantName,
        private readonly string $tableId,
        private readonly string $tableTitle,
        private readonly ?string $tableDate,
        private readonly ?string $tableLocation,
        private readonly string $role,
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
        // Only directors of a delegating table get a manage link: they approve it on the web
        $awaitsApproval = $this->manageUrl !== null;
        $name = $this->participantName !== '' ? $this->participantName : __('game-tables::notices.direction.someone');

        return $account instanceof UserModel ? $this->siteNotice(
            $this->targets()->directedTable($account, $this->tableId, $awaitsApproval),
            __($awaitsApproval ? 'game-tables::notices.direction.registered_pending' : 'game-tables::notices.direction.registered', [
                'name' => $name,
                'table' => $this->tableTitle,
            ]),
            $this->details($this->tableDate, $this->tableLocation),
            'user-plus',
            $this->directionGroup('table', $this->tableId),
            $this->participantUserId ?? null,
            $this->directionGroupedTitle($name, $this->tableTitle),
        ) : null;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $roleLabel = $this->role === 'player'
            ? __('game-tables::emails.guest_confirmation.role_player')
            : __('game-tables::emails.guest_confirmation.role_spectator');

        $message = (new MailMessage)
            ->subject(__('game-tables::emails.registration.subject', ['tableTitle' => $this->tableTitle]))
            ->greeting(__('game-tables::emails.registration.greeting'))
            ->line(__('game-tables::emails.registration.intro', [
                'name' => $this->participantName,
                'role' => $roleLabel,
                'tableTitle' => $this->tableTitle,
            ]));

        $message->line('**'.__('game-tables::emails.registration.table_title').':** '.$this->tableTitle);

        if ($this->tableDate !== null) {
            $message->line('**'.__('game-tables::emails.registration.table_date').':** '.$this->tableDate);
        }

        if ($this->tableLocation !== null) {
            $message->line('**'.__('game-tables::emails.registration.table_location').':** '.$this->tableLocation);
        }

        if ($this->manageUrl !== null) {
            return $message
                ->line(__('game-tables::emails.registration.pending_intro'))
                ->action(__('game-tables::emails.registration.manage'), $this->manageUrl);
        }

        $tableUrl = url("/mesas/{$this->tableId}");

        return $message
            ->action(__('game-tables::emails.registration.view_table'), $tableUrl);
    }
}
