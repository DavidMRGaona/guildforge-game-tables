<?php

declare(strict_types=1);

namespace Modules\GameTables\Infrastructure\Activity;

use App\Application\Activity\Services\ActivityPublisherInterface;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Modules\GameTables\Domain\Enums\CampaignStatus;
use Modules\GameTables\Domain\Enums\TableStatus;
use Modules\GameTables\Domain\Events\CampaignInvitationAccepted;
use Modules\GameTables\Domain\Events\CampaignJoinRequestAccepted;
use Modules\GameTables\Domain\Events\CampaignRosterMemberAdded;
use Modules\GameTables\Domain\Events\CampaignRosterMemberLeft;
use Modules\GameTables\Domain\Events\CampaignRosterMemberRemoved;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\CampaignModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameMasterModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameSystemModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameTableModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\ParticipantModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Pivots\CampaignGameMasterPivot;

/**
 * Tells the core (ActivityPublisherInterface, core#774) whenever the facts of a completed session
 * or campaign may have changed, whatever made the change (services, panel, automatic updates), so
 * whoever keeps a record of them reads them again:
 *
 * - a table or campaign saved while completed, or that stops being completed (completing it
 *   through complete() or the status field, publishing or unpublishing it, its creator);
 * - a registration saved or deleted on a completed table (a player confirmed, cancelled or removed);
 * - the direction of a completed table (GameMasterService), of a completed campaign and of its
 *   completed sessions, which inherit it; a game master linked to another account or deleted;
 * - the roster of a completed campaign;
 * - a campaign published or unpublished, for its completed sessions (they count only with it);
 * - deleting a completed table or campaign (and the sessions a deleted campaign leaves on their
 *   own), a game system (its tables go with it) or an account for good (its tables, campaigns,
 *   registrations and roster places go with it, through the foreign keys);
 * - a game system renamed, since facts carry its name.
 *
 * The publisher announces after the commit, once per subject and transaction; publishing a
 * subject whose facts did not change is harmless. Changes to tables or campaigns that are not
 * completed say nothing: they have no facts.
 */
final class AnnouncesActivityChanges
{
    /** The campaign roster, written by query builder (CampaignRosterRepositoryInterface) */
    private const string ROSTER_TABLE = 'gametables_campaign_players';

    /**
     * Completed sessions of each campaign being deleted, until it is gone.
     *
     * @var array<string, list<string>>
     */
    private static array $sessionsOfDeletedCampaigns = [];

    /**
     * What each game master, game system or account being deleted took part in, until it is gone.
     *
     * @var array<string, array{sessions: list<string>, campaigns: list<string>}>
     */
    private static array $subjectsOfDeleted = [];

