<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Feature\Filament;

use App\Filament\Resources\EventResource\Pages\EditEvent;
use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use Carbon\CarbonImmutable;
use DateTimeImmutable;
use DateTimeZone;
use Filament\Forms\Components\Select;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Modules\GameTables\Domain\Enums\SchedulingMode;
use Modules\GameTables\Domain\Repositories\EventGameTableConfigRepositoryInterface;
use Modules\GameTables\Filament\RelationManagers\EventGameTableConfigRelationManager;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\EventGameTableConfigModel;
use Tests\Support\Modules\InteractsWithModulePanel;
use Tests\Support\Modules\ModuleTestCase;
use Tests\Support\Time\UsesUtcStorage;

/**
 * The slots of a multi-day event are dated on the day the admin picks, in the site's calendar.
 */
final class EventTimeSlotDaysTest extends ModuleTestCase
{
    use InteractsWithModulePanel;
    use UsesUtcStorage;

    protected ?string $moduleName = 'game-tables';

    protected bool $autoEnableModule = true;

    protected function setUp(): void
    {
        parent::setUp();

        $this->useUtcStorage();
        app()->setLocale('es');
        $this->actingAsPanelAdmin();
    }

    public function test_a_multi_day_event_offers_each_of_its_days_in_the_site_calendar(): void
    {
        // From Saturday 10:00 to Monday 00:30 in Madrid (still Sunday in UTC)
        $event = $this->event('2026-11-14T09:00:00Z', '2026-11-15T23:30:00Z');

        $this->configTab($event)
            ->set('configData.time_slots', ['slot' => $this->slot()])
            ->assertFormFieldVisible('time_slots.slot.day', 'configForm')
            ->assertFormFieldExists('time_slots.slot.day', 'configForm', fn (Select $field): bool => $field->getOptions() === [
                '2026-11-14' => 'Sábado 14 de noviembre de 2026',
                '2026-11-15' => 'Domingo 15 de noviembre de 2026',
                '2026-11-16' => 'Lunes 16 de noviembre de 2026',
            ]);
    }

    public function test_a_slot_is_saved_on_the_day_chosen_for_it(): void
    {
        $event = $this->event('2026-11-14T09:00:00Z', '2026-11-15T19:00:00Z');

        $this->configTab($event)
            ->set('configData.time_slots', [
                'saturday' => $this->slot(['label' => 'Sábado tarde', 'day' => '2026-11-14']),
                'sunday' => $this->slot(['label' => 'Domingo tarde', 'day' => '2026-11-15']),
            ])
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame([
            ['2026-11-14T17:00:00+00:00', '2026-11-14T21:00:00+00:00'],
            ['2026-11-15T17:00:00+00:00', '2026-11-15T21:00:00+00:00'],
        ], $this->storedSlots($event));
    }

    public function test_a_slot_without_a_day_takes_the_first_day_of_the_event(): void
    {
        $event = $this->event('2026-11-14T09:00:00Z', '2026-11-15T19:00:00Z');

        $this->configTab($event)
            ->set('configData.time_slots', ['slot' => $this->slot()])
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame([['2026-11-14T17:00:00+00:00', '2026-11-14T21:00:00+00:00']], $this->storedSlots($event));
    }

    public function test_a_slot_that_ends_at_or_before_its_start_hour_ends_the_next_day(): void
    {
        $event = $this->event('2026-11-14T09:00:00Z', '2026-11-15T19:00:00Z');

        $this->configTab($event)
            ->set('configData.time_slots', ['slot' => $this->slot(['label' => 'Noche', 'day' => '2026-11-14', 'start_time' => '22:00', 'end_time' => '02:00'])])
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame([['2026-11-14T21:00:00+00:00', '2026-11-15T01:00:00+00:00']], $this->storedSlots($event));

        // The tab shows the same hours and day again, and a table starting then is in the slot
        $this->configTab($event, fill: false)
            ->assertSet('configData.time_slots', fn (array $slots): bool => array_first($slots)['day'] === '2026-11-14'
                && array_first($slots)['start_time'] === '22:00'
                && array_first($slots)['end_time'] === '02:00');

        $config = app(EventGameTableConfigRepositoryInterface::class)->findByEventOrDefault($event->id);
        $this->assertNotNull($config->slotStartingAt(new DateTimeImmutable('2026-11-14T21:00:00Z')));
    }

    public function test_a_single_day_slot_crossing_midnight_also_ends_the_next_day(): void
    {
        $event = $this->event('2026-11-14T17:00:00Z', '2026-11-14T22:00:00Z');

        $this->configTab($event)
            ->set('configData.time_slots', ['slot' => $this->slot(['label' => 'Noche', 'start_time' => '22:00', 'end_time' => '01:00'])])
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame([['2026-11-14T21:00:00+00:00', '2026-11-15T00:00:00+00:00']], $this->storedSlots($event));
    }

