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
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameTableModel;

/**
 * Every rule is a subquery of the list it filters, so a profile costs the same few queries however many
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
        return $this->tableItems($this->upcomingTables()->where(fn (Builder $query) => $this->runBy($query, $ownerId)), $limit);
    }

    public function countUpcomingTablesRunWithoutPublicNameBy(string $ownerId): int
    {
        return $this->upcomingTables()
            ->where(fn (Builder $query) => $this->runBy($query, $ownerId, publicName: false))
            ->whereNot(fn (Builder $query) => $this->runBy($query, $ownerId))
            ->count();
    }

    public function upcomingTablesPlayedBy(string $ownerId, ?Authenticatable $viewer, int $limit): array
    {
        $viewerId = $this->viewerId($viewer);

        if ($viewerId === null) {
            return [];
        }

        $query = $this->upcomingTables()->whereHas('participants', fn (Builder $participant) => $participant
            ->where('user_id', $ownerId)
            ->where('role', ParticipantRole::Player->value)
            ->where('status', ParticipantStatus::Confirmed->value));

        if ($viewerId !== $ownerId) {
            if (! $this->mayOpenManagementPages($viewer)) {
                return [];
            }

            $query->where(fn (Builder $visible) => $visible
                // «Gestionar inscritos» (GameTablePolicy::manageRegistrations) lists the players of the table
                ->where(fn (Builder $table) => $this->registrationsManagedBy($table, $viewerId))
                // The roster page (CampaignPolicy::manageRoster) lists the sessions of its roster players
                ->orWhere(fn (Builder $session) => $session
                    ->whereHas('campaign', fn (Builder $campaign) => $this->rosterManagedBy($campaign, $viewerId))
                    ->whereExists(fn (QueryBuilder $roster) => $this->rosterOf($roster, $ownerId, 'gametables_tables.campaign_id'))));
        }

        return $this->tableItems($query, $limit);
    }

    public function countTablesRunBy(string $ownerId): int
    {
        return GameTableModel::query()
            ->publiclyListed()
            ->where(fn (Builder $query) => $this->runBy($query, $ownerId))
            ->count();
    }

    public function activeCampaignsOf(string $ownerId, ?Authenticatable $viewer, int $limit): array
    {
        $campaigns = $this->campaignsOf($ownerId, $viewer)
            ->whereIn('status', self::ACTIVE_CAMPAIGN_STATUSES)
            ->with('gameSystem')
            ->withExists(['gameMasters as directed_by_owner' => fn (Builder $gm) => $this->gameMasterOf($gm, $ownerId, true)])
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

    public function countCampaignsOf(string $ownerId, ?Authenticatable $viewer): int
    {
        return $this->campaignsOf($ownerId, $viewer)->count();
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
     * @return Builder<GameTableModel>
     */
    private function upcomingTables(): Builder
    {
        return GameTableModel::query()->publiclyListed()->where('starts_at', '>=', now());
    }

    /**
     * @param  Builder<GameTableModel>  $query
     * @return list<ProfileTableItemDTO>
     */
    private function tableItems(Builder $query, int $limit): array
    {
        $tables = $query->with('gameSystem')->orderedByStart()->limit($limit)->get();

        return array_values($tables->map(fn (GameTableModel $table): ProfileTableItemDTO => new ProfileTableItemDTO(
            id: $table->id,
            title: $table->title,
            url: route('gametables.show', $table->slug ?? $table->id, false),
            startsAt: $table->starts_at->toDateTimeImmutable(),
            gameSystemName: $table->gameSystem->name ?? '',
        ))->all());
    }

    /**
     * The tables the owner directs as TableDirection reads it (local, or inherited from the campaign and
     * not excluded). With a public name only by default: the page never ties a game master whose name is
     * not public to their account; with `publicName: false`, only those.
     *
     * @param  Builder<GameTableModel>  $query
     */
    private function runBy(Builder $query, string $ownerId, bool $publicName = true): void
    {
        $query
            ->whereHas('localGameMasters', fn (Builder $gm) => $this->gameMasterOf($gm, $ownerId, $publicName))
            ->orWhere(fn (Builder $session) => $session
                ->whereNotNull('campaign_id')
                ->whereHas('campaign.gameMasters', fn (Builder $gm) => $this->gameMasterOf($gm, $ownerId, $publicName)
                    ->whereNotIn('gametables_game_masters.id', fn (QueryBuilder $excluded) => $excluded
                        ->select('gametables_table_gm.game_master_id')
                        ->from('gametables_table_gm')
                        ->whereColumn('gametables_table_gm.game_table_id', 'gametables_tables.id')
                        ->where('gametables_table_gm.excluded', true))));
    }

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $gm
     * @return Builder<TModel>
     */
    private function gameMasterOf(Builder $gm, string $userId, ?bool $publicName): Builder
    {
        $gm->where('gametables_game_masters.user_id', $userId);

        if ($publicName !== null) {
            $gm->where('gametables_game_masters.is_name_public', $publicName);
        }

        return $gm;
    }

    /**
     * GameTablePolicy::manageRegistrations as a subquery: a table of its own (not a campaign session),
     * published, delegating its registrations and directed by the viewer (a local game master; a table
     * without a campaign inherits none).
     *
     * @param  Builder<GameTableModel>  $table
     */
    private function registrationsManagedBy(Builder $table, string $viewerId): void
    {
        $table->whereNull('campaign_id')
            ->where('delegates_registrations', true)
            ->where('is_published', true)
            ->whereHas('localGameMasters', fn (Builder $gm) => $this->gameMasterOf($gm, $viewerId, null));
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
                ->orWhereHas('gameMasters', fn (Builder $gm) => $this->gameMasterOf($gm, $viewerId, null)));
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
                $query->whereHas('gameMasters', fn (Builder $gm) => $this->gameMasterOf($gm, $ownerId, true));

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

    private function rosterOf(QueryBuilder $roster, string $ownerId, string $campaignColumn): void
    {
        $roster->selectRaw('1')
            ->from('gametables_campaign_players')
            ->whereColumn('gametables_campaign_players.campaign_id', $campaignColumn)
            ->where('gametables_campaign_players.user_id', $ownerId);
    }
}
