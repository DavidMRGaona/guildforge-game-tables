<?php

declare(strict_types=1);

namespace Modules\GameTables\Infrastructure\SiteNotifications;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Modules\GameTables\Application\DTOs\CampaignResponseDTO;
use Modules\GameTables\Application\DTOs\GameTableResponseDTO;
use Modules\GameTables\Application\Services\CampaignDirectionInterface;
use Modules\GameTables\Application\Services\CampaignInvitationServiceInterface;
use Modules\GameTables\Application\Services\CampaignQueryServiceInterface;
use Modules\GameTables\Application\Services\FrontendCreationServiceInterface;
use Modules\GameTables\Application\Services\GameTableQueryServiceInterface;
use Modules\GameTables\Application\Services\TableDirectionInterface;
use Modules\GameTables\Domain\Enums\FrontendCreationStatus;
use Modules\GameTables\Filament\Resources\CampaignResource;
use Modules\GameTables\Filament\Resources\GameTableResource;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\CampaignModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameTableModel;
use Modules\GameTables\Infrastructure\Services\NotificationRecipientResolver;

/**
 * Where a notice of the notification centre leads, asked when it is delivered: a path of the
 * site, or null when the person may no longer see or do what it is about (the notice is then
 * not stored). Each answer is the rule of the page it leads to, never a looser one.
 */
final readonly class SiteNoticeTargets
{
    /** The profile tab that lists the account's pending campaign invitations. */
    public const string INVITATIONS_TAB = 'gametables-invitations';

    public function __construct(
        private GameTableQueryServiceInterface $tables,
        private CampaignQueryServiceInterface $campaigns,
        private TableDirectionInterface $tableDirection,
        private CampaignDirectionInterface $campaignDirection,
        private CampaignInvitationServiceInterface $invitations,
        private FrontendCreationServiceInterface $creation,
        private NotificationRecipientResolver $recipients,
    ) {}

    /**
     * The table's page, while it is published (cancelled and finished ones too, like the page).
     */
    public function table(?string $tableId): ?string
    {
        $table = $this->publishedTable($tableId);

        return $table instanceof GameTableResponseDTO ? $this->tablePath($table) : null;
    }

    /**
     * For a director of the table: «Gestionar inscritos» when the notice asks them to approve
     * someone on a table that delegates its registrations, its page otherwise.
     */
    public function directedTable(UserModel $user, ?string $tableId, bool $awaitsApproval): ?string
    {
        if (! $this->isUuid($tableId) || ! $this->tableDirection->isDirector($tableId, (string) $user->id)) {
            return null;
        }

        $model = GameTableModel::query()->find($tableId);
        if ($awaitsApproval && $model instanceof GameTableModel && $model->delegates_registrations) {
            return route('gametables.registrations', $model->slug ?? $model->id, false);
        }

        return $this->table($tableId);
    }

    /**
     * The campaign's page, while it is published.
     */
    public function campaign(?string $campaignId): ?string
    {
        if (! $this->isUuid($campaignId)) {
            return null;
        }

        $campaign = $this->campaigns->findPublished($campaignId);

        return $campaign instanceof CampaignResponseDTO ? route('campaigns.show', $campaign->slug ?? $campaign->id, false) : null;
    }

    /**
     * The roster page, for whoever directs the campaign.
     */
    public function roster(UserModel $user, ?string $campaignId): ?string
    {
        if (! $this->isUuid($campaignId) || ! $this->campaignDirection->isDirector($campaignId, (string) $user->id)) {
            return null;
        }

        return route('campaigns.roster', $campaignId, false);
    }

    /**
     * The profile tab with the account's invitations, while this one is still pending for its
     * email. Never the link of the mail, which carries the token.
     */
    public function invitations(UserModel $user, ?string $invitationId): ?string
    {
        if ($invitationId === null || $invitationId === '') {
            return null;
        }

        foreach ($this->invitations->pendingForEmail($user->email, CarbonImmutable::now()->toDateTimeImmutable()) as $invitation) {
            if ($invitation->id === $invitationId) {
                return route('profile.show', ['tab' => self::INVITATIONS_TAB], false);
            }
        }

        return null;
    }

    /**
     * The author's edit page of a table they proposed, while the policy lets them change it.
     */
    public function ownTableEdit(UserModel $user, ?string $tableId): ?string
    {
        $table = $this->isUuid($tableId) ? GameTableModel::query()->find($tableId) : null;
        if (! $table instanceof GameTableModel || ! Gate::forUser($user)->allows('update', $table)) {
            return null;
        }

        return route('gametables.edit', $table->slug ?? $table->id, false);
    }

    /**
     * The author's edit page of a campaign they proposed, while it is one of their drafts.
     */
    public function ownCampaignEdit(UserModel $user, ?string $campaignId): ?string
    {
        if (! $this->isUuid($campaignId)) {
            return null;
        }

        foreach ($this->creation->getUserCampaignDrafts((string) $user->id) as $draft) {
            if ($draft->id === $campaignId) {
                return route('campaigns.frontend-edit', $campaignId, false);
            }
        }

        return null;
    }

    /**
     * The table's review in the panel, for a moderator, while it still waits for one.
     */
    public function tableReview(UserModel $user, ?string $tableId): ?string
    {
        $table = $this->isUuid($tableId) ? GameTableModel::query()->find($tableId) : null;
        if (! $table instanceof GameTableModel
            || $table->frontend_creation_status !== FrontendCreationStatus::PendingReview
            || ! $this->recipients->moderatesTables($user)) {
            return null;
        }

        // The panel finds game tables by slug
        return GameTableResource::getUrl('edit', ['record' => $table->slug ?? $table->id], isAbsolute: false);
    }

    public function campaignReview(UserModel $user, ?string $campaignId): ?string
    {
        $campaign = $this->isUuid($campaignId) ? CampaignModel::query()->find($campaignId) : null;
        if (! $campaign instanceof CampaignModel
            || $campaign->frontend_creation_status !== FrontendCreationStatus::PendingReview
            || ! $this->recipients->moderatesCampaigns($user)) {
            return null;
        }

        return CampaignResource::getUrl('edit', ['record' => $campaign->id], isAbsolute: false);
    }

    private function publishedTable(?string $tableId): ?GameTableResponseDTO
    {
        return $this->isUuid($tableId) ? $this->tables->findPublished($tableId) : null;
    }

    private function tablePath(GameTableResponseDTO $table): string
    {
        return route('gametables.show', $table->slug ?? $table->id, false);
    }

    /**
     * Keys are uuids: anything else matches no row, and PostgreSQL rejects it in the query.
     *
     * @phpstan-assert-if-true non-empty-string $id
     */
    private function isUuid(?string $id): bool
    {
        return $id !== null && Str::isUuid($id);
    }
}
