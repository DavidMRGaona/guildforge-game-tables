<?php

declare(strict_types=1);

namespace Modules\GameTables\Infrastructure\Activity;

use App\Application\Activity\Services\ActivityPublisherInterface;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Modules\GameTables\Domain\Enums\CampaignStatus;
use Modules\GameTables\Domain\Enums\ParticipantRole;
use Modules\GameTables\Domain\Enums\ParticipantStatus;
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
use WeakMap;

/**
 * Tells the core (ActivityPublisherInterface, core#774) whenever the facts of a completed session
 * or campaign may have changed, whatever made the change (services, panel, automatic updates), so
 * whoever keeps a record of them reads them again:
 *
 * - a table saved while completed, or that stops being completed, when what its facts are built
 *   from changed (status, publication, campaign, creator, game system, start): completing it
 *   through complete() or the status field, publishing or unpublishing it;
 * - its campaign too, when completed, if a completed session of it changed its status, start or
 *   campaign or was deleted: a campaign took place at its last completed session;
 * - a registration on a completed table that makes or made somebody a confirmed player with an
 *   account (confirmed, cancelled, removed, handed to an account);
 * - the direction of a completed table (GameMasterService), of a completed campaign and of its
 *   completed sessions, which inherit it; a game master linked to another account, showing or
 *   hiding their name, or deleted;
 * - a campaign saved while completed, or that stops being completed, when its status,
 *   publication, creator or game system changed; publishing or unpublishing it also for its
 *   completed sessions (they count only with it);
 * - the roster of a completed campaign (added, removed, left, join request or invitation accepted);
 * - deleting a completed table or campaign (and the sessions a deleted campaign leaves on their
 *   own), or an account for good (its tables, campaigns, registrations and roster places go with
 *   it through the foreign keys, and the sessions of the campaigns it created are left on their
 *   own);
 * - a game system renamed, since facts carry its name (one in use cannot be deleted: the foreign
 *   keys of tables and campaigns restrict it).
 *
 * The publisher announces after the commit, once per subject and transaction; publishing a
 * subject whose facts did not change is harmless. Changes to tables or campaigns that are not
 * completed say nothing: they have no facts.
 */
final class AnnouncesActivityChanges
{
    /** The campaign roster, written by query builder (CampaignRosterRepositoryInterface) */
    private const string ROSTER_TABLE = 'gametables_campaign_players';

    /** What a session's facts are built from, besides its registrations and direction */
    private const array TABLE_FACTS = ['status', 'is_published', 'campaign_id', 'created_by', 'game_system_id', 'starts_at'];

    /** What a campaign's facts are built from, besides its roster, direction and sessions */
    private const array CAMPAIGN_FACTS = ['status', 'is_published', 'created_by', 'game_system_id'];

    /** What makes a registration somebody who played */
    private const array REGISTRATION_FACTS = ['status', 'role', 'user_id', 'game_table_id'];

    /**
     * What a record being deleted took part in, read before the delete, until it is gone (keyed
     * by the model, as CampaignObserver does: nothing outlives the instance).
     *
     * @var WeakMap<Model, array{sessions: list<string>, campaigns: list<string>}>|null
     */
    private static ?WeakMap $subjectsOfDeleted = null;

