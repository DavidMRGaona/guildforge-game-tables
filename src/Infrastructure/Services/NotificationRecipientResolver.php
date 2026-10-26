<?php

declare(strict_types=1);

namespace Modules\GameTables\Infrastructure\Services;

use App\Application\Authorization\Services\AuthorizationServiceInterface;
use App\Domain\Enums\UserRole;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Database\Eloquent\Builder;
use Modules\GameTables\Application\Services\NotificationRecipientResolverInterface;
use Modules\GameTables\Application\Services\TableDirectionInterface;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameMasterModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameTableModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\ParticipantModel;

final readonly class NotificationRecipientResolver implements NotificationRecipientResolverInterface
{
    private const string PANEL_ACCESS_PERMISSION = 'admin.access';

    /**
     * The permissions the panel's approve and reject actions check (canModerate() on each resource).
     */
    private const string TABLES_MODERATE_PERMISSION = 'gametables:gametables.update';

    private const string CAMPAIGNS_MODERATE_PERMISSION = 'gametables:campaigns.update';

    /**
     * Get the creator's email address by user ID.
     */
    public function getCreatorEmail(string $userId): ?string
    {
        return UserModel::query()
            ->where('id', $userId)
            ->value('email');
    }

    /**
     * Get the creator's display name by user ID.
     */
    public function getCreatorDisplayName(string $userId): string
    {
        return UserModel::query()
            ->where('id', $userId)
            ->value('name') ?? '';
    }

    /**
     * Get emails of the users who can approve or reject game tables in the panel.
     *
     * @return list<string>
     */
    public function getTableModeratorEmails(): array
    {
        return $this->moderatorEmails(self::TABLES_MODERATE_PERMISSION);
    }

    /**
     * Get emails of the users who can approve or reject campaigns in the panel.
     *
     * @return list<string>
     */
    public function getCampaignModeratorEmails(): array
    {
        return $this->moderatorEmails(self::CAMPAIGNS_MODERATE_PERMISSION);
    }

    /**
     * Same rule as the panel: panel access plus the permission its moderation actions check.
     *
     * The query only narrows the candidates (admins hold every permission without it being
     * listed on their role); the authorization service has the final word on each one.
     *
     * @return list<string>
     */
    private function moderatorEmails(string $permission): array
    {
        $authorization = app(AuthorizationServiceInterface::class);

        /** @var list<string> $emails */
        $emails = UserModel::query()
            ->whereHas('roles', fn (Builder $roles): Builder => $roles->where(
                fn (Builder $query): Builder => $query
                    ->where('name', UserRole::Admin->value)
                    ->orWhereHas('permissions', fn (Builder $permissions): Builder => $permissions->where('key', $permission)),
            ))
            ->get()
            ->filter(fn (UserModel $user): bool => $authorization->canAll($user, [self::PANEL_ACCESS_PERMISSION, $permission]))
            ->pluck('email')
            ->filter()
            ->unique()
            ->values()
            ->all();

        return $emails;
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
