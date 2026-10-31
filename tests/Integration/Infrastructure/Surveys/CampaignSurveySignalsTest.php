<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Integration\Infrastructure\Surveys;

use App\Domain\Surveys\Events\SurveySubjectCancelled;
use App\Domain\Surveys\Events\SurveySubjectEnded;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Modules\GameTables\Domain\Enums\CampaignStatus;
use Modules\GameTables\Domain\Enums\TableStatus;
use Modules\GameTables\Domain\Events\CampaignStatusChanged;
use Modules\GameTables\Domain\Repositories\CampaignRepositoryInterface;
use Modules\GameTables\Domain\ValueObjects\CampaignId;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\CampaignModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameSystemModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameTableModel;
use RuntimeException;
use Tests\Support\Modules\ModuleTestCase;

/**
 * What a campaign tells the surveys module (through the core's events) when it is completed,
 * cancelled or deleted, whatever path saves it: the panel's status field is the usual one.
 */
final class CampaignSurveySignalsTest extends ModuleTestCase
{
    protected ?string $moduleName = 'game-tables';

    protected bool $autoEnableModule = true;

    private GameSystemModel $system;

    protected function setUp(): void
    {
        parent::setUp();

        $this->useUtcStorage();
        Event::fake([SurveySubjectEnded::class, SurveySubjectCancelled::class, CampaignStatusChanged::class]);
        $this->system = GameSystemModel::create(['id' => (string) Str::uuid(), 'name' => 'Sistema de ejemplo', 'slug' => Str::random(10), 'is_active' => true]);
    }

    public function test_completing_a_campaign_announces_its_end(): void
    {
        $campaign = $this->campaign(CampaignStatus::Active);
        $this->travelTo(new DateTimeImmutable('2030-03-10T20:00:00+00:00'));

        $campaign->update(['status' => CampaignStatus::Completed]);

        Event::assertDispatchedTimes(SurveySubjectEnded::class, 1);
        Event::assertDispatched(SurveySubjectEnded::class, fn (SurveySubjectEnded $event): bool => $event->sourceType === 'campaign'
            && $event->subjectId === $campaign->id
            && $event->endedAt == new DateTimeImmutable('2030-03-10T20:00:00+00:00'));
        Event::assertNotDispatched(SurveySubjectCancelled::class);
    }

    public function test_the_module_event_carries_both_statuses(): void
    {
        $campaign = $this->campaign(CampaignStatus::Recruiting);

        $campaign->update(['status' => CampaignStatus::Active]);

        Event::assertDispatchedTimes(CampaignStatusChanged::class, 1);
        Event::assertDispatched(CampaignStatusChanged::class, fn (CampaignStatusChanged $event): bool => $event->campaignId->value === $campaign->id
            && $event->previousStatus === CampaignStatus::Recruiting
            && $event->newStatus === CampaignStatus::Active);
    }

    public function test_cancelling_a_campaign_announces_its_cancellation(): void
    {
        $campaign = $this->campaign(CampaignStatus::OnHold);

        $campaign->update(['status' => CampaignStatus::Cancelled]);

        Event::assertDispatchedTimes(SurveySubjectCancelled::class, 1);
        Event::assertDispatched(SurveySubjectCancelled::class, fn (SurveySubjectCancelled $event): bool => $event->sourceType === 'campaign'
            && $event->subjectId === $campaign->id);
        Event::assertNotDispatched(SurveySubjectEnded::class);
    }

    public function test_other_status_changes_and_plain_saves_announce_nothing(): void
    {
        $campaign = $this->campaign(CampaignStatus::Recruiting);

        $campaign->update(['status' => CampaignStatus::Active]);
        $campaign->update(['status' => CampaignStatus::OnHold]);
        $campaign->update(['title' => 'Otro título']);

        Event::assertDispatchedTimes(CampaignStatusChanged::class, 2);
        Event::assertNotDispatched(SurveySubjectEnded::class);
        Event::assertNotDispatched(SurveySubjectCancelled::class);
    }

