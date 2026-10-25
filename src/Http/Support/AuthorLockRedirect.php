<?php

declare(strict_types=1);

namespace Modules\GameTables\Http\Support;

use Illuminate\Http\RedirectResponse;
use Modules\GameTables\Domain\Enums\FrontendCreationStatus;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\CampaignModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameTableModel;

/**
 * The author of a table or campaign that is no longer theirs to change (sent to review or
 * published) goes back to «Mis mesas» / «Mis campañas» with the reason instead of a bare 403.
 * Anyone else gets null and still meets the usual policy. The controllers ask before acting and
 * the form requests before validating, so a stale edit form gets the reason, not field errors.
 * An action sent from the profile's drafts goes back to the profile instead (AuthorReturnTarget).
 */
final readonly class AuthorLockRedirect
{
    public function __construct(private AuthorReturnTarget $returnTarget) {}

    public function forTable(?GameTableModel $table, ?string $userId): ?RedirectResponse
    {
        if (! $table instanceof GameTableModel
            || $userId === null
            || $table->created_by !== $userId
            || FrontendCreationStatus::allowsAuthorChanges($table->frontend_creation_status, (bool) $table->is_published)) {
            return null;
        }

        $reason = match (true) {
            (bool) $table->is_published => 'game-tables::messages.errors.table_locked_published',
            $table->frontend_creation_status === FrontendCreationStatus::PendingReview => 'game-tables::messages.errors.table_locked_in_review',
            default => 'game-tables::messages.errors.table_locked',
        };

        return $this->returnTarget
            ->redirect('gametables.my-tables', AuthorReturnTarget::TABLES_TAB)
            ->with('error', __($reason));
    }

    public function forCampaign(?CampaignModel $campaign, ?string $userId): ?RedirectResponse
    {
        if (! $campaign instanceof CampaignModel
            || $userId === null
            || $campaign->created_by !== $userId
            || FrontendCreationStatus::allowsAuthorChanges($campaign->frontend_creation_status, (bool) $campaign->is_published)) {
            return null;
        }

        $reason = match (true) {
            (bool) $campaign->is_published => 'game-tables::messages.errors.campaign_locked_published',
            $campaign->frontend_creation_status === FrontendCreationStatus::PendingReview => 'game-tables::messages.errors.campaign_locked_in_review',
            default => 'game-tables::messages.errors.campaign_locked',
        };

        return $this->returnTarget
            ->redirect('campaigns.my-campaigns', AuthorReturnTarget::CAMPAIGNS_TAB)
            ->with('error', __($reason));
    }
}
