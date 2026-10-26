<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Feature\Http\Controllers;

use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Carbon\CarbonImmutable;
use DateTimeImmutable;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\GameTables\Domain\Entities\EventGameTableConfig;
use Modules\GameTables\Domain\Enums\LocationMode;
use Modules\GameTables\Domain\Enums\SchedulingMode;
use Modules\GameTables\Domain\Repositories\EventGameTableConfigRepositoryInterface;
use Modules\GameTables\Domain\ValueObjects\TimeSlotDefinition;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameSystemModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameTableModel;
use Tests\Support\Modules\ModuleTestCase;

/**
 * On an event scheduled by time slots the web form offers only its slots, with the tables each one
 * still takes: the server applies the same rule, so a forged start never lands outside the slots
 * nor in a slot that has reached its max_tables. Every table takes its place but a cancelled one
 * and a rejected one, until it is sent again.
 */
final class FrontendTableTimeSlotTest extends ModuleTestCase
{
    private const string NOT_A_SLOT = 'Elige uno de los horarios del evento';

    private const string FULL = 'El horario «Tarde» ya no admite más mesas';

    // 18:00 to 22:00 in Madrid, two tables at most
    private const string EVENING = '2030-07-01T18:00:00+02:00';

    // 10:00 to 14:00 in Madrid, no limit
    private const string MORNING = '2030-07-01T10:00:00+02:00';

    protected ?string $moduleName = 'game-tables';

    protected bool $autoEnableModule = true;

    private UserModel $author;

    private GameSystemModel $gameSystem;

    protected function setUp(): void
    {
        parent::setUp();

        app()->setLocale('es');
        app('router')->getRoutes()->refreshNameLookups();
        config()->set('modules.settings.game-tables', [
            'frontend_creation' => [
                'enabled' => true,
                'access_level' => 'registered',
                'allowed_content' => 'both',
            ],
        ]);

        $this->author = UserModel::factory()->create();
        $this->gameSystem = GameSystemModel::create([
            'id' => (string) Str::uuid(),
            'name' => 'Sistema de prueba',
            'slug' => 'sistema-de-prueba',
            'is_active' => true,
        ]);
    }

    public function test_a_start_that_is_not_one_of_the_event_slots_is_refused(): void
    {
        $event = $this->slotEvent();

        foreach (['2030-07-01T19:00:00+02:00', '2030-07-02T18:00:00+02:00', '2030-07-01T18:00:01+02:00'] as $start) {
            $this->actingAs($this->author)
                ->post(route('gametables.store'), $this->tableData('Mesa fuera de horario', $event->id, $start))
                ->assertSessionHasErrors(['starts_at' => self::NOT_A_SLOT]);
        }

        $this->assertFalse(GameTableModel::query()->where('title', 'Mesa fuera de horario')->exists());
    }

    /**
     * The form sends the slot with its offset, but the same instant written in another zone, or
     * as the site's wall time, is the same slot.
     */
    public function test_a_slot_start_is_accepted_whatever_zone_it_is_written_in(): void
    {
        $event = $this->slotEvent();

        foreach ([self::MORNING, '2030-07-01T08:00:00Z', '2030-07-01T10:00'] as $index => $start) {
            $this->actingAs($this->author)
                ->post(route('gametables.store'), $this->tableData('Mesa de mañana '.$index, $event->id, $start))
                ->assertSessionHasNoErrors()
                ->assertRedirect(route('gametables.my-tables'));
        }

        $this->assertSame(3, GameTableModel::query()->where('event_id', $event->id)->count());
    }

    public function test_a_full_slot_is_refused_and_a_cancelled_table_leaves_its_place(): void
    {
        $event = $this->slotEvent();
        $this->tableAt($event->id, self::EVENING, 'Mesa ya publicada', status: 'scheduled');
        $this->tableAt($event->id, self::EVENING, 'Mesa cancelada', status: 'cancelled');
        // Same time, another event: it takes no place in this one
        $this->tableAt($this->slotEvent()->id, self::EVENING, 'Mesa de otro evento');

        $this->actingAs($this->author)
            ->post(route('gametables.store'), $this->tableData('Segunda mesa de tarde', $event->id, self::EVENING))
            ->assertSessionHasNoErrors();

        // A draft takes its place too
        $this->actingAs($this->author)
            ->post(route('gametables.store'), $this->tableData('Tercera mesa de tarde', $event->id, '2030-07-01T16:00:00Z'))
            ->assertSessionHasErrors(['starts_at' => self::FULL]);

        $this->assertFalse(GameTableModel::query()->where('title', 'Tercera mesa de tarde')->exists());
    }

