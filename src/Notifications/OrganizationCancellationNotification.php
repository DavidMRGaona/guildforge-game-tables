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
use Modules\GameTables\Infrastructure\Mail\GameTablesMailCategories;
use Modules\GameTables\Infrastructure\Mail\GameTablesMailSubjects;
use Modules\GameTables\Notifications\Concerns\ShowsOnSite;

/**
 * Sent to a participant (registered user or guest) whose registration the organisation
 * cancelled from the panel.
 */
final class OrganizationCancellationNotification extends Notification implements HasMailCategory, HasMailSubject, HasSiteNotification, ShouldQueue
{
    use Queueable;
    use ShowsOnSite;

    public function __construct(
        private readonly string $participantName,
        private readonly string $tableId,
        private readonly string $tableTitle,
        private readonly ?string $tableDate,
        private readonly ?string $tableLocation,
        private readonly bool $guest = false,
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
        return $this->siteNotice(
            $this->targets()->table($this->tableId),
            __('game-tables::notices.sign_ups.organization_cancelled', ['table' => $this->tableTitle]),
            $this->details($this->tableDate, $this->tableLocation),
            'ticket',
        );
    }

    public function toMail(object $notifiable): MailMessage
    {
        $tableUrl = url("/mesas/{$this->tableId}");

        $message = (new MailMessage)
            ->subject(__('game-tables::emails.organization_cancellation.subject', ['tableTitle' => $this->tableTitle]))
            ->greeting(__('game-tables::emails.organization_cancellation.greeting', ['name' => $this->participantName]))
            ->line(__('game-tables::emails.organization_cancellation.intro', ['tableTitle' => $this->tableTitle]))
            ->line(__('game-tables::emails.organization_cancellation.details'));

        $message->line('**'.__('game-tables::emails.organization_cancellation.table_title').':** '.$this->tableTitle);

        if ($this->tableDate !== null) {
            $message->line('**'.__('game-tables::emails.organization_cancellation.table_date').':** '.$this->tableDate);
        }

        if ($this->tableLocation !== null) {
            $message->line('**'.__('game-tables::emails.organization_cancellation.table_location').':** '.$this->tableLocation);
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
