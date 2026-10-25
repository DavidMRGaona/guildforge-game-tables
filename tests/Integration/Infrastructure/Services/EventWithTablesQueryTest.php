<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Integration\Infrastructure\Services;

use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameSystemModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameTableModel;
use Modules\GameTables\Infrastructure\Services\EventWithTablesQuery;
use Tests\Support\Modules\ModuleTestCase;

/**
 * The event filter of the tables list: published events that have not ended, with how many
 * published tables (neither drafts nor cancelled) each has.
 */
final class EventWithTablesQueryTest extends ModuleTestCase
{
    protected ?string $moduleName = 'game-tables';

    protected bool $autoEnableModule = true;

    public function test_it_lists_the_open_events_with_their_published_tables(): void
    {
        $later = $this->event('Jornadas de otoño', startsInDays: 20);
        $sooner = $this->event('Torneo de invierno', startsInDays: 10);
        $this->table($later, 'scheduled');
        $this->table($later, 'full');
        $this->table($sooner, 'scheduled');

        $this->assertSame([
            ['id' => $sooner->id, 'title' => 'Torneo de invierno', 'count' => 1],
            ['id' => $later->id, 'title' => 'Jornadas de otoño', 'count' => 2],
        ], (new EventWithTablesQuery)->getEventsWithPublishedTables());
    }

    public function test_drafts_cancelled_and_unpublished_tables_do_not_count(): void
    {
        $event = $this->event('Jornadas de otoño', startsInDays: 20);
        $this->table($event, 'scheduled');
        $this->table($event, 'draft');
        $this->table($event, 'cancelled');
        $this->table($event, 'scheduled', published: false);

        $this->assertSame(
            [['id' => $event->id, 'title' => 'Jornadas de otoño', 'count' => 1]],
            (new EventWithTablesQuery)->getEventsWithPublishedTables(),
        );
    }

    public function test_events_without_tables_unpublished_or_ended_are_left_out(): void
    {
        $this->event('Sin mesas', startsInDays: 5);
        $this->table($this->event('Sin publicar', startsInDays: 5, published: false), 'scheduled');
        $this->table($this->event('Ya terminado', startsInDays: -10), 'scheduled');

        $this->assertSame([], (new EventWithTablesQuery)->getEventsWithPublishedTables());
    }

    private function event(string $title, int $startsInDays, bool $published = true): EventModel
    {
        // Dates relative to now: the factory would draw them from the real clock
        $start = CarbonImmutable::now()->addDays($startsInDays);

        return EventModel::factory()->create([
            'title' => $title,
            'slug' => Str::slug($title).'-'.Str::random(5),
            'start_date' => $start,
            'end_date' => $start->addDay(),
            'is_published' => $published,
        ]);
    }

    private function table(EventModel $event, string $status, bool $published = true): void
    {
        $system = GameSystemModel::query()->first()
            ?? GameSystemModel::create(['id' => (string) Str::uuid(), 'name' => 'Sistema', 'slug' => 'sistema', 'is_active' => true]);

        GameTableModel::create([
            'id' => (string) Str::uuid(),
            'game_system_id' => $system->id,
            'event_id' => $event->id,
            'created_by' => UserModel::factory()->create()->id,
            'title' => 'Mesa',
            'slug' => 'mesa-'.Str::random(8),
            'starts_at' => CarbonImmutable::parse($event->start_date),
            'duration_minutes' => 240,
            'table_type' => 'one_shot',
            'table_format' => 'in_person',
            'status' => $status,
            'min_players' => 1,
            'max_players' => 5,
            'max_spectators' => 0,
            'language' => 'es',
            'is_published' => $published,
        ]);
    }
}