    /**
     * A rejected table goes back to draft with the reason and gives up its place until it is sent
     * again: sending it checks the slot once more, and a slot filled meanwhile keeps it a draft.
     */
    public function test_a_rejected_table_leaves_its_place_until_it_is_sent_again(): void
    {
        $event = $this->slotEvent();
        $this->tableAt($event->id, self::EVENING, 'Mesa ya publicada', status: 'scheduled');
        $rejected = $this->tableAt($event->id, self::EVENING, 'Mesa rechazada', moderationNotes: 'Falta la sinopsis');
        $rejectedWhileRoom = $this->tableAt($event->id, self::MORNING, 'Mesa rechazada de mañana', moderationNotes: 'Falta la sinopsis');

        $this->actingAs($this->author)
            ->post(route('gametables.store'), $this->tableData('Mesa que ocupa el sitio', $event->id, self::EVENING))
            ->assertSessionHasNoErrors();

        $this->actingAs($this->author)
            ->post(route('gametables.submit-review', $rejected->id))
            ->assertSessionHas('error', self::FULL);
        $rejected->refresh();
        $this->assertSame('draft', $rejected->frontend_creation_status?->value);
        $this->assertSame('Falta la sinopsis', $rejected->moderation_notes);

        $this->actingAs($this->author)
            ->post(route('gametables.submit-review', $rejectedWhileRoom->id))
            ->assertSessionMissing('error');
        $this->assertSame('pending_review', $rejectedWhileRoom->refresh()->frontend_creation_status?->value);
    }

    public function test_editing_a_table_in_a_full_slot_keeps_it_there_but_no_other_moves_in(): void
    {
        $event = $this->slotEvent();
        $this->tableAt($event->id, self::EVENING, 'Mesa de otra persona', author: UserModel::factory()->create());
        $evening = $this->tableAt($event->id, self::EVENING, 'Mesa de tarde');
        $morning = $this->tableAt($event->id, self::MORNING, 'Mesa de mañana');

        $this->actingAs($this->author)
            ->put(route('gametables.update', $evening), $this->tableData('Mesa de tarde corregida', $event->id, self::EVENING))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('gametables.my-tables'));
        $this->assertSame('Mesa de tarde corregida', $evening->refresh()->title);

