<?php

declare(strict_types=1);

namespace Modules\GameTables\Infrastructure\Services;

use App\Application\Authorization\Services\AuthorizationServiceInterface;
use App\Domain\Enums\UserRole;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Database\Eloquent\Builder;
use Modules\GameTables\Application\DTOs\MailRecipientDTO;
use Modules\GameTables\Application\Services\NotificationRecipientResolverInterface;
use Modules\GameTables\Application\Services\TableDirectionInterface;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameMasterModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameTableModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\ParticipantModel;

final readonly class NotificationRecipientResolver implements NotificationRecipientResolverInterface
{
    public const string PANEL_ACCESS_PERMISSION = 'admin.access';

    /**
     * The permissions the panel's approve and reject actions check (canModerate() on each resource).
     */
    public const string TABLES_MODERATE_PERMISSION = 'gametables:gametables.update';

    public const string CAMPAIGNS_MODERATE_PERMISSION = 'gametables:campaigns.update';

    public function getCreatorRecipient(string $userId): ?MailRecipientDTO
    {
        $user = UserModel::query()->find($userId);

        return $user instanceof UserModel ? $this->account($user) : null;
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

    public function getTableModeratorRecipients(): array
    {
        return $this->moderators(self::TABLES_MODERATE_PERMISSION);
    }

    public function getCampaignModeratorRecipients(): array
    {
        return $this->moderators(self::CAMPAIGNS_MODERATE_PERMISSION);
    }

    /**
     * Whether the account moderates tables right now: the rule that picks the recipients of a
     * proposal, asked again when its notice is delivered.
     */
    public function moderatesTables(UserModel $user): bool
    {
        return $this->moderates($user, self::TABLES_MODERATE_PERMISSION);
    }

    public function moderatesCampaigns(UserModel $user): bool
    {
        return $this->moderates($user, self::CAMPAIGNS_MODERATE_PERMISSION);
    }

    /**
     * Same rule as the panel: panel access plus the permission its moderation actions check.
     */
    private function moderates(UserModel $user, string $permission): bool
    {
        return app(AuthorizationServiceInterface::class)->canAll($user, [self::PANEL_ACCESS_PERMISSION, $permission]);
    }

    /**
     * The query only narrows the candidates (admins hold every permission without it being
     * listed on their role); moderates() has the final word on each one.
     *
     * @return list<MailRecipientDTO>
     */
    private function moderators(string $permission): array
    {
        $users = UserModel::query()
            ->whereHas('roles', fn (Builder $roles): Builder => $roles->where(
                fn (Builder $query): Builder => $query
                    ->where('name', UserRole::Admin->value)
                    ->orWhereHas('permissions', fn (Builder $permissions): Builder => $permissions->where('key', $permission)),
            ))
            ->get()
            ->filter(fn (UserModel $user): bool => $this->moderates($user, $permission))
            ->all();

        return $this->uniqueByAddress(array_map($this->account(...), array_values($users)));
    }

    public function getGameMasterRecipients(string $gameTableId): array
    {
        $gameTable = GameTableModel::query()->find($gameTableId);

        if ($gameTable === null) {
            return [];
        }

        $recipients = $gameTable->effective_game_masters
            ->filter(fn (GameMasterModel $gm): bool => $gm->notify_by_email)
            ->map(function (GameMasterModel $gm): ?MailRecipientDTO {
                // Linked to an account: the account, so its mail choices apply
                if ($gm->user_id !== null) {
                    return $gm->user instanceof UserModel ? $this->account($gm->user) : null;
                }

                return $gm->email !== null && $gm->email !== '' ? MailRecipientDTO::address($gm->email) : null;
            })
            ->filter()
            ->values()
            ->all();

        return $this->uniqueByAddress($recipients);
    }

    public function getDirectorRecipients(string $gameTableId): array
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

        $users = UserModel::query()->whereIn('id', $notifiable)->get()->all();

        return $this->uniqueByAddress(array_map($this->account(...), array_values($users)));
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

    public function getParticipantRecipient(string $participantId): ?MailRecipientDTO
    {
        $participant = ParticipantModel::query()
            ->where('id', $participantId)
            ->with('user')
            ->first();

        if ($participant === null) {
            return null;
        }

        if ($participant->user_id !== null) {
            return $participant->user instanceof UserModel ? $this->account($participant->user) : null;
        }

        return $participant->email !== null && $participant->email !== ''
            ? MailRecipientDTO::address($participant->email, $participant->display_name)
            : null;
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

    private function account(UserModel $user): ?MailRecipientDTO
    {
        $email = (string) $user->email;

        return $email !== '' ? MailRecipientDTO::account((string) $user->id, $email, (string) $user->name) : null;
    }

    /**
     * One recipient per address (case-insensitive), the first one kept.
     *
     * @param  array<int, MailRecipientDTO|null>  $recipients
     * @return list<MailRecipientDTO>
     */
    private function uniqueByAddress(array $recipients): array
    {
        $unique = [];

        foreach ($recipients as $recipient) {
            if ($recipient instanceof MailRecipientDTO) {
                $unique[mb_strtolower($recipient->email)] ??= $recipient;
            }
        }

        return array_values($unique);
    }
}
