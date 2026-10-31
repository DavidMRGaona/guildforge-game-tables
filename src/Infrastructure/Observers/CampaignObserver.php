<?php

declare(strict_types=1);

namespace Modules\GameTables\Infrastructure\Observers;

use App\Domain\Surveys\Events\SurveySubjectCancelled;
use App\Domain\Surveys\Events\SurveySubjectEnded;
use Illuminate\Support\Facades\DB;
use Modules\GameTables\Domain\Enums\CampaignStatus;
use Modules\GameTables\Domain\Enums\TableStatus;
use Modules\GameTables\Domain\Events\CampaignStatusChanged;
use Modules\GameTables\Domain\ValueObjects\CampaignId;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\CampaignModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameTableModel;
use Modules\GameTables\Infrastructure\Surveys\CampaignSessionSurveySubjectSource;
use Modules\GameTables\Infrastructure\Surveys\CampaignSurveySubjectSource;
use WeakMap;

/**
 * A campaign's status only changes by saving the model (the panel's status field), so this is
 * where the module announces it, and tells a surveys module, through the core, that a campaign
 * ended or will never end. Everything goes out once the change commits.
 *
 * Not ShouldHandleEventsAfterCommit: deleting a campaign leaves its sessions as tables on their
 * own (`set null`), so they are read before the delete and announced after it.
 */
final class CampaignObserver
{
    /** @var WeakMap<CampaignModel, list<string>>|null sessions not over, read before the delete */
    private static ?WeakMap $pendingSessions = null;

    public function updated(CampaignModel $campaign): void
    {
        if (! $campaign->wasChanged('status')) {
            return;
        }

        $previous = CampaignStatus::from($this->statusValue($campaign->getOriginal('status')));
        $current = CampaignStatus::from($this->statusValue($campaign->status));
        $id = (string) $campaign->getKey();
        $now = now()->toDateTimeImmutable();

        DB::afterCommit(static function () use ($id, $previous, $current, $now): void {
            CampaignStatusChanged::dispatch(new CampaignId($id), $previous, $current);

            match ($current) {
                CampaignStatus::Completed => event(new SurveySubjectEnded(CampaignSurveySubjectSource::TYPE, $id, $now)),
                CampaignStatus::Cancelled => event(new SurveySubjectCancelled(CampaignSurveySubjectSource::TYPE, $id, $now)),
                default => null,
            };
        });
    }

    public function deleting(CampaignModel $campaign): void
    {
        /** @var list<string> $sessions */
        $sessions = GameTableModel::query()
            ->where('campaign_id', $campaign->getKey())
            ->whereNotIn('status', [TableStatus::Completed->value, TableStatus::Cancelled->value])
            ->pluck('id')
            ->map(static fn (mixed $id): string => (string) $id)
            ->all();

        self::$pendingSessions ??= new WeakMap;
        self::$pendingSessions[$campaign] = $sessions;
    }

    /**
     * A campaign deleted before it ended will never end, nor will its sessions still to come:
     * a surveys module, if any, drops the surveys prepared for their end.
     */
    public function deleted(CampaignModel $campaign): void
    {
        $sessions = self::$pendingSessions[$campaign] ?? [];
        unset(self::$pendingSessions[$campaign]);

        $id = (string) $campaign->getKey();
        $unfinished = ! CampaignStatus::from($this->statusValue($campaign->status))->isFinal();
        $now = now()->toDateTimeImmutable();

        DB::afterCommit(static function () use ($id, $unfinished, $sessions, $now): void {
            if ($unfinished) {
                event(new SurveySubjectCancelled(CampaignSurveySubjectSource::TYPE, $id, $now));
            }

            foreach ($sessions as $session) {
                event(new SurveySubjectCancelled(CampaignSessionSurveySubjectSource::TYPE, $session, $now));
            }
        });
    }

    private function statusValue(mixed $status): string
    {
        return $status instanceof CampaignStatus ? $status->value : (string) $status;
    }
}
