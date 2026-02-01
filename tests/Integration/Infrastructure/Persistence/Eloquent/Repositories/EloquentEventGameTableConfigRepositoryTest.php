<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Integration\Infrastructure\Persistence\Eloquent\Repositories;

use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\GameTables\Domain\Entities\EventGameTableConfig;
use Modules\GameTables\Domain\Enums\CreationAccessLevel;
use Modules\GameTables\Domain\Enums\LocationMode;
use Modules\GameTables\Domain\Enums\SchedulingMode;
use Modules\GameTables\Domain\Repositories\EventGameTableConfigRepositoryInterface;
use Modules\GameTables\Domain\ValueObjects\EligibilityOverride;
use Modules\GameTables\Domain\ValueObjects\TimeSlotDefinition;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\EventGameTableConfigModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Repositories\EloquentEventGameTableConfigRepository;
use Tests\TestCase;

final class EloquentEventGameTableConfigRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private EloquentEventGameTableConfigRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new EloquentEventGameTableConfigRepository();
    }

    public function test_it_implements_repository_interface(): void
    {
        $this->assertInstanceOf(EventGameTableConfigRepositoryInterface::class, $this->repository);
    }

    public function test_it_saves_new_config(): void
    {
        $event = EventModel::factory()->create();

        $config = new EventGameTableConfig(
            eventId: $event->id,
            tablesEnabled: true,
            schedulingMode: SchedulingMode::FreeSchedule,
            timeSlots: [],
            locationMode: LocationMode::FreeChoice,
            fixedLocation: null,
            eligibilityOverride: null,
        );

        $this->repository->save($config);

        $this->assertDatabaseHas('game_tables_event_configs', [
            'event_id' => $event->id,
            'tables_enabled' => true,
            'scheduling_mode' => 'free',
            'location_mode' => 'free',
        ]);
    }

    public function test_it_saves_config_with_time_slots(): void
    {
        $event = EventModel::factory()->create();
        $timeSlot = TimeSlotDefinition::create(
            label: 'Morning',
            startTime: new DateTimeImmutable('2026-03-01 09:00:00'),
            endTime: new DateTimeImmutable('2026-03-01 13:00:00'),
            maxTables: 5,
        );

        $config = new EventGameTableConfig(
            eventId: $event->id,
            tablesEnabled: true,
            schedulingMode: SchedulingMode::SlotBased,
            timeSlots: [$timeSlot],
            locationMode: LocationMode::FixedLocation,
            fixedLocation: 'Main Hall',
            eligibilityOverride: null,
        );

        $this->repository->save($config);

        $this->assertDatabaseHas('game_tables_event_configs', [
            'event_id' => $event->id,
            'tables_enabled' => true,
            'scheduling_mode' => 'slots',
            'location_mode' => 'fixed',
            'fixed_location' => 'Main Hall',
        ]);

        $model = EventGameTableConfigModel::query()->find($event->id);
        $this->assertNotNull($model);
        $this->assertCount(1, $model->time_slots);
        $this->assertEquals('Morning', $model->time_slots[0]['label']);
    }

    public function test_it_saves_config_with_eligibility_override(): void
    {
        $event = EventModel::factory()->create();
        $override = EligibilityOverride::create(
            accessLevel: CreationAccessLevel::Role,
            allowedRoles: ['editor', 'admin'],
        );

        $config = new EventGameTableConfig(
            eventId: $event->id,
            tablesEnabled: true,
            schedulingMode: SchedulingMode::FreeSchedule,
            timeSlots: [],
            locationMode: LocationMode::EventLocation,
            fixedLocation: null,
            eligibilityOverride: $override,
        );

        $this->repository->save($config);

        $model = EventGameTableConfigModel::query()->find($event->id);
        $this->assertNotNull($model);
        $this->assertNotNull($model->eligibility_override);
        $this->assertEquals('role', $model->eligibility_override['access_level']);
        $this->assertEquals(['editor', 'admin'], $model->eligibility_override['allowed_roles']);
    }

    public function test_it_updates_existing_config(): void
    {
        $event = EventModel::factory()->create();

        EventGameTableConfigModel::create([
            'event_id' => $event->id,
            'tables_enabled' => false,
            'scheduling_mode' => 'free',
            'location_mode' => 'free',
        ]);

        $updatedConfig = new EventGameTableConfig(
            eventId: $event->id,
            tablesEnabled: true,
            schedulingMode: SchedulingMode::SlotBased,
            timeSlots: [],
            locationMode: LocationMode::FixedLocation,
            fixedLocation: 'Room B',
            eligibilityOverride: null,
        );

        $this->repository->save($updatedConfig);

        $this->assertDatabaseHas('game_tables_event_configs', [
            'event_id' => $event->id,
            'tables_enabled' => true,
            'scheduling_mode' => 'slots',
            'location_mode' => 'fixed',
            'fixed_location' => 'Room B',
        ]);

        // Only one record should exist
        $this->assertDatabaseCount('game_tables_event_configs', 1);
    }

    public function test_it_finds_config_by_event(): void
    {
        $event = EventModel::factory()->create();
        $override = EligibilityOverride::create(
            accessLevel: CreationAccessLevel::Registered,
        );

        EventGameTableConfigModel::create([
            'event_id' => $event->id,
            'tables_enabled' => true,
            'scheduling_mode' => 'slots',
            'time_slots' => [
                [
                    'label' => 'Afternoon',
                    'start_time' => '2026-03-01T14:00:00+00:00',
                    'end_time' => '2026-03-01T18:00:00+00:00',
                    'max_tables' => 3,
                ],
            ],
            'location_mode' => 'event',
            'fixed_location' => null,
            'eligibility_override' => $override->toArray(),
        ]);

        $config = $this->repository->findByEvent($event->id);

        $this->assertNotNull($config);
        $this->assertEquals($event->id, $config->eventId());
        $this->assertTrue($config->isEnabled());
        $this->assertEquals(SchedulingMode::SlotBased, $config->schedulingMode());
        $this->assertCount(1, $config->timeSlots());
        $this->assertEquals('Afternoon', $config->timeSlots()[0]->label);
        $this->assertEquals(LocationMode::EventLocation, $config->locationMode());
        $this->assertNotNull($config->eligibilityOverride());
        $this->assertEquals(CreationAccessLevel::Registered, $config->eligibilityOverride()->accessLevel);
    }

    public function test_it_returns_null_when_config_not_found(): void
    {
        $config = $this->repository->findByEvent('non-existent-id');

        $this->assertNull($config);
    }

    public function test_it_returns_default_config_when_not_found(): void
    {
        $config = $this->repository->findByEventOrDefault('non-existent-id');

        $this->assertNotNull($config);
        $this->assertEquals('non-existent-id', $config->eventId());
        $this->assertFalse($config->isEnabled());
        $this->assertEquals(SchedulingMode::FreeSchedule, $config->schedulingMode());
        $this->assertEmpty($config->timeSlots());
        $this->assertEquals(LocationMode::FreeChoice, $config->locationMode());
        $this->assertNull($config->fixedLocation());
        $this->assertNull($config->eligibilityOverride());
    }

    public function test_it_deletes_config(): void
    {
        $event = EventModel::factory()->create();

        EventGameTableConfigModel::create([
            'event_id' => $event->id,
            'tables_enabled' => true,
            'scheduling_mode' => 'free',
            'location_mode' => 'free',
        ]);

        $this->repository->delete($event->id);

        $this->assertDatabaseMissing('game_tables_event_configs', [
            'event_id' => $event->id,
        ]);
    }

    public function test_it_checks_if_config_exists(): void
    {
        $event = EventModel::factory()->create();

        $this->assertFalse($this->repository->exists($event->id));

        EventGameTableConfigModel::create([
            'event_id' => $event->id,
            'tables_enabled' => true,
            'scheduling_mode' => 'free',
            'location_mode' => 'free',
        ]);

        $this->assertTrue($this->repository->exists($event->id));
    }
}
