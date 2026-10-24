<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Feature\Http\Controllers;

use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\GameTables\Domain\Entities\EventGameTableConfig;
use Modules\GameTables\Domain\Enums\LocationMode;
use Modules\GameTables\Domain\Enums\SchedulingMode;
use Modules\GameTables\Domain\Repositories\EventGameTableConfigRepositoryInterface;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameSystemModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameTableModel;
use Tests\Support\Modules\ModuleTestCase;

/**
 * The web form offers only published events that have not ended and have tables enabled; the
 * server applies the same rule, so a forged event_id never links a table to any other event.
 */
final class FrontendTableEventLinkTest extends ModuleTestCase
{
    private const string REFUSED = 'Solo puedes vincular la mesa a un evento abierto a nuevas mesas';

    protected ?string $moduleName = 'game-tables';

    protected bool $autoEnableModule = true;

    private UserModel $author;

    private GameSystemModel $gameSystem;

    protected function setUp(): void
    {
        parent::setUp();

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

    public function test_creating_a_table_for_an_event_the_form_does_not_offer_is_refused(): void
    {
        foreach ($this->closedEvents() as $reason => $event) {
            $this->actingAs($this->author)
                ->post(route('gametables.store'), $this->tableData('Mesa intrusa '.$reason, $event->id))
                ->assertSessionHasErrors(['event_id' => self::REFUSED]);
        }

        $this->assertFalse(GameTableModel::query()->where('title', 'like', 'Mesa intrusa%')->exists());
    }

    public function test_creating_a_table_for_an_open_event_or_without_one_is_accepted(): void
    {
        $open = $this->openEvent();

        $this->actingAs($this->author)
            ->post(route('gametables.store'), $this->tableData('Mesa del evento', $open->id))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('gametables.my-tables'));
        $this->actingAs($this->author)
            ->post(route('gametables.store'), $this->tableData('Mesa suelta', null))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('gametables.my-tables'));