    public function test_deleting_an_unfinished_campaign_cancels_it_and_its_pending_sessions(): void
    {
        $campaign = $this->campaign(CampaignStatus::Active);
        $pending = $this->sessionOf($campaign, TableStatus::Scheduled);
        $draft = $this->sessionOf($campaign, TableStatus::Draft);
        $this->sessionOf($campaign, TableStatus::Completed);
        $this->sessionOf($campaign, TableStatus::Cancelled);

        $this->app->make(CampaignRepositoryInterface::class)->delete(new CampaignId($campaign->id));

        $this->assertNull(CampaignModel::query()->find($campaign->id));
        Event::assertDispatchedTimes(SurveySubjectCancelled::class, 3);
        Event::assertDispatched(SurveySubjectCancelled::class, fn (SurveySubjectCancelled $event): bool => $event->sourceType === 'campaign'
            && $event->subjectId === $campaign->id);
        Event::assertDispatched(SurveySubjectCancelled::class, fn (SurveySubjectCancelled $event): bool => $event->sourceType === 'campaign-session'
            && $event->subjectId === $pending->id);
        Event::assertDispatched(SurveySubjectCancelled::class, fn (SurveySubjectCancelled $event): bool => $event->sourceType === 'campaign-session'
            && $event->subjectId === $draft->id);
    }

    public function test_deleting_a_completed_campaign_cancels_only_pending_sessions(): void
    {
        $campaign = $this->campaign(CampaignStatus::Completed);
        $pending = $this->sessionOf($campaign, TableStatus::InProgress);
        $this->sessionOf($campaign, TableStatus::Completed);
        $this->campaign(CampaignStatus::Cancelled)->delete();

        $campaign->delete();

        Event::assertDispatchedTimes(SurveySubjectCancelled::class, 1);
        Event::assertDispatched(SurveySubjectCancelled::class, fn (SurveySubjectCancelled $event): bool => $event->sourceType === 'campaign-session'
            && $event->subjectId === $pending->id);
    }

    public function test_nothing_is_announced_when_the_transaction_rolls_back(): void
    {
        $completed = $this->campaign(CampaignStatus::Active);
        $deleted = $this->campaign(CampaignStatus::Active);
        $this->sessionOf($deleted, TableStatus::Scheduled);

        try {
            DB::transaction(function () use ($completed, $deleted): void {
                $completed->update(['status' => CampaignStatus::Completed]);
                $deleted->delete();

                throw new RuntimeException('Rolled back');
            });
        } catch (RuntimeException) {
            // expected
        }

        Event::assertNotDispatched(SurveySubjectEnded::class);
        Event::assertNotDispatched(SurveySubjectCancelled::class);
        Event::assertNotDispatched(CampaignStatusChanged::class);
    }

    public function test_the_events_wait_for_the_commit(): void
    {
        $campaign = $this->campaign(CampaignStatus::Active);

        DB::transaction(function () use ($campaign): void {
            $campaign->update(['status' => CampaignStatus::Completed]);

            Event::assertNotDispatched(SurveySubjectEnded::class);
        });

        Event::assertDispatchedTimes(SurveySubjectEnded::class, 1);
    }

    private function campaign(CampaignStatus $status): CampaignModel
    {
        return CampaignModel::create([
            'id' => (string) Str::uuid(),
            'game_system_id' => $this->system->id,
            'created_by' => UserModel::factory()->create()->id,
            'title' => 'Campaña de ejemplo',
            'slug' => 'campana-'.Str::lower(Str::random(8)),
            'status' => $status,
        ]);
    }

    private function sessionOf(CampaignModel $campaign, TableStatus $status): GameTableModel
    {
        return GameTableModel::create([
            'id' => (string) Str::uuid(),
            'game_system_id' => $this->system->id,
            'campaign_id' => $campaign->id,
            'created_by' => UserModel::factory()->create()->id,
            'title' => 'Sesión de ejemplo',
            'slug' => 'sesion-'.Str::lower(Str::random(8)),
            'starts_at' => '2030-03-10 18:00:00',
            'duration_minutes' => 240,
            'table_type' => 'campaign_session',
            'table_format' => 'online',
            'status' => $status,
            'min_players' => 1,
            'max_players' => 5,
            'language' => 'es',
            'registration_type' => 'everyone',
            'is_published' => $status !== TableStatus::Draft,
        ]);
    }
}
