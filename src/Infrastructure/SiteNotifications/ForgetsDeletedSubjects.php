<?php

declare(strict_types=1);

namespace Modules\GameTables\Infrastructure\SiteNotifications;

use App\Application\Mail\Services\MailSubjectMuteServiceInterface;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Support\Facades\DB;
use Modules\GameTables\Infrastructure\Mail\GameTablesMailSubjects;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\CampaignModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameTableModel;

/**
 * A deleted table or campaign is forgotten by the core: its mutes leave the profile and its
 * notices leave the bell, so nothing links to a page that is gone. Every way of deleting one
 * goes through its model (the web, the panel, the repositories), except the cascade of an
 * account deleted for good, which takes the records it created: they are listed before it goes.
 * Only once the deletion is committed: a rolled-back one keeps everything.
 */
final class ForgetsDeletedSubjects
{
    /**
     * Records of each account being deleted for good, by account id, until it is gone.
     *
     * @var array<string, array{tables: list<string>, campaigns: list<string>}>
     */
    private static array $recordsOfDeletedCreators = [];

    public static function register(): void
    {
        GameTableModel::deleted(static function (GameTableModel $table): void {
            self::forget(GameTablesMailSubjects::TABLE, [(string) $table->getKey()]);
        });

        CampaignModel::deleted(static function (CampaignModel $campaign): void {
            self::forget(GameTablesMailSubjects::CAMPAIGN, [(string) $campaign->getKey()]);
        });

        UserModel::forceDeleting(static function (UserModel $user): void {
            self::$recordsOfDeletedCreators[(string) $user->id] = [
                'tables' => self::ids(GameTableModel::query()->where('created_by', $user->id)->pluck('id')->all()),
                'campaigns' => self::ids(CampaignModel::query()->where('created_by', $user->id)->pluck('id')->all()),
            ];
        });

        UserModel::forceDeleted(static function (UserModel $user): void {
            $records = self::$recordsOfDeletedCreators[(string) $user->id] ?? ['tables' => [], 'campaigns' => []];
            unset(self::$recordsOfDeletedCreators[(string) $user->id]);

            self::forget(GameTablesMailSubjects::TABLE, $records['tables']);
            self::forget(GameTablesMailSubjects::CAMPAIGN, $records['campaigns']);
        });
    }

    /**
     * @param  list<string>  $ids
     */
    private static function forget(string $type, array $ids): void
    {
        if ($ids === []) {
            return;
        }

        DB::afterCommit(static function () use ($type, $ids): void {
            $mutes = app(MailSubjectMuteServiceInterface::class);

            foreach ($ids as $id) {
                $mutes->forgetSubject($type, $id);
            }
        });
    }

    /**
     * @param  array<int, mixed>  $ids
     * @return list<string>
     */
    private static function ids(array $ids): array
    {
        return array_values(array_map(static fn (mixed $id): string => is_scalar($id) ? (string) $id : '', $ids));
    }
}
