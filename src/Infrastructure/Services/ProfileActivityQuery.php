<?php

declare(strict_types=1);

namespace Modules\GameTables\Infrastructure\Services;

use App\Application\Services\EmailVerificationPolicyInterface;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Modules\GameTables\Application\DTOs\ProfileCampaignItemDTO;
use Modules\GameTables\Application\DTOs\ProfileTableItemDTO;
use Modules\GameTables\Application\Services\ProfileActivityQueryInterface;
use Modules\GameTables\Domain\Enums\CampaignStatus;
use Modules\GameTables\Domain\Enums\FrontendCreationStatus;
use Modules\GameTables\Domain\Enums\ParticipantRole;
use Modules\GameTables\Domain\Enums\ParticipantStatus;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\CampaignModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameMasterModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameTableModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\ParticipantModel;

/**
 * Who directs a table is GameTableModel::scopeDirectedBy(), the SQL form of the module's one rule
 * (effectiveFrom()). Every rule is a subquery of the list it filters, so a profile costs the same few queries however many
 * tables and campaigns the owner or the viewer has.
 */
final readonly class ProfileActivityQuery implements ProfileActivityQueryInterface
{
    /** The campaign statuses the profile lists as going on */
    private const array ACTIVE_CAMPAIGN_STATUSES = [
        CampaignStatus::Recruiting->value,
        CampaignStatus::Active->value,
        CampaignStatus::OnHold->value,
    ];

    public function __construct(
        private EmailVerificationPolicyInterface $verification,
    ) {}

    public function upcomingTablesRunBy(string $ownerId, int $limit): array
    {
        return $this->tableItems($this->upcomingTables()->directedBy($ownerId, namePublic: true), $limit);
    }

    public function countUpcomingTablesRunWithoutPublicNameBy(string $ownerId): int
    {
        return $this->upcomingTables()
            ->directedBy($ownerId, namePublic: false)
            ->whereNot(fn (Builder $query) => $query->directedBy($ownerId, namePublic: true))
            ->count();
    }

    public function upcomingTablesPlayedBy(string $ownerId, ?Authenticatable $viewer, int $limit): array
    {
        $query = $this->playedTablesShownTo($this->upcomingTables(), $ownerId, $viewer);

        return $query instanceof Builder ? $this->tableItems($query, $limit) : [];
    }

    public function recentTablesPlayedBy(string $ownerId, ?Authenticatable $viewer, int $limit): array
    {
        $query = $this->playedTablesShownTo($this->startedTables(), $ownerId, $viewer);

        return $query instanceof Builder ? $this->tableItems($query, $limit, 'desc') : [];
    }

    public function countTablesPlayedBy(string $ownerId): int
    {
        return $this->startedTables()
            ->whereHas('participants', fn (Builder $participant) => $this->confirmedPlayer($participant, $ownerId))
            ->count();
    }

    public function countTablesRunBy(string $ownerId): int
    {
        return GameTableModel::query()
            ->publiclyListed()
            ->directedBy($ownerId, namePublic: true)
            ->count();
    }

    public function activeCampaignsOf(string $ownerId, ?Authenticatable $viewer, int $limit): array
    {
        $campaigns = $this->campaignsOf($ownerId, $viewer)
            ->whereIn('status', self::ACTIVE_CAMPAIGN_STATUSES)
            ->with('gameSystem')
            ->withExists(['gameMasters as directed_by_owner' => function (Builder $gm) use ($ownerId): void {
                /** @var Builder<GameMasterModel> $gm */
                $gm->linkedTo($ownerId, true);
            }])
            ->orderBy('title')
            ->orderBy('id')
            ->limit($limit)
            ->get();

        return array_values($campaigns->map(fn (CampaignModel $campaign): ProfileCampaignItemDTO => new ProfileCampaignItemDTO(
            id: $campaign->id,
            title: $campaign->title,
            url: route('campaigns.show', $campaign->slug ?? $campaign->id, false),
            gameSystemName: $campaign->gameSystem->name ?? '',
            status: $campaign->status,
            role: (bool) $campaign->getAttribute('directed_by_owner') ? ProfileCampaignItemDTO::ROLE_DIRECTOR : ProfileCampaignItemDTO::ROLE_PLAYER,
        ))->all());
    }

    public function countCampaignsOf(string $ownerId): int
    {
        return CampaignModel::query()
            ->publiclyListed()
            ->where(fn (Builder $query) => $query
                ->whereHas('gameMasters', fn (Builder $gm) => $gm->linkedTo($ownerId, true))
                ->orWhereExists(fn (QueryBuilder $roster) => $this->rosterOf($roster, $ownerId, 'gametables_campaigns.id')
                    ->whereExists(fn (QueryBuilder $account) => $this->activeAccount($account, 'gametables_campaign_players.user_id'))))
            ->count();
    }

    private function viewerId(?Authenticatable $viewer): ?string
    {
        return $viewer instanceof Authenticatable ? (string) $viewer->getAuthIdentifier() : null;
    }

    /**
     * «Gestionar inscritos» and the roster page sit behind the sign-in and the email verification
     * (EnsureEmailIsVerifiedWhenRequired), and an anonymized account directs nothing. Fails closed: an
     * account that is not a site user, or a check that throws, opens neither.
     */
    private function mayOpenManagementPages(?Authenticatable $viewer): bool
    {
        if (! $viewer instanceof UserModel || $viewer->anonymized_at !== null) {
            return false;
        }

        try {
            return ! $this->verification->mustVerify($viewer);
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * The tables among `$tables` where the owner holds a confirmed player seat, as far as the viewer
     * may see the players: the owner themselves, or who opens the players list of that table. Null
     * when the viewer may see none of them.
     *
     * @param  Builder<GameTableModel>  $tables
     * @return Builder<GameTableModel>|null
     */
    private function playedTablesShownTo(Builder $tables, string $ownerId, ?Authenticatable $viewer): ?Builder
    {
        $viewerId = $this->viewerId($viewer);

        if ($viewerId === null) {
            return null;
        }

        $query = $tables->whereHas('participants', fn (Builder $participant) => $this->confirmedPlayer($participant, $ownerId));

        if ($viewerId === $ownerId) {
            return $query;
        }

        if (! $this->mayOpenManagementPages($viewer)) {
            return null;
        }

        return $query->where(fn (Builder $visible) => $visible
            // «Gestionar inscritos» (GameTablePolicy::manageRegistrations) lists the players of the table
            ->where(fn (Builder $table) => $this->registrationsManagedBy($table, $viewerId))
            // The roster page (CampaignPolicy::manageRoster) lists the sessions of its roster players
            ->orWhere(fn (Builder $session) => $session
                ->whereHas('campaign', fn (Builder $campaign) => $this->rosterManagedBy($campaign, $viewerId))
                ->whereExists(fn (QueryBuilder $roster) => $this->rosterOf($roster, $ownerId, 'gametables_tables.campaign_id'))));
    }

    /**
     * A confirmed player seat of the owner, while their account is not anonymized: an erased account
     * plays nothing on its profile.
     *
     * @param  Builder<ParticipantModel>  $participant
     */
    private function confirmedPlayer(Builder $participant, string $ownerId): void
    {
        $participant
            ->where('gametables_participants.user_id', $ownerId)
            ->where('gametables_participants.role', ParticipantRole::Player->value)
            ->where('gametables_participants.status', ParticipantStatus::Confirmed->value)
            ->whereExists(fn (QueryBuilder $account) => $this->activeAccount($account, 'gametables_participants.user_id'));
    }

    private function activeAccount(QueryBuilder $account, string $userColumn): void
    {
        $account->selectRaw('1')
            ->from('users')
            ->whereColumn('users.id', $userColumn)
            ->whereNull('users.anonymized_at');
    }

    /**
     * Listed tables not started yet, as the tables listing shows them (a published session of an
     * unpublished campaign included, like its own page).
     *
     * @return Builder<GameTableModel>
     */
    private function upcomingTables(): Builder
    {
        return GameTableModel::query()->publiclyListed()->where('gametables_tables.starts_at', '>', now());
    }

    /**
     * Listed tables already started (one starting right now included), what upcomingTables() no
     * longer holds, and, for a campaign session, of a published campaign: what the profile counts as
     * played is what SessionActivitySource reports to other modules.
     *
     * @return Builder<GameTableModel>
     */
    private function startedTables(): Builder
    {
        return GameTableModel::query()
            ->publiclyListed()
            ->where('gametables_tables.starts_at', '<=', now())
            ->where(fn (Builder $query) => $query
                ->whereNull('gametables_tables.campaign_id')
                ->orWhereHas('campaign', fn (Builder $campaign) => $campaign->where('gametables_campaigns.is_published', true)));
    }

    /**
     * @param  Builder<GameTableModel>  $query
     * @param  'asc'|'desc'  $direction
     * @return list<ProfileTableItemDTO>
     */
    private function tableItems(Builder $query, int $limit, string $direction = 'asc'): array
    {
        $tables = $query->with('gameSystem')->orderedByStart($direction)->limit($limit)->get();

        return array_values($tables->map(fn (GameTableModel $table): ProfileTableItemDTO => new ProfileTableItemDTO(
            id: $table->id,
            title: $table->title,
            url: route('gametables.show', $table->slug ?? $table->id, false),
            startsAt: $table->starts_at->toDateTimeImmutable(),
            gameSystemName: $table->gameSystem->name ?? '',
        ))->all());
    }

    /**
     * GameTablePolicy::manageRegistrations as a subquery: a table of its own (not a campaign session),
     * published, delegating its registrations and directed by the viewer.
     *
     * @param  Builder<GameTableModel>  $table
     */
    private function registrationsManagedBy(Builder $table, string $viewerId): void
    {
        $table->whereNull('campaign_id')
            ->where('delegates_registrations', true)
            ->where('is_published', true)
            ->directedBy($viewerId);
    }

    /**
     * CampaignPolicy::manageRoster as a subquery: an approved or published campaign directed by the
     * viewer as CampaignDirection reads it (its web creator or one of its game masters).
     *
     * @param  Builder<CampaignModel>  $campaign
     */
    private function rosterManagedBy(Builder $campaign, string $viewerId): void
    {
        $campaign
            ->where(fn (Builder $open) => $open
                ->where('gametables_campaigns.frontend_creation_status', FrontendCreationStatus::Approved->value)
                ->orWhere('gametables_campaigns.is_published', true))
            ->where(fn (Builder $directed) => $directed
                ->where(fn (Builder $web) => $web
                    ->where('gametables_campaigns.created_by', $viewerId)
                    ->whereNotNull('gametables_campaigns.frontend_creation_status'))
                ->orWhereHas('gameMasters', fn (Builder $gm) => $gm->linkedTo($viewerId)));
    }

    /**
     * The owner's listed campaigns (any status but cancelled) they direct under a public name or, as
     * far as the viewer may see the roster, play in.
     *
     * @return Builder<CampaignModel>
     */
    private function campaignsOf(string $ownerId, ?Authenticatable $viewer): Builder
    {
        $viewerId = $this->viewerId($viewer);
        $isOwner = $viewerId === $ownerId;
        // Anybody else sees only the roster places of the rosters they manage
        $rosterManager = $viewerId !== null && ! $isOwner && $this->mayOpenManagementPages($viewer) ? $viewerId : null;

        return CampaignModel::query()
            ->publiclyListed()
            ->where(function (Builder $query) use ($ownerId, $isOwner, $rosterManager): void {
                $query->whereHas('gameMasters', fn (Builder $gm) => $gm->linkedTo($ownerId, true));

                if ($isOwner || $rosterManager !== null) {
                    $query->orWhere(function (Builder $played) use ($ownerId, $rosterManager): void {
                        if ($rosterManager !== null) {
                            $this->rosterManagedBy($played, $rosterManager);
                        }

                        $played->whereExists(fn (QueryBuilder $roster) => $this->rosterOf($roster, $ownerId, 'gametables_campaigns.id'));
                    });
                }
            });
    }

    private function rosterOf(QueryBuilder $roster, string $ownerId, string $campaignColumn): QueryBuilder
    {
        return $roster->selectRaw('1')
            ->from('gametables_campaign_players')
            ->whereColumn('gametables_campaign_players.campaign_id', $campaignColumn)
            ->where('gametables_campaign_players.user_id', $ownerId);
    }
}
