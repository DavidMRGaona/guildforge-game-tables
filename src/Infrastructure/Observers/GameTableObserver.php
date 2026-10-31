<?php

declare(strict_types=1);

namespace Modules\GameTables\Infrastructure\Observers;

use App\Domain\Surveys\Events\SurveySubjectCancelled;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;
use Modules\GameTables\Application\Services\CampaignRosterSessionSyncInterface;
use Modules\GameTables\Domain\Enums\TableStatus;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameTableModel;
use Modules\GameTables\Infrastructure\Surveys\GameTableSurveySubjectSource;
use Throwable;

/**
 * Enrols the campaign roster when a session becomes published and open, whatever saved it: the
 * service (approval, automatic publication, the panel «Publicar» action), the panel form (edit or
 * create) or a change of campaign. It runs once the save is committed.
 *
 * Game masters are not copied from campaigns: they are inherited through the campaign
 * relationship (effective_game_masters).
 *
 * Also tells a surveys module, through the core, that a table deleted before it ended will never
 * end.
 */
final readonly class GameTableObserver implements ShouldHandleEventsAfterCommit
{
    public function __construct(
        private CampaignRosterSessionSyncInterface $sync,
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

    /**
     * A table deleted before it ended will never end: a surveys module, if any, drops the surveys
     * prepared for its end (core event). Campaign sessions are another kind of subject.
     */
    public function deleted(GameTableModel $table): void
    {
        if ($table->campaign_id !== null || in_array($table->status, [TableStatus::Completed, TableStatus::Cancelled], true)) {
            return;
        }

        event(new SurveySubjectCancelled(GameTableSurveySubjectSource::TYPE, (string) $table->getKey(), now()->toDateTimeImmutable()));
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
