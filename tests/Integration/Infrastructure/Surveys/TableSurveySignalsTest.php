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
use Modules\GameTables\Application\Services\GameTableServiceInterface;
use Modules\GameTables\Domain\Enums\TableFormat;
use Modules\GameTables\Domain\Enums\TableStatus;
use Modules\GameTables\Domain\Enums\TableType;
use Modules\GameTables\Domain\Exceptions\InvalidStatusTransitionException;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\CampaignModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameSystemModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameTableModel;
use Modules\GameTables\Infrastructure\Services\GameTableStatusUpdater;
use Tests\Support\Modules\ModuleTestCase;

/**
 * What a table tells the surveys module (through the core's events) when it ends, is cancelled
 * or is deleted before it ended. Campaign sessions say nothing: they are another kind of subject.
 */
final class TableSurveySignalsTest extends ModuleTestCase
{
    protected ?string $moduleName = 'game-tables';

    protected bool $autoEnableModule = true;

    private GameTableServiceInterface $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->useUtcStorage();
        Event::fake([SurveySubjectEnded::class, SurveySubjectCancelled::class]);
        $this->service = $this->app->make(GameTableServiceInterface::class);
    }

    public function test_completing_after_the_slot_announces_the_end_of_the_slot(): void
    {
        $table = $this->table(TableStatus::InProgress);
        $this->travelTo(new DateTimeImmutable('2030-03-10T23:30:00+00:00'));

        $this->service->complete($table->id);

        Event::assertDispatchedTimes(SurveySubjectEnded::class, 1);
        Event::assertDispatched(SurveySubjectEnded::class, fn (SurveySubjectEnded $event): bool => $event->sourceType === 'game-table'
            && $event->subjectId === $table->id
            && $event->endedAt == new DateTimeImmutable('2030-03-10T22:00:00+00:00'));
    }

    public function test_completing_before_the_slot_ends_announces_now(): void
    {
        $table = $this->table(TableStatus::InProgress);
        $this->travelTo(new DateTimeImmutable('2030-03-10T20:00:00+00:00'));

        $this->service->complete($table->id);

        Event::assertDispatched(SurveySubjectEnded::class, fn (SurveySubjectEnded $event): bool => $event->endedAt == new DateTimeImmutable('2030-03-10T20:00:00+00:00'));
    }

    public function test_the_automatic_update_announces_the_end_once(): void
    {
        $table = $this->table(TableStatus::InProgress);
        $now = new DateTimeImmutable('2030-03-10T22:05:00+00:00');
        $this->travelTo($now);

        $this->app->make(GameTableStatusUpdater::class)->update($now);
        $this->app->make(GameTableStatusUpdater::class)->update($now);

        Event::assertDispatchedTimes(SurveySubjectEnded::class, 1);
        Event::assertDispatched(SurveySubjectEnded::class, fn (SurveySubjectEnded $event): bool => $event->subjectId === $table->id);
    }

    public function test_the_end_is_announced_after_the_commit(): void
    {
        $table = $this->table(TableStatus::InProgress);

        DB::transaction(function () use ($table): void {
            $this->service->complete($table->id);

            Event::assertNotDispatched(SurveySubjectEnded::class);
        });

        Event::assertDispatchedTimes(SurveySubjectEnded::class, 1);
    }

    public function test_a_refused_completion_announces_nothing(): void
    {
        $table = $this->table(TableStatus::Cancelled);

        try {
            $this->service->complete($table->id);
            $this->fail('A cancelled table was completed');
        } catch (InvalidStatusTransitionException) {
            // expected
        }

        Event::assertNotDispatched(SurveySubjectEnded::class);
    }

    public function test_campaign_sessions_announce_nothing_to_surveys(): void
    {
        $session = $this->table(TableStatus::InProgress, campaign: true);
        $other = $this->table(TableStatus::Scheduled, campaign: true);

        $this->service->complete($session->id);
        $this->service->cancel($other->id);
        $this->service->delete($this->table(TableStatus::Scheduled, campaign: true)->id);

        Event::assertNotDispatched(SurveySubjectEnded::class);
        Event::assertNotDispatched(SurveySubjectCancelled::class);
    }

    public function test_cancelling_announces_the_subject_will_never_end(): void
    {
        $table = $this->table(TableStatus::Scheduled);

        $this->service->cancel($table->id);

        Event::assertDispatchedTimes(SurveySubjectCancelled::class, 1);
        Event::assertDispatched(SurveySubjectCancelled::class, fn (SurveySubjectCancelled $event): bool => $event->sourceType === 'game-table'
            && $event->subjectId === $table->id);
    }

    public function test_deleting_an_unfinished_table_announces_the_cancellation(): void
    {
        $byService = $this->table(TableStatus::Scheduled);
        $byPanel = $this->table(TableStatus::Draft);

        $this->service->delete($byService->id);
        $byPanel->delete();

        Event::assertDispatchedTimes(SurveySubjectCancelled::class, 2);
        Event::assertDispatched(SurveySubjectCancelled::class, fn (SurveySubjectCancelled $event): bool => $event->subjectId === $byService->id);
        Event::assertDispatched(SurveySubjectCancelled::class, fn (SurveySubjectCancelled $event): bool => $event->subjectId === $byPanel->id);
    }

    public function test_deleting_a_completed_or_cancelled_table_announces_nothing(): void
    {
        $this->service->delete($this->table(TableStatus::Completed)->id);
        $this->table(TableStatus::Cancelled)->delete();

        Event::assertNotDispatched(SurveySubjectCancelled::class);
    }

    private function table(TableStatus $status, bool $campaign = false): GameTableModel
    {
        $creator = UserModel::factory()->create();
        $system = GameSystemModel::create([
            'id' => (string) Str::uuid(),
            'name' => 'Sistema de ejemplo',
            'slug' => 'sistema-'.bin2hex(random_bytes(4)),
            'is_active' => true,
        ]);
        $campaignId = $campaign ? CampaignModel::create([
            'id' => (string) Str::uuid(),
            'game_system_id' => $system->id,
            'created_by' => $creator->id,
            'title' => 'Campaña de ejemplo',
            'slug' => Str::random(10),
            'status' => 'recruiting',
        ])->id : null;

        return GameTableModel::create([
            'id' => (string) Str::uuid(),
            'game_system_id' => $system->id,
            'campaign_id' => $campaignId,
            'created_by' => $creator->id,
            'title' => 'Mesa de ejemplo',
            'slug' => 'mesa-'.bin2hex(random_bytes(4)),
            'starts_at' => '2030-03-10 18:00:00',
            'duration_minutes' => 240,
            'table_type' => TableType::OneShot,
            'table_format' => TableFormat::InPerson,
            'status' => $status,
            'min_players' => 2,
            'max_players' => 6,
            'language' => 'es',
            'auto_confirm' => true,
            'is_published' => $status !== TableStatus::Draft,
        ]);
    }
}