    public static function register(): void
    {
        GameTableModel::saved(static function (GameTableModel $table): void {
            if (! self::isNew($table) && ! $table->wasChanged(self::TABLE_FACTS)) {
                return;
            }

            $counts = $table->status === TableStatus::Completed;

            if ($counts || self::wasCompleted($table, TableStatus::Completed->value)) {
                self::session((string) $table->getKey());

                if (self::isNew($table) || $table->wasChanged(['status', 'starts_at', 'campaign_id'])) {
                    self::completedCampaigns([$table->campaign_id, $table->getRawOriginal('campaign_id')]);
                }
            }
        });
        GameTableModel::deleted(static function (GameTableModel $table): void {
            if ($table->status === TableStatus::Completed) {
                self::session((string) $table->getKey());
                self::completedCampaigns([$table->campaign_id]);
            }
        });

        ParticipantModel::saved(static function (ParticipantModel $participant): void {
            $changed = self::isNew($participant) || $participant->wasChanged(self::REGISTRATION_FACTS);

            if ($changed && (self::playsNow($participant) || self::playedBefore($participant))) {
                self::registrationChanged($participant);
            }
        });
        ParticipantModel::deleted(static function (ParticipantModel $participant): void {
            if (self::playsNow($participant)) {
                self::registrationChanged($participant);
            }
        });

        CampaignModel::saved(static function (CampaignModel $campaign): void {
            if (! self::isNew($campaign) && ! $campaign->wasChanged(self::CAMPAIGN_FACTS)) {
                return;
            }

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
            self::remember($campaign, [
                'sessions' => self::completedSessionsOf([(string) $campaign->getKey()]),
                'campaigns' => $campaign->status === CampaignStatus::Completed ? [(string) $campaign->getKey()] : [],
            ]);
        });
        CampaignModel::deleted(static fn (CampaignModel $campaign) => self::announceDeleted($campaign));

        Event::listen(
            [CampaignRosterMemberAdded::class, CampaignRosterMemberRemoved::class, CampaignRosterMemberLeft::class, CampaignJoinRequestAccepted::class, CampaignInvitationAccepted::class],
            static function (CampaignRosterMemberAdded|CampaignRosterMemberRemoved|CampaignRosterMemberLeft|CampaignJoinRequestAccepted|CampaignInvitationAccepted $event): void {
                self::completedCampaigns([$event->campaignId]);
            },
        );

        // The direction of a campaign, which its sessions inherit
        CampaignGameMasterPivot::created(static fn (CampaignGameMasterPivot $pivot) => self::campaignDirectionChanged($pivot->campaign_id));
        CampaignGameMasterPivot::deleted(static fn (CampaignGameMasterPivot $pivot) => self::campaignDirectionChanged($pivot->campaign_id));

        GameMasterModel::saved(static function (GameMasterModel $gameMaster): void {
            // A game master who hides their name is never reported as a director
            if ($gameMaster->wasChanged(['user_id', 'is_name_public'])) {
                self::announce(self::directedWith((string) $gameMaster->getKey()));
            }
        });
        GameMasterModel::deleting(static fn (GameMasterModel $gameMaster) => self::remember($gameMaster, self::directedWith((string) $gameMaster->getKey())));
        GameMasterModel::deleted(static fn (GameMasterModel $gameMaster) => self::announceDeleted($gameMaster));

        GameSystemModel::saved(static function (GameSystemModel $system): void {
            if ($system->wasChanged('name')) {
                self::announce(self::playedWith((string) $system->getKey()));
            }
        });

        UserModel::forceDeleting(static fn (UserModel $user) => self::remember($user, self::takenPartBy($user->id)));
        UserModel::forceDeleted(static fn (UserModel $user) => self::announceDeleted($user));
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
        self::completedCampaigns([$campaignId]);
        self::sessions(self::completedSessionsOf([$campaignId]));
    }

    /**
     * A registration that makes or made somebody a player: only a completed table has facts. Its
     * table before the save too, should it ever be moved.
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
     * A confirmed player with an account, as the session's facts read it.
     */
    private static function playsNow(ParticipantModel $participant): bool
    {
        return $participant->role === ParticipantRole::Player
            && $participant->status === ParticipantStatus::Confirmed
            && $participant->user_id !== null;
    }

    private static function playedBefore(ParticipantModel $participant): bool
    {
        return ! self::isNew($participant)
            && $participant->getRawOriginal('role') === ParticipantRole::Player->value
            && $participant->getRawOriginal('status') === ParticipantStatus::Confirmed->value
            && $participant->getRawOriginal('user_id') !== null;
    }

    /**
     * Just inserted: wasRecentlyCreated stays true for the life of the instance, and an insert
     * syncs no changes, so a later update of the same instance is not taken for its creation.
     */
    private static function isNew(Model $record): bool
    {
        return $record->wasRecentlyCreated && $record->getChanges() === [];
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
     * Completed sessions and campaigns an account created, played, ran or was on the roster of,
     * and the completed sessions of the campaigns it created: they go with it at database level
     * and leave their sessions on their own.
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
                ...self::completedSessionsOf(self::ids(CampaignModel::query()->where('created_by', $userId))),
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
     * Publishes the campaigns given that are completed.
     *
     * @param  list<mixed>  $campaignIds
     */
    private static function completedCampaigns(array $campaignIds): void
    {
        $ids = array_values(array_unique(array_filter($campaignIds, is_string(...))));

        if ($ids === []) {
            return;
        }

        foreach (self::ids(CampaignModel::query()->whereKey($ids)->where('status', CampaignStatus::Completed->value)) as $id) {
            self::campaign($id);
        }
    }

    /**
     * @param  Builder<GameTableModel>|Builder<CampaignModel>  $query
     * @return list<string>
     */
    private static function ids(Builder $query): array
    {
        return array_values(array_map(strval(...), $query->pluck($query->getModel()->getQualifiedKeyName())->all()));
    }

    /**
     * @param  array{sessions: list<string>, campaigns: list<string>}  $subjects
     */
    private static function remember(Model $record, array $subjects): void
    {
        self::$subjectsOfDeleted ??= new WeakMap;
        self::$subjectsOfDeleted[$record] = $subjects;
    }

    private static function announceDeleted(Model $record): void
    {
        $subjects = self::$subjectsOfDeleted[$record] ?? ['sessions' => [], 'campaigns' => []];
        unset(self::$subjectsOfDeleted[$record]);

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
