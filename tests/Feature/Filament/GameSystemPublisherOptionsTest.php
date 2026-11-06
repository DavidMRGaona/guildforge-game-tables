<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Feature\Filament;

use Filament\Forms\Components\Select;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Modules\GameTables\Filament\Resources\GameSystemResource;
use Modules\GameTables\Filament\Resources\GameSystemResource\Pages\CreateGameSystem;
use Modules\GameTables\Filament\Resources\GameSystemResource\Pages\EditGameSystem;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameSystemModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\PublisherModel;
use Tests\Support\Modules\InteractsWithModulePanel;
use Tests\Support\Modules\ModuleTestCase;

/**
 * «Activo» of a publisher decides whether it is offered for a game system; a system keeps showing the
 * publisher it already has, even an inactive one.
 */
final class GameSystemPublisherOptionsTest extends ModuleTestCase
{
    use InteractsWithModulePanel;

    protected ?string $moduleName = 'game-tables';

    protected bool $autoEnableModule = true;

    private PublisherModel $active;

    private PublisherModel $inactive;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registerOnAdminPanel([GameSystemResource::class], []);
        $this->actingAsPanelAdmin();
        $this->active = $this->publisher('Editorial activa', true);
        $this->inactive = $this->publisher('Editorial inactiva', false);
    }

    public function test_only_active_publishers_are_offered_for_a_new_system(): void
    {
        Livewire::test(CreateGameSystem::class)
            ->assertFormFieldExists('publisher_id', fn (Select $field): bool => array_key_exists($this->active->id, $field->getOptions())
                && ! array_key_exists($this->inactive->id, $field->getOptions()));
    }

    public function test_a_system_keeps_its_inactive_publisher_among_the_options(): void
    {
        $system = GameSystemModel::create([
            'id' => (string) Str::uuid(),
            'name' => 'Sistema de ejemplo',
            'slug' => 'sistema-de-ejemplo',
            'publisher_id' => $this->inactive->id,
            'is_active' => true,
        ]);

        Livewire::test(EditGameSystem::class, ['record' => $system->getRouteKey()])
            ->assertFormFieldExists('publisher_id', fn (Select $field): bool => array_key_exists($this->active->id, $field->getOptions())
                && array_key_exists($this->inactive->id, $field->getOptions()));
    }

    private function publisher(string $name, bool $active): PublisherModel
    {
        return PublisherModel::create(['id' => (string) Str::uuid(), 'name' => $name, 'slug' => Str::slug($name), 'is_active' => $active]);
    }
}
