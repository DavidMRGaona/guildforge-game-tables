<?php

declare(strict_types=1);

namespace Modules\GameTables\Infrastructure\Services;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Modules\GameTables\Application\Services\NotificationRecipientResolverInterface;
use Modules\GameTables\Application\Services\TableDirectionInterface;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameMasterModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameTableModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\ParticipantModel;

final readonly class NotificationRecipientResolver implements NotificationRecipientResolverInterface
{
    /**
     * Get the creator's email address by user ID.
     */
    public function getCreatorEmail(string $userId): ?string
    {
        return \App\Infrastructure\Persistence\Eloquent\Models\UserModel::query()
            ->where('id', $userId)
            ->value('email');
    }

    /**
     * Get the creator's display name by user ID.
     */
    public function getCreatorDisplayName(string $userId): string
    {
        return \App\Infrastructure\Persistence\Eloquent\Models\UserModel::query()
            ->where('id', $userId)
            ->value('name') ?? '';
    }

    /**
     * Get emails of admin/editor users who can moderate game tables.
     *
     * @return array<string>
     */
    public function getModerationAdminEmails(): array
    {
        return \App\Infrastructure\Persistence\Eloquent\Models\UserModel::query()
            ->whereHas('roles', fn ($query) => $query->whereIn('name', ['admin', 'editor']))
            ->pluck('email')
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Get emails of game masters that have notify_by_email enabled.
     *
     * @return array<string>
     */
    public function getGameMasterEmails(string $gameTableId): array
    {
        $gameTable = GameTableModel::query()->find($gameTableId);

        if ($gameTable === null) {
            return [];
        }

        return $gameTable->effective_game_masters
            ->filter(fn (GameMasterModel $gm): bool => $gm->notify_by_email)
            ->map(fn (GameMasterModel $gm): ?string => $gm->email ?? $gm->user?->email)
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Get the account emails of the linked directors that have notify_by_email enabled.
     *
     * @return list<string>
     */
    public function getDirectorEmails(string $gameTableId): array
    {
        $userIds = app(TableDirectionInterface::class)->directorUserIds($gameTableId);

        if ($userIds === []) {
            return [];
        }

        $gameTable = GameTableModel::query()->find($gameTableId);

        if ($gameTable === null) {
            return [];
        }

        $notifiable = $gameTable->effective_game_masters
            ->filter(fn (GameMasterModel $gm): bool => $gm->notify_by_email && in_array((string) $gm->user_id, $userIds, true))
            ->map(fn (GameMasterModel $gm): string => (string) $gm->user_id)
            ->unique()
            ->values()
            ->all();

        /** @var list<string> $emails */
        $emails = UserModel::query()
            ->whereIn('id', $notifiable)
            ->pluck('email')
            ->filter()
            ->unique()
            ->values()
            ->all();

        return $emails;
    }

    /**
     * Get the table's notification email address.
     */
    public function getTableNotificationEmail(string $gameTableId): ?string
    {
        return GameTableModel::query()
            ->where('id', $gameTableId)
            ->value('notification_email');
    }

    /**
     * Get the participant's email address.
     */
    public function getParticipantEmail(string $participantId): ?string
    {
        $participant = ParticipantModel::query()
            ->where('id', $participantId)
            ->with('user')
            ->first();

        if ($participant === null) {
            return null;
        }

        return $participant->email ?? $participant->user?->email;
    }

    /**
     * Get the participant's display name.
     */
    public function getParticipantDisplayName(string $participantId): string
    {
        $participant = ParticipantModel::query()
            ->where('id', $participantId)
            ->with('user')
            ->first();

        if ($participant === null) {
            return '';
        }

        return $participant->display_name;
    }
}
