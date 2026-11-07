<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Feature\Filament;

use App\Filament\Resources\EventResource\Pages\EditEvent;
use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use App\Infrastructure\Persistence\Eloquent\Models\PermissionModel;
use App\Infrastructure\Persistence\Eloquent\Models\RoleModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Support\Str;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Modules\GameTables\Domain\Enums\LocationMode;
use Modules\GameTables\Domain\Enums\SchedulingMode;
use Modules\GameTables\Filament\RelationManagers\EventGameTableConfigRelationManager;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\EventGameTableConfigModel;
use Tests\Support\Modules\InteractsWithModulePanel;
use Tests\Support\Modules\ModuleTestCase;

/**
 * The event's game tables tab follows the module's permissions, not the event's: seeing it
 * needs gametables.view_any or gametables.settings, and saving it needs gametables.settings,
 * checked on the server, because its rules (who creates tables, early access) stand in for
 * the module's creation settings for that event.
 */
final class EventGameTablesTabPermissionsTest extends ModuleTestCase
{
    use InteractsWithModulePanel;

    private const string EVENTS_UPDATE = 'events.update';

    private const string VIEW_ANY = 'gametables:gametables.view_any';

    private const string UPDATE = 'gametables:gametables.update';

    private const string SETTINGS = 'gametables:gametables.settings';

    protected ?string $moduleName = 'game-tables';

    protected bool $autoEnableModule = true;

    public function test_the_tab_needs_view_any_or_settings(): void
    {
        $event = EventModel::factory()->create();

        $this->actingAs($this->userWith([self::EVENTS_UPDATE]));
        $this->assertFalse(EventGameTableConfigRelationManager::canViewForRecord($event, EditEvent::class));

        $this->actingAs($this->userWith([self::EVENTS_UPDATE, self::VIEW_ANY]));
        $this->assertTrue(EventGameTableConfigRelationManager::canViewForRecord($event, EditEvent::class));

        $this->actingAs($this->userWith([self::EVENTS_UPDATE, self::SETTINGS]));
        $this->assertTrue(EventGameTableConfigRelationManager::canViewForRecord($event, EditEvent::class));
    }

    public function test_editing_events_alone_cannot_open_the_tab(): void
    {
        $event = EventModel::factory()->create();
        $this->actingAs($this->userWith([self::EVENTS_UPDATE]));

        // Filament checks canViewForRecord() on every request of the tab, so no save can follow
        $this->tab($event)->assertForbidden();

        $this->assertNull(EventGameTableConfigModel::query()->find($event->id));
    }

    public function test_viewing_alone_shows_the_tab_read_only(): void
    {
        $event = $this->eventWithTables();
        $this->actingAs($this->userWith([self::EVENTS_UPDATE, self::VIEW_ANY, self::UPDATE]));

        $this->tab($event)
            ->assertOk()
            ->assertFormFieldIsDisabled('tables_enabled', 'configForm')
            ->assertFormFieldIsDisabled('location_mode', 'configForm')
            ->assertDontSee(__('game-tables::messages.event_config.save'));

        $this->actingAs($this->userWith([self::EVENTS_UPDATE, self::VIEW_ANY, self::SETTINGS]));

        $this->tab($event)
            ->assertFormFieldIsEnabled('tables_enabled', 'configForm')
            ->assertSee(__('game-tables::messages.event_config.save'));
    }

    public function test_enabling_the_tables_needs_settings(): void
    {
        $event = EventModel::factory()->create();
        $this->actingAs($this->userWith([self::EVENTS_UPDATE, self::VIEW_ANY, self::UPDATE]));

        $this->tab($event)
            ->set('configData.tables_enabled', true)
            ->set('configData.scheduling_mode', SchedulingMode::FreeSchedule->value)
            ->set('configData.location_mode', LocationMode::FreeChoice->value)
            ->set('configData.creation_opens_at', '2026-11-01 10:00:00')
            ->call('save')
            ->assertForbidden();

        $this->assertNull(EventGameTableConfigModel::query()->find($event->id));
    }

    public function test_disabling_the_tables_needs_settings(): void
    {
        $event = $this->eventWithTables();
        $this->actingAs($this->userWith([self::EVENTS_UPDATE, self::VIEW_ANY, self::UPDATE]));

        $this->tab($event)
            ->set('configData.tables_enabled', false)
            ->call('save')
            ->assertForbidden();

        $this->assertTrue((bool) EventGameTableConfigModel::query()->findOrFail($event->id)->tables_enabled);
    }

    public function test_settings_allows_changing_the_configuration(): void
    {
        $event = $this->eventWithTables();
        $this->actingAs($this->userWith([self::EVENTS_UPDATE, self::VIEW_ANY, self::SETTINGS]));

        $this->tab($event)
            ->set('configData.fixed_location', 'Sala grande')
            ->set('configData.location_mode', LocationMode::FixedLocation->value)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('Sala grande', EventGameTableConfigModel::query()->findOrFail($event->id)->fixed_location);

        $this->tab($event)
            ->set('configData.tables_enabled', false)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertFalse((bool) EventGameTableConfigModel::query()->findOrFail($event->id)->tables_enabled);
    }

    /**
     * @return Testable<EventGameTableConfigRelationManager>
     */
    private function tab(EventModel $event): Testable
    {
        return Livewire::test(EventGameTableConfigRelationManager::class, ['ownerRecord' => $event, 'pageClass' => EditEvent::class]);
    }

    private function eventWithTables(): EventModel
    {
        $event = EventModel::factory()->create();

        EventGameTableConfigModel::create([
            'event_id' => $event->id,
            'tables_enabled' => true,
            'scheduling_mode' => SchedulingMode::FreeSchedule->value,
            'location_mode' => LocationMode::FreeChoice->value,
            'time_slots' => [],
            'creation_opens_at' => '2026-11-01 10:00:00',
        ]);

        return $event;
    }

    /**
     * @param  list<string>  $permissionKeys
     */
    private function userWith(array $permissionKeys): UserModel
    {
        $role = RoleModel::create(['name' => 'tester_'.uniqid(), 'display_name' => 'Permissions tester']);

        foreach ($permissionKeys as $key) {
            $module = str_contains($key, ':') ? Str::before($key, ':') : null;
            $permission = PermissionModel::firstOrCreate(
                ['key' => $key],
                ['label' => $key, 'resource' => Str::before(Str::after($key, ':'), '.'), 'action' => Str::afterLast($key, '.'), 'module' => $module],
            );
            $role->permissions()->attach($permission->id);
        }

        $user = UserModel::factory()->editor()->create();
        $user->roles()->attach($role->id);

        return $user;
    }
}