        $this->assertSame($open->id, GameTableModel::query()->where('title', 'Mesa del evento')->value('event_id'));
        $this->assertNull(GameTableModel::query()->where('title', 'Mesa suelta')->value('event_id'));
    }

    /**
     * From an event page the form arrives with that event already chosen (eventContext) and
     * posts its id: that flow keeps working.
     */
    public function test_a_table_created_from_an_event_page_is_linked_to_that_event(): void
    {
        $open = $this->openEvent();

        $this->actingAs($this->author)
            ->get(route('gametables.create', ['event' => $open->slug]))
            ->assertInertia(fn (Assert $page) => $page
                ->component('GameTables/Create', false)
                ->where('eventContext.event_id', $open->id));

        $this->actingAs($this->author)
            ->post(route('gametables.store'), $this->tableData('Mesa desde el evento', $open->id))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('gametables.my-tables'));

        $this->assertSame($open->id, GameTableModel::query()->where('title', 'Mesa desde el evento')->value('event_id'));
    }

    /**
     * Opening the create form for a closed event explains why before the form, instead of letting
     * the author fill it in to be refused on sending it.
     */
    public function test_the_create_page_of_a_closed_event_says_why_before_the_form(): void
    {
        $closed = $this->closedEvents();
        $expected = [
            'unpublished' => 'event_not_open',
            'ended' => 'event_not_open',
            'disabled' => 'tables_not_enabled_for_event',
            'not_enabled' => 'tables_not_enabled_for_event',
        ];

        foreach ($expected as $key => $reason) {
            $this->actingAs($this->author)
                ->get(route('gametables.create', ['event' => $closed[$key]->slug]))
                ->assertInertia(fn (Assert $page) => $page
                    ->component('GameTables/CreateNotEligible', false)
                    ->where('reason', $reason));
        }
    }

    public function test_the_closed_event_reason_is_translated_in_both_languages(): void
    {
        $es = (string) file_get_contents(__DIR__.'/../../../../resources/js/locales/es.ts');
        $en = (string) file_get_contents(__DIR__.'/../../../../resources/js/locales/en.ts');
        $page = (string) file_get_contents(__DIR__.'/../../../../resources/js/pages/GameTables/CreateNotEligible.vue');

        $this->assertStringContainsString("reasonEventNotOpen: 'Este evento no está abierto a nuevas mesas'", $es);
        $this->assertStringContainsString("reasonEventNotOpen: 'This event is not open to new tables'", $en);
        $this->assertStringContainsString("event_not_open: t('gameTables.createNotEligible.reasonEventNotOpen')", $page);
    }

    /**
     * The event page offers to create a table only while the form takes the event: an ended
     * event shows no button, nor a «sign in to create» to visitors.
     */
    public function test_the_event_page_offers_no_creation_once_the_event_has_ended(): void
    {
        $open = $this->openEvent();
        $ended = $this->closedEvents()['ended'];

        $this->get(route('events.show', $ended->slug))
            ->assertInertia(fn (Assert $page) => $page
                ->where('eventCreationEligibility.eligible', false)
                ->where('eventCreationEligibility.reason', 'event_not_open')
                ->where('eventCreationEligibility.requires_authentication', false));

        $this->actingAs($this->author)
            ->get(route('events.show', $ended->slug))
            ->assertInertia(fn (Assert $page) => $page
                ->where('eventCreationEligibility.eligible', false)
                ->where('eventCreationEligibility.reason', 'event_not_open'));

        $this->actingAs($this->author)
            ->get(route('events.show', $open->slug))
            ->assertInertia(fn (Assert $page) => $page
                ->where('eventCreationEligibility.eligible', true)
                ->where('eventCreationEligibility.can_create_tables', true));
    }

    public function test_editing_a_draft_into_an_event_the_form_does_not_offer_is_refused(): void
    {
        $draft = $this->tableDraft();

        foreach ($this->closedEvents() as $event) {
            $this->actingAs($this->author)
                ->put(route('gametables.update', $draft), $this->tableData('Mesa corregida', $event->id))
                ->assertSessionHasErrors(['event_id' => self::REFUSED]);
        }

        $draft->refresh();
        $this->assertNull($draft->event_id);
        $this->assertSame('Mesa en borrador', $draft->title);
    }

    public function test_editing_a_draft_into_an_open_event_is_accepted(): void
    {
        $open = $this->openEvent();
        $draft = $this->tableDraft();

        $this->actingAs($this->author)
            ->put(route('gametables.update', $draft), $this->tableData('Mesa corregida', $open->id))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('gametables.my-tables'));

        $this->assertSame($open->id, $draft->refresh()->event_id);
    }

    /**
     * An admin may link a table to any event from the panel, and an open event may close later;
     * the author can still edit the table from the web while keeping that event, but cannot move
     * it to another closed one.
     */
    public function test_editing_a_table_keeps_its_stored_event_but_refuses_another_closed_one(): void
    {
        $closed = $this->closedEvents();
        $draft = $this->tableDraft($closed['unpublished']->id);

        $this->actingAs($this->author)
            ->put(route('gametables.update', $draft), $this->tableData('Mesa corregida', $closed['unpublished']->id))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('gametables.my-tables'));

        $draft->refresh();
        $this->assertSame($closed['unpublished']->id, $draft->event_id);
        $this->assertSame('Mesa corregida', $draft->title);

        $this->actingAs($this->author)
            ->put(route('gametables.update', $draft), $this->tableData('Mesa movida', $closed['ended']->id))
            ->assertSessionHasErrors(['event_id' => self::REFUSED]);

        $draft->refresh();
        $this->assertSame($closed['unpublished']->id, $draft->event_id);
        $this->assertSame('Mesa corregida', $draft->title);
    }

    /**
     * As the server keeps the stored event, the edit form offers it after the open ones, so the
     * selector does not come up empty; no other closed event is offered.
     */
    public function test_the_edit_form_offers_the_stored_event_even_once_it_has_closed(): void
    {
        $open = $this->openEvent();
        $closed = $this->closedEvents();
        $draft = $this->tableDraft($closed['ended']->id);

        $this->actingAs($this->author)
            ->get(route('gametables.edit', $draft->slug))
            ->assertInertia(fn (Assert $page) => $page
                ->component('GameTables/Edit', false)
                ->where('formData.events', fn ($events): bool => collect($events)->pluck('id')->all() === [$open->id, $closed['ended']->id]));

        // The create form still offers the open events only
        $this->actingAs($this->author)
            ->get(route('gametables.create'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('GameTables/Create', false)
                ->where('formData.events', fn ($events): bool => collect($events)->pluck('id')->all() === [$open->id]));
    }

    /**
     * The stored-event exception only applies to someone who may edit the table, so a PUT to
     * another user's draft is refused for its closed event like any other closed one.
     */
    public function test_someone_elses_table_event_is_refused_like_any_closed_one(): void
    {
        $closed = $this->closedEvents()['unpublished'];
        $draft = $this->tableDraft($closed->id, UserModel::factory()->create());

        $this->actingAs($this->author)
            ->put(route('gametables.update', $draft), $this->tableData('Mesa ajena', $closed->id))
            ->assertSessionHasErrors(['event_id' => self::REFUSED]);

        $draft->refresh();
        $this->assertSame('Mesa en borrador', $draft->title);
        $this->assertSame($closed->id, $draft->event_id);
    }

    public function test_the_refusal_is_translated_in_both_languages(): void
    {
        $this->assertSame(self::REFUSED, trans('game-tables::messages.validation.event_not_open', [], 'es'));
        $this->assertSame('You can only link the table to an event open to new tables', trans('game-tables::messages.validation.event_not_open', [], 'en'));
    }

    private function openEvent(): EventModel
    {
        $event = EventModel::factory()->published()->upcoming()->create();
        $this->enableTablesFor($event);

        return $event;
    }

    /**
     * Events the create form does not offer, one for each reason.
     *
     * @return array{not_enabled: EventModel, unpublished: EventModel, ended: EventModel, disabled: EventModel}
     */
    private function closedEvents(): array
    {
        $unpublished = EventModel::factory()->draft()->upcoming()->create();
        $this->enableTablesFor($unpublished);
        $ended = EventModel::factory()->published()->create([
            'start_date' => now()->subDays(10),
            'end_date' => now()->subDays(9),
        ]);
        $this->enableTablesFor($ended);
        $disabled = EventModel::factory()->published()->upcoming()->create();
        $this->enableTablesFor($disabled, enabled: false);

        return [
            'not_enabled' => EventModel::factory()->published()->upcoming()->create(),
            'unpublished' => $unpublished,
            'ended' => $ended,
            'disabled' => $disabled,
        ];
    }

    private function enableTablesFor(EventModel $event, bool $enabled = true): void
    {
        app(EventGameTableConfigRepositoryInterface::class)->save(new EventGameTableConfig(
            eventId: (string) $event->id,
            tablesEnabled: $enabled,
            schedulingMode: SchedulingMode::FreeSchedule,
            timeSlots: [],
            locationMode: LocationMode::FreeChoice,
            fixedLocation: null,
            eligibilityOverride: null,
        ));
    }

    /**
     * @return array<string, mixed>
     */
    private function tableData(string $title, ?string $eventId): array
    {
        return [
            'game_system_id' => $this->gameSystem->id,
            'event_id' => $eventId,
            'title' => $title,
            'starts_at' => now()->addWeeks(2)->toIso8601String(),
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

    private function tableDraft(?string $eventId = null, ?UserModel $creator = null): GameTableModel
    {
        return GameTableModel::create([
            'id' => (string) Str::uuid(),
            'game_system_id' => $this->gameSystem->id,
            'event_id' => $eventId,
            'created_by' => ($creator ?? $this->author)->id,
            'title' => 'Mesa en borrador',
            'slug' => 'mesa-en-borrador',
            'starts_at' => now()->addWeek(),
            'duration_minutes' => 240,
            'table_type' => 'one_shot',
            'table_format' => 'in_person',
            'status' => 'draft',
            'min_players' => 1,
            'max_players' => 5,
            'language' => 'es',
            'registration_type' => 'everyone',
            'auto_confirm' => true,
            'is_published' => false,
            'frontend_creation_status' => 'draft',
        ]);
    }
}