    public function test_a_day_outside_the_event_is_refused(): void
    {
        $event = $this->event('2026-11-14T09:00:00Z', '2026-11-15T19:00:00Z');

        $this->configTab($event)
            ->set('configData.time_slots', ['slot' => $this->slot(['day' => '2026-11-20'])])
            ->call('save')
            ->assertHasErrors(['configData.time_slots.slot.day']);

        $this->assertNull(EventGameTableConfigModel::query()->find($event->id));
    }

    public function test_a_single_day_event_does_not_ask_for_the_day(): void
    {
        $event = $this->event('2026-11-14T09:00:00Z', '2026-11-14T19:00:00Z');

        $this->configTab($event)
            ->set('configData.time_slots', ['slot' => $this->slot(['day' => '2026-11-20'])])
            ->assertFormFieldHidden('time_slots.slot.day', 'configForm')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame([['2026-11-14T17:00:00+00:00', '2026-11-14T21:00:00+00:00']], $this->storedSlots($event));
    }

    public function test_the_tab_shows_the_stored_day_of_each_slot(): void
    {
        $event = $this->event('2026-11-14T09:00:00Z', '2026-11-15T19:00:00Z');
        $this->storeSlots($event, [['label' => 'Domingo', 'start_time' => '2026-11-15T09:00:00+00:00', 'end_time' => '2026-11-15T13:00:00+00:00', 'max_tables' => 3]]);

        Livewire::test(EventGameTableConfigRelationManager::class, ['ownerRecord' => $event, 'pageClass' => EditEvent::class])
            ->assertSet('configData.time_slots', fn (array $slots): bool => array_first($slots)['label'] === 'Domingo'
                && array_first($slots)['day'] === '2026-11-15'
                && array_first($slots)['start_time'] === '10:00'
                && array_first($slots)['end_time'] === '14:00');
    }

    public function test_a_slot_left_outside_a_moved_event_keeps_its_day(): void
    {
        // The event was moved a week later after its slots were saved
        $event = $this->event('2026-11-21T09:00:00Z', '2026-11-22T19:00:00Z');
        $this->storeSlots($event, [['label' => 'Domingo', 'start_time' => '2026-11-15T09:00:00+00:00', 'end_time' => '2026-11-15T13:00:00+00:00', 'max_tables' => null]]);

        $component = Livewire::test(EventGameTableConfigRelationManager::class, ['ownerRecord' => $event, 'pageClass' => EditEvent::class]);
        $key = array_key_first($component->get('configData.time_slots'));

        $component
            ->assertSet("configData.time_slots.{$key}.day", '2026-11-15')
            ->assertFormFieldExists("time_slots.{$key}.day", 'configForm', fn (Select $field): bool => array_keys($field->getOptions()) === ['2026-11-15', '2026-11-21', '2026-11-22'])
            ->set('configData.creation_opens_at', '2026-11-01 10:00:00')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame([['2026-11-15T09:00:00+00:00', '2026-11-15T13:00:00+00:00']], $this->storedSlots($event));
    }

    private function event(string $start, string $end): EventModel
    {
        return EventModel::factory()->create([
            'start_date' => CarbonImmutable::parse($start),
            'end_date' => CarbonImmutable::parse($end),
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $slots
     */
    private function storeSlots(EventModel $event, array $slots): void
    {
        EventGameTableConfigModel::create([
            'event_id' => $event->id,
            'tables_enabled' => true,
            'scheduling_mode' => SchedulingMode::SlotBased->value,
            'location_mode' => 'free',
            'time_slots' => $slots,
        ]);
    }

    /**
     * @return Testable<EventGameTableConfigRelationManager>
     */
    private function configTab(EventModel $event, bool $fill = true): Testable
    {
        $component = Livewire::test(EventGameTableConfigRelationManager::class, ['ownerRecord' => $event, 'pageClass' => EditEvent::class]);

        if (! $fill) {
            return $component;
        }

        return $component
            ->set('configData.tables_enabled', true)
            ->set('configData.scheduling_mode', SchedulingMode::SlotBased->value)
            ->set('configData.creation_opens_at', '2026-11-01 10:00:00');
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function slot(array $overrides = []): array
    {
        return [
            'label' => 'Tarde',
            'start_time' => '18:00',
            'end_time' => '22:00',
            'max_tables' => null,
            ...$overrides,
        ];
    }

    /**
     * @return list<array{0: string, 1: string}>
     */
    private function storedSlots(EventModel $event): array
    {
        $utc = new DateTimeZone('UTC');

        return array_map(
            static fn (array $slot): array => [
                (new DateTimeImmutable($slot['start_time']))->setTimezone($utc)->format('c'),
                (new DateTimeImmutable($slot['end_time']))->setTimezone($utc)->format('c'),
            ],
            array_values(EventGameTableConfigModel::query()->findOrFail($event->id)->time_slots ?? []),
        );
    }
}