    public static function register(): void
    {
        GameTableModel::saved(static function (GameTableModel $table): void {
            if ($table->status === TableStatus::Completed || self::wasCompleted($table, TableStatus::Completed->value)) {
                self::session((string) $table->getKey());
            }
        });
        GameTableModel::deleted(static function (GameTableModel $table): void {
            if ($table->status === TableStatus::Completed) {
                self::session((string) $table->getKey());
            }
        });

        ParticipantModel::saved(static fn (ParticipantModel $participant) => self::registrationChanged($participant));
        ParticipantModel::deleted(static fn (ParticipantModel $participant) => self::registrationChanged($participant));

        CampaignModel::saved(static function (CampaignModel $campaign): void {
            $id = (string) $campaign->getKey();

            if ($campaign->status === CampaignStatus::Completed || self::wasCompleted($campaign, CampaignStatus::Completed->value)) {
                self::campaign($id);
            }

            // Its sessions count only while it is published
            if ($campaign->wasChanged('is_published')) {
                self::sessions(self::completedSessionsOf([$id]));
            }
        });
        // Deleting a campaign leaves its sessions as tables on their own (`set null`, no model
        // event), without the direction they inherited: read before the delete
        CampaignModel::deleting(static function (CampaignModel $campaign): void {
            self::$sessionsOfDeletedCampaigns[(string) $campaign->getKey()] = self::completedSessionsOf([(string) $campaign->getKey()]);
        });
        CampaignModel::deleted(static function (CampaignModel $campaign): void {
            $id = (string) $campaign->getKey();
            $sessions = self::$sessionsOfDeletedCampaigns[$id] ?? [];
            unset(self::$sessionsOfDeletedCampaigns[$id]);

            if ($campaign->status === CampaignStatus::Completed) {
                self::campaign($id);
            }

            self::sessions($sessions);
        });

        Event::listen(
            [CampaignRosterMemberAdded::class, CampaignRosterMemberRemoved::class, CampaignRosterMemberLeft::class, CampaignJoinRequestAccepted::class, CampaignInvitationAccepted::class],
            static function (CampaignRosterMemberAdded|CampaignRosterMemberRemoved|CampaignRosterMemberLeft|CampaignJoinRequestAccepted|CampaignInvitationAccepted $event): void {
                if (CampaignModel::query()->whereKey($event->campaignId)->where('status', CampaignStatus::Completed->value)->exists()) {
                    self::campaign($event->campaignId);
                }
            },
        );

        // The direction of a campaign, which its sessions inherit
        CampaignGameMasterPivot::created(static fn (CampaignGameMasterPivot $pivot) => self::campaignDirectionChanged($pivot->campaign_id));
        CampaignGameMasterPivot::deleted(static fn (CampaignGameMasterPivot $pivot) => self::campaignDirectionChanged($pivot->campaign_id));

        GameMasterModel::saved(static function (GameMasterModel $gameMaster): void {
            if ($gameMaster->wasChanged('user_id')) {
                self::announce(self::directedWith((string) $gameMaster->getKey()));
            }
        });
        GameMasterModel::deleting(static function (GameMasterModel $gameMaster): void {
            self::$subjectsOfDeleted['game-master|'.$gameMaster->getKey()] = self::directedWith((string) $gameMaster->getKey());
        });
        GameMasterModel::deleted(static fn (GameMasterModel $gameMaster) => self::announceDeleted('game-master|'.$gameMaster->getKey()));

        GameSystemModel::saved(static function (GameSystemModel $system): void {
            if ($system->wasChanged('name')) {
                self::announce(self::playedWith((string) $system->getKey()));
            }
        });
        GameSystemModel::deleting(static function (GameSystemModel $system): void {
            self::$subjectsOfDeleted['game-system|'.$system->getKey()] = self::playedWith((string) $system->getKey());
        });
        GameSystemModel::deleted(static fn (GameSystemModel $system) => self::announceDeleted('game-system|'.$system->getKey()));

        UserModel::forceDeleting(static function (UserModel $user): void {
            self::$subjectsOfDeleted['account|'.$user->id] = self::takenPartBy($user->id);
        });
        UserModel::forceDeleted(static fn (UserModel $user) => self::announceDeleted('account|'.$user->id));
    }

    /**
     * The direction of a table changed (its own game masters, or those it inherits that it
     * excludes): pivot writes fire no model event, so GameMasterService says so.
     */
    public static function tableDirectionChanged(string $tableId): void
    {
        if (GameTableModel::query()->whereKey($tableId)->where('status', TableStatus::Completed->value)->exists()) {
            self::session($tableId);
        }
    }

    private static function campaignDirectionChanged(string $campaignId): void
    {
        if (CampaignModel::query()->whereKey($campaignId)->where('status', CampaignStatus::Completed->value)->exists()) {
            self::campaign($campaignId);
        }

        self::sessions(self::completedSessionsOf([$campaignId]));
    }

    /**
     * A registration changed: only a completed table has facts. Its table before the save too,
     * should it ever be moved.
     */
    private static function registrationChanged(ParticipantModel $participant): void
    {
        $tables = array_values(array_unique(array_filter(
            [$participant->game_table_id, $participant->getRawOriginal('game_table_id')],
            is_string(...),
        )));

        self::sessions(self::ids(GameTableModel::query()->whereKey($tables)->where('status', TableStatus::Completed->value)));
    }

    /**
     * It was completed before this save, and no longer is.
     */
    private static function wasCompleted(GameTableModel|CampaignModel $record, string $completed): bool
    {
        return $record->wasChanged('status') && $record->getRawOriginal('status') === $completed;
    }

