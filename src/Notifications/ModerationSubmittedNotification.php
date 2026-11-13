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
use Modules\GameTables\Filament\Resources\GameTableResource;
use Modules\GameTables\Infrastructure\Mail\GameTablesMailCategories;
use Modules\GameTables\Infrastructure\Mail\GameTablesMailSubjects;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameTableModel;
use Modules\GameTables\Notifications\Concerns\ShowsOnSite;

final class ModerationSubmittedNotification extends Notification implements HasMailCategory, HasMailSubject, HasSiteNotification, ShouldQueue
{
    use Queueable;
    use ShowsOnSite;

    public function __construct(
        private readonly string $tableId,
        private readonly string $tableTitle,
        private readonly string $creatorName,
        // The author's account, so a moderator never gets their own proposal and anonymizing
        // the author forgets the notice
        public readonly ?string $creatorId = null,
    ) {}

    public function mailCategory(): string
    {
        return GameTablesMailCategories::MODERATION;
    }

    public function mailSubject(): ?MailSubjectDTO
    {
        return GameTablesMailSubjects::table($this->tableId, $this->tableTitle);
    }

    public function toSiteNotification(object $notifiable): ?SiteNotificationDTO
    {
        $account = $this->account($notifiable);

        return $account instanceof UserModel ? $this->siteNotice(
            $this->targets()->tableReview($account, $this->tableId),
            __('game-tables::notices.moderation.table', ['table' => $this->tableTitle]),
            $this->creatorName !== '' ? (string) __('game-tables::notices.moderation.proposed_by', ['name' => $this->creatorName]) : null,
            'shield-check',
            $this->moderationGroup(),
            $this->creatorId ?? null,
            $this->moderationGroupedTitle(),
        ) : null;
    }

    public function toMail(object $notifiable): MailMessage
    {
        // The panel finds game tables by slug; without one, the list of tables to review
        $slug = GameTableModel::query()->whereKey($this->tableId)->value('slug');
        $reviewUrl = is_string($slug) && $slug !== ''
            ? GameTableResource::getUrl('edit', ['record' => $slug])
            : GameTableResource::getUrl('index');

        return (new MailMessage)
            ->subject(__('game-tables::emails.moderation_submitted.subject', ['tableTitle' => $this->tableTitle]))
            ->greeting(__('game-tables::emails.moderation_submitted.greeting'))
            ->line(__('game-tables::emails.moderation_submitted.intro'))
            ->line('**'.__('game-tables::emails.moderation_submitted.table_title').':** '.$this->tableTitle)
            ->line('**'.__('game-tables::emails.moderation_submitted.created_by').':** '.$this->creatorName)
            ->action(__('game-tables::emails.moderation_submitted.review_table'), $reviewUrl)
            ->line('')
            ->line(__('game-tables::emails.moderation_submitted.outro'));
    }
}
