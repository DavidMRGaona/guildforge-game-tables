<?php

declare(strict_types=1);

namespace Modules\GameTables\Infrastructure\Publications;

use App\Domain\Publications\Events\PublicationChanged;
use App\Domain\Publications\Events\PublicationRemoved;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Modules\GameTables\Domain\Events\CampaignInvitationAccepted;
use Modules\GameTables\Domain\Events\CampaignJoinRequestAccepted;
use Modules\GameTables\Domain\Events\CampaignRosterMemberAdded;
use Modules\GameTables\Domain\Events\CampaignRosterMemberLeft;
use Modules\GameTables\Domain\Events\CampaignRosterMemberRemoved;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\CampaignModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameSystemModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameTableModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\ParticipantModel;

/**
 * Tells publication listeners (core contract) when a table on its own or a campaign may have
 * changed, once the change commits, whatever saved it (services, panel, automatic updates):
 *
 * - saving a published table or campaign, or one just taken down or turned into a campaign session;
 * - a registration saved or deleted (its seats), for a published table on its own;
 * - a roster joined or left (its room), for the campaign;
 * - a game system renamed, for the published tables and campaigns that show its name;
 * - deleting them, also when an account deleted for good takes them along through the foreign key.
 *
 * Listeners read the record again, so a save that changed nothing they show is harmless; drafts
 * that stay drafts say nothing.
 */
final class AnnouncesPublicationChanges
{
    /**
     * Published tables and campaigns of each account being deleted for good, until it is gone.
     *
     * @var array<string, array{tables: list<string>, campaigns: list<string>}>
     */
    private static array $recordsOfDeletedCreators = [];

    public static function register(): void
    {
        GameTableModel::saved(static function (GameTableModel $table): void {
            if (self::concernsPublishedRecord($table) && ($table->campaign_id === null || $table->wasChanged('campaign_id'))) {
                self::changed(GameTablePublicationSource::TYPE, (string) $table->getKey());
            }
        });
        GameTableModel::deleted(static function (GameTableModel $table): void {
            if ($table->campaign_id === null) {
                self::removed(GameTablePublicationSource::TYPE, (string) $table->getKey());
            }
        });

        ParticipantModel::saved(static fn (ParticipantModel $participant) => self::seatsChanged($participant->game_table_id));
        ParticipantModel::deleted(static fn (ParticipantModel $participant) => self::seatsChanged($participant->game_table_id));

        CampaignModel::saved(static function (CampaignModel $campaign): void {
            if (self::concernsPublishedRecord($campaign)) {
                self::changed(CampaignPublicationSource::TYPE, (string) $campaign->getKey());
            }
        });
        CampaignModel::deleted(static fn (CampaignModel $campaign) => self::removed(CampaignPublicationSource::TYPE, (string) $campaign->getKey()));

        Event::listen(
            [CampaignRosterMemberAdded::class, CampaignRosterMemberRemoved::class, CampaignRosterMemberLeft::class, CampaignJoinRequestAccepted::class, CampaignInvitationAccepted::class],
            static fn (CampaignRosterMemberAdded|CampaignRosterMemberRemoved|CampaignRosterMemberLeft|CampaignJoinRequestAccepted|CampaignInvitationAccepted $event) => self::changed(CampaignPublicationSource::TYPE, $event->campaignId),
        );

        GameSystemModel::saved(static function (GameSystemModel $system): void {
            if ($system->wasChanged('name')) {
                self::recordsOfSystemChanged((string) $system->getKey());
            }
        });

        UserModel::forceDeleting(static function (UserModel $user): void {
            self::$recordsOfDeletedCreators[$user->id] = [
                'tables' => self::ids(GameTableModel::query()->where('is_published', true)->whereNull('campaign_id')->where('created_by', $user->id)),
                'campaigns' => self::ids(CampaignModel::query()->where('is_published', true)->where('created_by', $user->id)),
            ];
        });
        UserModel::forceDeleted(static function (UserModel $user): void {
            $records = self::$recordsOfDeletedCreators[$user->id] ?? ['tables' => [], 'campaigns' => []];
            unset(self::$recordsOfDeletedCreators[$user->id]);

            foreach ($records['tables'] as $id) {
                self::removed(GameTablePublicationSource::TYPE, $id);
            }

            foreach ($records['campaigns'] as $id) {
                self::removed(CampaignPublicationSource::TYPE, $id);
            }
        });
    }

    /**
     * Published now, or taken down by this very save.
     */
    private static function concernsPublishedRecord(GameTableModel|CampaignModel $record): bool
    {
        return $record->is_published || $record->wasChanged('is_published');
    }

    /**
     * The seats of a table changed: only a published table on its own is a publication. Read once
     * the change commits, which is also when the table is read back by listeners.
     */
    private static function seatsChanged(string $tableId): void
    {
        DB::afterCommit(static function () use ($tableId): void {
            $isPublication = GameTableModel::query()->whereKey($tableId)->where('is_published', true)->whereNull('campaign_id')->exists();

            if ($isPublication) {
                event(new PublicationChanged(GameTablePublicationSource::TYPE, $tableId));
            }
        });
    }

    private static function recordsOfSystemChanged(string $systemId): void
    {
        $tables = self::ids(GameTableModel::query()->where('is_published', true)->whereNull('campaign_id')->where('game_system_id', $systemId));
        $campaigns = self::ids(CampaignModel::query()->where('is_published', true)->where('game_system_id', $systemId));

        foreach ($tables as $id) {
            self::changed(GameTablePublicationSource::TYPE, $id);
        }

        foreach ($campaigns as $id) {
            self::changed(CampaignPublicationSource::TYPE, $id);
        }
    }

    /**
     * @param  Builder<GameTableModel>|Builder<CampaignModel>  $query
     * @return list<string>
     */
    private static function ids(Builder $query): array
    {
        return array_values(array_map(strval(...), $query->pluck('id')->all()));
    }

    private static function changed(string $type, string $id): void
    {
        DB::afterCommit(static fn () => event(new PublicationChanged($type, $id)));
    }

    private static function removed(string $type, string $id): void
    {
        DB::afterCommit(static fn () => event(new PublicationRemoved($type, $id)));
    }
}
