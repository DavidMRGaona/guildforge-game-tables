<?php

declare(strict_types=1);

namespace Modules\GameTables\Infrastructure\Observers;

use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;
use Modules\GameTables\Application\Services\CampaignRosterSessionSyncInterface;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameTableModel;
use Throwable;

/**
 * Enrols the campaign roster when a session becomes published and open, whatever saved it: the
 * service (approval, automatic publication, the panel «Publicar» action), the panel form (edit or
 * create) or a change of campaign. It runs once the save is committed.
 *
 * Game masters are not copied from campaigns: they are inherited through the campaign
 * relationship (effective_game_masters).
 */
final class GameTableObserver implements ShouldHandleEventsAfterCommit
{
    public function __construct(
        private readonly CampaignRosterSessionSyncInterface $sync,
    ) {}

    public function saved(GameTableModel $table): void
    {
        if (! $this->opensForTheRoster($table)) {
            return;
        }

        try {
            $this->sync->onSessionPublished((string) $table->getKey());
        } catch (Throwable $e) {
            // The table is already saved: a failed enrolment must not break that save
            report($e);
        }
    }

    private function opensForTheRoster(GameTableModel $table): bool
    {
        return $table->campaign_id !== null
            && $table->is_published
            && $table->status->isRegistrable()
            && ($this->wasJustCreated($table) || $table->wasChanged(['is_published', 'status', 'campaign_id']));
    }

    /**
     * wasRecentlyCreated stays true for the life of the instance; an insert syncs no changes, so a
     * later update of the same instance (which does) is not taken for its creation.
     */
    private function wasJustCreated(GameTableModel $table): bool
    {
        return $table->wasRecentlyCreated && $table->getChanges() === [];
    }
}
