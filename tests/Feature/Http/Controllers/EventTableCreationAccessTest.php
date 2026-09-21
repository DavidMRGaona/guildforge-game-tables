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
use Tests\Support\Modules\ModuleTestCase;

/**
 * An event only hosts game tables once an admin turns the toggle on, and an event
 * that was never configured has no config row at all. Global creation settings are
 * deliberately permissive here so that the refusal can only come from the event.
 */
final class EventTableCreationAccessTest extends ModuleTestCase
{
    protected ?string $moduleName = 'game-tables';

    protected bool $autoEnableModule = true;

    private UserModel $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = UserModel::create([
            'id' => (string) Str::uuid(),
            'name' => 'Table Creator',
            'email' => 'event-access@example.com',
            'password' => 'password',
        ]);

        config()->set('modules.settings.game-tables.frontend_creation.enabled', true);
        config()->set('modules.settings.game-tables.frontend_creation.access_level', 'registered');
    }

    public function test_an_event_without_config_refuses_table_creation(): void
    {
        $event = EventModel::factory()->create();

        $this->actingAs($this->user)
            ->get('/mesas/crear?event=' . $event->slug)
            ->assertInertia(
                fn (Assert $page) => $page
                    ->component('GameTables/CreateNotEligible', false)
                    ->where('reason', 'tables_not_enabled_for_event')
            );
    }

    public function test_an_event_with_tables_enabled_allows_table_creation(): void
    {
        $event = EventModel::factory()->create();
        $this->enableTablesFor((string) $event->id);

        $this->actingAs($this->user)
            ->get('/mesas/crear?event=' . $event->slug)
            ->assertInertia(fn (Assert $page) => $page->component('GameTables/Create', false));
    }

    private function enableTablesFor(string $eventId): void
    {
        app(EventGameTableConfigRepositoryInterface::class)->save(new EventGameTableConfig(
            eventId: $eventId,
            tablesEnabled: true,
            schedulingMode: SchedulingMode::FreeSchedule,
            timeSlots: [],
            locationMode: LocationMode::FreeChoice,
            fixedLocation: null,
            eligibilityOverride: null,
            earlyAccessEnabled: false,
            creationOpensAt: null,
            earlyAccessTier: null,
        ));
    }
}