    /**
     * Completed sessions and campaigns a game master directs: tables with a row of theirs (their
     * own or an exclusion), campaigns they direct and the sessions those campaigns pass it on to.
     *
     * @return array{sessions: list<string>, campaigns: list<string>}
     */
    private static function directedWith(string $gameMasterId): array
    {
        $campaigns = self::ids(CampaignModel::query()->whereHas('gameMasters', static fn (Builder $query): Builder => $query->whereKey($gameMasterId)));

        return [
            'sessions' => array_values(array_unique([
                ...self::ids(GameTableModel::query()
                    ->where('status', TableStatus::Completed->value)
                    ->whereHas('gameMasters', static fn (Builder $query): Builder => $query->whereKey($gameMasterId))),
                ...self::completedSessionsOf($campaigns),
            ])),
            'campaigns' => self::ids(CampaignModel::query()->whereKey($campaigns)->where('status', CampaignStatus::Completed->value)),
        ];
    }

    /**
     * Completed sessions and campaigns played with a game system.
     *
     * @return array{sessions: list<string>, campaigns: list<string>}
     */
    private static function playedWith(string $systemId): array
    {
        return [
            'sessions' => self::ids(GameTableModel::query()->where('game_system_id', $systemId)->where('status', TableStatus::Completed->value)),
            'campaigns' => self::ids(CampaignModel::query()->where('game_system_id', $systemId)->where('status', CampaignStatus::Completed->value)),
        ];
    }

    /**
     * Completed sessions and campaigns an account created, played, ran or was on the roster of.
     *
     * @return array{sessions: list<string>, campaigns: list<string>}
     */
    private static function takenPartBy(string $userId): array
    {
        $completedTables = static fn (): Builder => GameTableModel::query()->where('status', TableStatus::Completed->value);
        $completedCampaigns = static fn (): Builder => CampaignModel::query()->where('status', CampaignStatus::Completed->value);

        return [
            'sessions' => array_values(array_unique([
                ...self::ids($completedTables()->where('created_by', $userId)),
                ...self::ids($completedTables()->whereHas('participants', static fn (Builder $query): Builder => $query->where('user_id', $userId))),
                ...self::ids($completedTables()->directedBy($userId)),
            ])),
            'campaigns' => array_values(array_unique([
                ...self::ids($completedCampaigns()->where('created_by', $userId)),
                ...self::ids($completedCampaigns()->whereIn('id', DB::table(self::ROSTER_TABLE)->select('campaign_id')->where('user_id', $userId))),
                ...self::ids($completedCampaigns()->whereHas('gameMasters', static fn (Builder $query): Builder => $query->where('gametables_game_masters.user_id', $userId))),
            ])),
        ];
    }

    /**
     * @param  list<string>  $campaignIds
     * @return list<string>
     */
    private static function completedSessionsOf(array $campaignIds): array
    {
        return $campaignIds === [] ? [] : self::ids(GameTableModel::query()
            ->whereIn('campaign_id', $campaignIds)
            ->where('status', TableStatus::Completed->value));
    }

    /**
     * @param  Builder<GameTableModel>|Builder<CampaignModel>  $query
     * @return list<string>
     */
    private static function ids(Builder $query): array
    {
        return array_values(array_map(strval(...), $query->pluck($query->getModel()->getQualifiedKeyName())->all()));
    }

    private static function announceDeleted(string $key): void
    {
        $subjects = self::$subjectsOfDeleted[$key] ?? ['sessions' => [], 'campaigns' => []];
        unset(self::$subjectsOfDeleted[$key]);

        self::announce($subjects);
    }

    /**
     * @param  array{sessions: list<string>, campaigns: list<string>}  $subjects
     */
    private static function announce(array $subjects): void
    {
        self::sessions($subjects['sessions']);

        foreach ($subjects['campaigns'] as $id) {
            self::campaign($id);
        }
    }

    /**
     * @param  list<string>  $ids
     */
    private static function sessions(array $ids): void
    {
        foreach ($ids as $id) {
            self::session($id);
        }
    }

    private static function session(string $id): void
    {
        app(ActivityPublisherInterface::class)->recorded(SessionActivitySource::TYPE, $id);
    }

    private static function campaign(string $id): void
    {
        app(ActivityPublisherInterface::class)->recorded(CampaignActivitySource::TYPE, $id);
    }
}
