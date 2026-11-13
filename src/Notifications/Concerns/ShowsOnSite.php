<?php

declare(strict_types=1);

namespace Modules\GameTables\Notifications\Concerns;

use App\Application\SiteNotifications\Contracts\HasSiteNotification;
use App\Application\SiteNotifications\DTOs\SiteNotificationDTO;
use App\Application\SiteNotifications\DTOs\SiteNotificationGroupDTO;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Closure;
use Modules\GameTables\Infrastructure\SiteNotifications\SiteNoticeTargets;

/**
 * A notification mailed and shown in the notification centre (core#491). The core stores the
 * notice for accounts only, so a guest or a plain address keeps getting just the mail. Every
 * destination is asked from SiteNoticeTargets when the notice is delivered, not when it was
 * queued: without one (the person can no longer see the thing) nothing is stored.
 */
trait ShowsOnSite
{
    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', HasSiteNotification::CHANNEL];
    }

    protected function targets(): SiteNoticeTargets
    {
        return app(SiteNoticeTargets::class);
    }

    /**
     * The account the notice is for; the core never asks for anything else.
     */
    protected function account(object $notifiable): ?UserModel
    {
        return $notifiable instanceof UserModel ? $notifiable : null;
    }

    /**
     * @param  string  $icon  One of the core's closed list; the module's dice by default
     * @param  (Closure(SiteNotificationGroupDTO): string)|null  $groupedTitle
     */
    protected function siteNotice(
        ?string $path,
        string $title,
        ?string $body = null,
        string $icon = 'dices',
        ?string $groupKey = null,
        ?string $actorId = null,
        ?Closure $groupedTitle = null,
    ): ?SiteNotificationDTO {
        if ($path === null) {
            return null;
        }

        return new SiteNotificationDTO(
            title: $title,
            url: $path,
            body: $body !== null && trim($body) !== '' ? $body : null,
            icon: $icon,
            groupKey: $groupKey,
            actorId: $actorId !== null && $actorId !== '' ? $actorId : null,
            groupedTitle: $groupedTitle,
        );
    }

    /**
     * «15/11/2026 18:00 · Sala de juegos»: the parts the notice has, on one line.
     */
    protected function details(?string ...$parts): ?string
    {
        $parts = array_filter($parts, static fn (?string $part): bool => $part !== null && trim($part) !== '');

        return $parts === [] ? null : implode(' · ', $parts);
    }

    /**
     * The notices about other people in a table or campaign the person directs gather in one
     * row per thing, whatever happened: «Ana y 3 más: novedades en «La tumba»».
     */
    protected function directionGroup(string $kind, string $id): string
    {
        return "game-tables.{$kind}:{$id}";
    }

    /**
     * @return Closure(SiteNotificationGroupDTO): string
     */
    protected function directionGroupedTitle(string $name, string $thing): Closure
    {
        return static fn (SiteNotificationGroupDTO $group): string => trans_choice(
            'game-tables::notices.direction.grouped',
            $group->otherActors,
            ['name' => $name, 'count' => $group->otherActors, 'thing' => $thing],
        );
    }

    /**
     * The proposals waiting for review, tables and campaigns alike: one row for the moderators.
     */
    protected function moderationGroup(): string
    {
        return 'game-tables.moderation';
    }

    /**
     * @return Closure(SiteNotificationGroupDTO): string
     */
    protected function moderationGroupedTitle(): Closure
    {
        return static fn (SiteNotificationGroupDTO $group): string => trans_choice(
            'game-tables::notices.moderation.grouped',
            $group->count,
            ['count' => $group->count],
        );
    }
}