        $this->actingAs($this->author)
            ->put(route('gametables.update', $morning), $this->tableData('Mesa movida a la tarde', $event->id, self::EVENING))
            ->assertSessionHasErrors(['starts_at' => self::FULL]);
        $this->assertSame('Mesa de mañana', $morning->refresh()->title);
    }

    /**
     * An admin may change the slots, or lower their limit, after a table was created: the author
     * can still edit the table while keeping its event and start, but cannot move it to another
     * start the event does not offer.
     */
    public function test_editing_keeps_a_start_the_event_no_longer_offers_but_refuses_another(): void
    {
        $event = $this->slotEvent(eveningMax: 1);
        $this->tableAt($event->id, self::EVENING, 'Mesa de otra persona', author: UserModel::factory()->create());
        $overbooked = $this->tableAt($event->id, self::EVENING, 'Mesa sobrante');
        $moved = $this->tableAt($event->id, '2030-07-01T20:00:00+02:00', 'Mesa de un horario borrado');

        $this->actingAs($this->author)
            ->put(route('gametables.update', $overbooked), $this->tableData('Mesa sobrante corregida', $event->id, '2030-07-01T16:00:00Z'))
            ->assertSessionHasNoErrors();
        $this->actingAs($this->author)
            ->put(route('gametables.update', $moved), $this->tableData('Mesa de un horario borrado corregida', $event->id, '2030-07-01T20:00'))
            ->assertSessionHasNoErrors();

        $this->actingAs($this->author)
            ->put(route('gametables.update', $moved), $this->tableData('Mesa movida', $event->id, '2030-07-01T21:00:00+02:00'))
            ->assertSessionHasErrors(['starts_at' => self::NOT_A_SLOT]);
        $this->assertSame('Mesa de un horario borrado corregida', $moved->refresh()->title);
    }

    public function test_an_event_with_free_schedule_takes_any_start(): void
    {
        $event = $this->slotEvent(mode: SchedulingMode::FreeSchedule);

        $this->actingAs($this->author)
            ->post(route('gametables.store'), $this->tableData('Mesa a cualquier hora', $event->id, '2030-07-01T19:17:00+02:00'))
            ->assertSessionHasNoErrors();

        $this->assertTrue(GameTableModel::query()->where('title', 'Mesa a cualquier hora')->exists());
    }

    /**
     * The form learns how many tables each slot still takes (null without a limit); on edit, the
     * table's own place counts as free.
     */
    public function test_the_form_shows_the_tables_each_slot_still_takes(): void
    {
        $event = $this->slotEvent();
        $this->tableAt($event->id, self::EVENING, 'Mesa cancelada', status: 'cancelled');
        $this->tableAt($event->id, self::EVENING, 'Mesa rechazada', moderationNotes: 'Falta la sinopsis');
        $evening = $this->tableAt($event->id, self::EVENING, 'Mesa de tarde');

        $this->actingAs($this->author)
            ->get(route('gametables.create', ['event' => $event->slug]))
            ->assertInertia(fn (Assert $page) => $page
                ->component('GameTables/Create', false)
                ->where('eventContext.available_time_slots.0.label', 'Tarde')
                ->where('eventContext.available_time_slots.0.available_slots', 1)
                ->where('eventContext.available_time_slots.1.label', 'Mañana')
                ->where('eventContext.available_time_slots.1.available_slots', null));

        $this->actingAs($this->author)
            ->get(route('gametables.edit', $evening->slug))
            ->assertInertia(fn (Assert $page) => $page
                ->component('GameTables/Edit', false)
                ->where('eventContext.available_time_slots.0.available_slots', 2));
    }

    public function test_the_slot_choices_and_refusals_are_translated_in_both_languages(): void
    {
        $this->assertSame(self::NOT_A_SLOT, trans('game-tables::messages.validation.starts_at_not_a_slot', [], 'es'));
        $this->assertSame('Choose one of the event time slots', trans('game-tables::messages.validation.starts_at_not_a_slot', [], 'en'));
        $this->assertSame(self::FULL, trans('game-tables::messages.validation.time_slot_full', ['slot' => 'Tarde'], 'es'));
        $this->assertSame('The "Evening" time slot takes no more tables', trans('game-tables::messages.validation.time_slot_full', ['slot' => 'Evening'], 'en'));

        $es = (string) file_get_contents(__DIR__.'/../../../../resources/js/locales/es.ts');
        $en = (string) file_get_contents(__DIR__.'/../../../../resources/js/locales/en.ts');
        $this->assertStringContainsString("timeSlotTablesLeft: '{label} (queda {count} mesa) | {label} (quedan {count} mesas)'", $es);
        $this->assertStringContainsString("timeSlotFull: '{label} (completo)'", $es);
        $this->assertStringContainsString("timeSlotTablesLeft: '{label} ({count} table left) | {label} ({count} tables left)'", $en);
        $this->assertStringContainsString("timeSlotFull: '{label} (full)'", $en);

        foreach (['Create.vue', 'Edit.vue'] as $page) {
            $source = (string) file_get_contents(__DIR__.'/../../../../resources/js/pages/GameTables/'.$page);
            $this->assertStringContainsString('timeSlotLabel(slot)', $source, $page);
        }
    }

    private function slotEvent(SchedulingMode $mode = SchedulingMode::SlotBased, int $eveningMax = 2): EventModel
    {
        $event = EventModel::factory()->published()->create([
            'start_date' => CarbonImmutable::parse('2030-07-01T08:00:00Z'),
            'end_date' => CarbonImmutable::parse('2030-07-01T20:00:00Z'),
        ]);

        app(EventGameTableConfigRepositoryInterface::class)->save(new EventGameTableConfig(
            eventId: (string) $event->id,
            tablesEnabled: true,
            schedulingMode: $mode,
            timeSlots: [
                TimeSlotDefinition::create('Tarde', new DateTimeImmutable(self::EVENING), new DateTimeImmutable('2030-07-01T22:00:00+02:00'), $eveningMax),
                TimeSlotDefinition::create('Mañana', new DateTimeImmutable(self::MORNING), new DateTimeImmutable('2030-07-01T14:00:00+02:00')),
            ],
            locationMode: LocationMode::FreeChoice,
        ));

        return $event;
    }

    private function tableAt(
        string $eventId,
        string $start,
        string $title,
        string $status = 'draft',
        ?UserModel $author = null,
        ?string $moderationNotes = null,
    ): GameTableModel {
        return GameTableModel::create([
            'id' => (string) Str::uuid(),
            'game_system_id' => $this->gameSystem->id,
            'event_id' => $eventId,
            'created_by' => ($author ?? $this->author)->id,
            'title' => $title,
            'slug' => Str::slug($title).'-'.Str::lower(Str::random(6)),
            'starts_at' => CarbonImmutable::parse($start),
            'duration_minutes' => 240,
            'table_type' => 'one_shot',
            'table_format' => 'in_person',
            'status' => $status,
            'min_players' => 1,
            'max_players' => 5,
            'language' => 'es',
            'registration_type' => 'everyone',
            'auto_confirm' => true,
            'is_published' => $status !== 'draft',
            'frontend_creation_status' => $status === 'draft' ? 'draft' : 'approved',
            // A rejection sends the table back to draft with its reason
            'moderation_notes' => $moderationNotes,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function tableData(string $title, string $eventId, string $startsAt): array
    {
        return [
            'game_system_id' => $this->gameSystem->id,
            'event_id' => $eventId,
            'title' => $title,
            'starts_at' => $startsAt,
            'duration_minutes' => 240,
            'table_type' => 'one_shot',
            'table_format' => 'in_person',
            'min_players' => 2,
            'max_players' => 5,
            'language' => 'es',
            'experience_level' => 'none',
            'character_creation' => 'pre_generated',
        ];
    }
}
