<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Feature\Filament;

use App\Filament\Resources\EventResource\Pages\EditEvent;
use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use App\Infrastructure\Persistence\Eloquent\Models\PermissionModel;
use Filament\Forms\Components\Select;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Modules\GameTables\Filament\RelationManagers\EventGameTableConfigRelationManager;
use Tests\Support\Modules\InteractsWithModulePanel;
use Tests\Support\Modules\ModuleTestCase;

/**
 * An event's own creation rules: the permissions are picked from the registered ones by their label,
 * and "anyone" is not a level, since creating always needs a session.
 */
final class EventCreationAccessFieldsTest extends ModuleTestCase
{
    use InteractsWithModulePanel;

    protected ?string $moduleName = 'game-tables';

    protected bool $autoEnableModule = true;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAsPanelAdmin();
        PermissionModel::firstOrCreate(
            ['key' => 'gametables:gametables.create'],
            ['label' => 'Crear mesas', 'resource' => 'gametables', 'action' => 'create'],
        );
    }

    public function test_the_override_levels_leave_out_anyone(): void
    {
        $this->configTab()
            ->set('configData.eligibility_override_enabled', true)
            ->assertFormFieldExists('eligibility_access_level', 'configForm', fn (Select $field): bool => ! array_key_exists('everyone', $field->getOptions())
                && array_key_exists('registered', $field->getOptions()));
    }

    public function test_the_override_permission_is_picked_by_its_label(): void
    {
        $this->configTab()
            ->set('configData.eligibility_override_enabled', true)
            ->set('configData.eligibility_access_level', 'permission')
            ->assertFormFieldExists('eligibility_required_permission', 'configForm', fn (Select $field): bool => ($field->getOptions()['gametables:gametables.create'] ?? null) === 'Crear mesas');
    }

    public function test_the_early_access_permission_is_picked_by_its_label(): void
    {
        $this->configTab()
            ->set('configData.early_access_enabled', true)
            ->set('configData.early_access_type', 'permission')
            ->assertFormFieldExists('early_access_permission', 'configForm', fn (Select $field): bool => ($field->getOptions()['gametables:gametables.create'] ?? null) === 'Crear mesas');
    }

    /**
     * @return Testable<EventGameTableConfigRelationManager>
     */
    private function configTab(): Testable
    {
        $event = EventModel::factory()->published()->create();

        return Livewire::test(EventGameTableConfigRelationManager::class, ['ownerRecord' => $event, 'pageClass' => EditEvent::class])
            ->set('configData.tables_enabled', true);
    }
}
