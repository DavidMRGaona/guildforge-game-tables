<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Feature\Infrastructure\Services;

use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Carbon\CarbonImmutable;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Support\Str;
use Modules\GameTables\Application\DTOs\FrontendCreateGameTableDTO;
use Modules\GameTables\Application\Services\FrontendCreationServiceInterface;
use Modules\GameTables\Domain\Entities\EventGameTableConfig;
use Modules\GameTables\Domain\Enums\CharacterCreation;
use Modules\GameTables\Domain\Enums\ExperienceLevel;
use Modules\GameTables\Domain\Enums\LocationMode;
use Modules\GameTables\Domain\Enums\SchedulingMode;
use Modules\GameTables\Domain\Enums\TableFormat;
use Modules\GameTables\Domain\Enums\TableType;
use Modules\GameTables\Domain\Repositories\EventGameTableConfigRepositoryInterface;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameSystemModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameTableModel;
use Tests\Support\Modules\ModuleTestCase;
use Tests\Support\Time\UsesUtcStorage;

/**
 * Tables created from the public form take the panel defaults.
 */
final class FrontendCreationSettingsTest extends ModuleTestCase
{
    use UsesUtcStorage;

    protected ?string $moduleName = 'game-tables';

    protected bool $autoEnableModule = true;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('modules.settings.game-tables', [
            'frontend_creation' => ['enabled' => true, 'allowed_content' => 'both', 'access_level' => 'registered'],
        ]);
    }

    public function test_the_form_data_carries_the_defaults_and_limits_of_the_settings(): void
    {
        config()->set('modules.settings.game-tables.defaults', ['duration_minutes' => 180, 'min_players' => 2, 'max_players' => 6, 'max_spectators' => 1]);
        config()->set('modules.settings.game-tables.limits', ['max_players_limit' => 8, 'max_spectators_limit' => 2, 'max_duration_minutes' => 300]);

        $formData = app(FrontendCreationServiceInterface::class)->getCreateFormData();

        $this->assertSame(['duration_minutes' => 180, 'min_players' => 2, 'max_players' => 6, 'max_spectators' => 1], $formData['defaults']);
        $this->assertSame(['max_players' => 8, 'max_spectators' => 2, 'max_duration_minutes' => 300], $formData['limits']);
    }

    public function test_a_table_created_from_the_web_takes_the_registration_defaults(): void
    {
        config()->set('modules.settings.game-tables.defaults', [
            'auto_confirm' => false,
            'registration_type' => 'members_only',
            'members_early_access_days' => 2,
        ]);

        $table = $this->createFromTheWeb();

        $this->assertFalse($table->auto_confirm);
        $this->assertSame('members_only', $table->registration_type->value);
        $this->assertSame(2, $table->members_early_access_days);
    }

    public function test_without_panel_values_a_web_table_keeps_the_previous_registration_values(): void
    {
        $table = $this->createFromTheWeb();

        $this->assertTrue($table->auto_confirm);
        $this->assertSame('everyone', $table->registration_type->value);
        $this->assertSame(0, $table->members_early_access_days);
    }

    public function test_the_default_closing_date_of_an_event_table_is_capped_to_the_table_start(): void
    {
        $table = $this->createForAnEvent(eventStart: '2030-02-10 18:00:00', tableStart: '2030-02-09 18:00:00');

        $this->assertSame('2030-02-09 18:00:00', $this->closingDateOf($table));
    }

    public function test_the_default_closing_date_stays_the_event_start_when_the_table_starts_later(): void
    {
        $table = $this->createForAnEvent(eventStart: '2030-02-10 18:00:00', tableStart: '2030-02-10 20:00:00');

        $this->assertSame('2030-02-10 18:00:00', $this->closingDateOf($table));
    }

    public function test_a_table_on_a_later_day_of_the_event_closes_at_its_own_start(): void
    {
        $table = $this->createForAnEvent(eventStart: '2030-02-10 18:00:00', tableStart: '2030-02-11 10:00:00', eventEnd: '2030-02-12 20:00:00');

        $this->assertSame('2030-02-11 10:00:00', $this->closingDateOf($table));
    }

    public function test_a_table_on_the_first_day_of_a_multi_day_event_closes_at_the_event_start(): void
    {
        $table = $this->createForAnEvent(eventStart: '2030-02-10 10:00:00', tableStart: '2030-02-10 21:00:00', eventEnd: '2030-02-12 20:00:00');

        $this->assertSame('2030-02-10 10:00:00', $this->closingDateOf($table));
    }

    public function test_the_first_day_of_the_event_is_a_day_of_the_site_calendar(): void
    {
        $this->useUtcStorage();

        // 00:30 on the 11th of July in Madrid, still the 10th in UTC: the table is on the first day
        $table = $this->createForAnEvent(eventStart: '2030-07-10T22:30:00Z', tableStart: '2030-07-11T08:00:00Z', eventEnd: '2030-07-12T20:00:00Z');
        $this->assertSame('2030-07-10T22:30:00+00:00', $this->closingInstantOf($table));

        // 00:30 on the 11th in Madrid is the second day of an event that starts at 22:00 on the 10th
        $table = $this->createForAnEvent(eventStart: '2030-07-10T20:00:00Z', tableStart: '2030-07-10T22:30:00Z', eventEnd: '2030-07-12T20:00:00Z');
        $this->assertSame('2030-07-10T22:30:00+00:00', $this->closingInstantOf($table));
    }

    public function test_a_draft_given_a_later_day_of_the_event_closes_at_its_own_start(): void
    {
        $event = $this->eventWithTables('2030-02-10 18:00:00', '2030-02-12 20:00:00');
        $table = $this->createFromTheWeb(new DateTimeImmutable('2030-02-01 18:00:00'));
        $this->assertNull($table->registration_closes_at);

        $this->updateDraftStart($table, new DateTimeImmutable('2030-02-11 10:00:00'), $event->id);

        $this->assertSame('2030-02-11 10:00:00', $this->closingDateOf($table->refresh()));
    }

    public function test_a_draft_given_the_first_day_of_the_event_closes_at_the_event_start(): void
    {
        $event = $this->eventWithTables('2030-02-10 18:00:00', '2030-02-12 20:00:00');
        $table = $this->createFromTheWeb(new DateTimeImmutable('2030-02-01 18:00:00'));

        $this->updateDraftStart($table, new DateTimeImmutable('2030-02-10 20:00:00'), $event->id);

        $this->assertSame('2030-02-10 18:00:00', $this->closingDateOf($table->refresh()));
    }

    public function test_moving_a_draft_earlier_caps_its_kept_closing_date_to_the_new_start(): void
    {
        $table = $this->createForAnEvent(eventStart: '2030-02-10 18:00:00', tableStart: '2030-02-10 20:00:00');
        $this->assertSame('2030-02-10 18:00:00', $this->closingDateOf($table));

        $this->updateDraftStart($table, new DateTimeImmutable('2030-02-10 12:00:00'));

        $this->assertSame('2030-02-10 12:00:00', $this->closingDateOf($table->refresh()));
    }

    public function test_moving_a_draft_later_keeps_its_closing_date(): void
    {
        $table = $this->createForAnEvent(eventStart: '2030-02-10 18:00:00', tableStart: '2030-02-10 20:00:00');

        $this->updateDraftStart($table, new DateTimeImmutable('2030-02-11 20:00:00'));

        $this->assertSame('2030-02-10 18:00:00', $this->closingDateOf($table->refresh()));
    }

    private function closingDateOf(GameTableModel $table): string
    {
        $this->assertNotNull($table->registration_closes_at);

        return $table->registration_closes_at->format('Y-m-d H:i:s');
    }

    private function closingInstantOf(GameTableModel $table): string
    {
        $this->assertNotNull($table->registration_closes_at);

        return CarbonImmutable::instance($table->registration_closes_at)->setTimezone(new DateTimeZone('UTC'))->format('c');
    }

    private function updateDraftStart(GameTableModel $table, DateTimeImmutable $startsAt, ?string $eventId = null): void
    {
        app(FrontendCreationServiceInterface::class)->updateDraft($table->id, new FrontendCreateGameTableDTO(
            createdBy: (string) $table->created_by,
            gameSystemId: $table->game_system_id,
            title: $table->title,
            startsAt: $startsAt,
            durationMinutes: 240,
            tableType: TableType::OneShot,
            tableFormat: TableFormat::InPerson,
            minPlayers: 3,
            maxPlayers: 5,
            eventId: $eventId ?? $table->event_id,
            experienceLevel: ExperienceLevel::cases()[0],
            characterCreation: CharacterCreation::cases()[0],
        ));
    }

    private function createForAnEvent(string $eventStart, string $tableStart, ?string $eventEnd = null): GameTableModel
    {
        $event = $this->eventWithTables($eventStart, $eventEnd);

        return $this->createFromTheWeb(new DateTimeImmutable($tableStart), $event->id);
    }

    private function eventWithTables(string $start, ?string $end = null): EventModel
    {
        $event = EventModel::factory()->create([
            'start_date' => CarbonImmutable::parse($start),
            'end_date' => CarbonImmutable::parse($end ?? $start)->addHours($end === null ? 6 : 0),
        ]);
        app(EventGameTableConfigRepositoryInterface::class)->save(new EventGameTableConfig(
            eventId: $event->id,
            tablesEnabled: true,
            schedulingMode: SchedulingMode::FreeSchedule,
            timeSlots: [],
            locationMode: LocationMode::FreeChoice,
        ));

        return $event;
    }

    private function createFromTheWeb(?DateTimeImmutable $startsAt = null, ?string $eventId = null): GameTableModel
    {
        $user = UserModel::factory()->create();
        $system = GameSystemModel::query()->firstOrCreate(['slug' => 'sistema-de-prueba'], [
            'id' => (string) Str::uuid(),
            'name' => 'Sistema de prueba',
            'is_active' => true,
        ]);

        $created = app(FrontendCreationServiceInterface::class)->createGameTable(new FrontendCreateGameTableDTO(
            createdBy: (string) $user->id,
            gameSystemId: $system->id,
            title: 'Mesa desde la web',
            startsAt: $startsAt ?? new DateTimeImmutable('+1 week'),
            durationMinutes: 240,
            tableType: TableType::OneShot,
            tableFormat: TableFormat::InPerson,
            minPlayers: 3,
            maxPlayers: 5,
            eventId: $eventId,
            experienceLevel: ExperienceLevel::cases()[0],
            characterCreation: CharacterCreation::cases()[0],
        ));

        return GameTableModel::query()->findOrFail($created->id);
    }
}
