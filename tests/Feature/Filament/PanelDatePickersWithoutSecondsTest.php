<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Feature\Filament;

use App\Filament\Resources\EventResource\Pages\EditEvent;
use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use Filament\Forms\Components\DateTimePicker;
use Livewire\Livewire;
use Modules\GameTables\Filament\RelationManagers\EventGameTableConfigRelationManager;
use Modules\GameTables\Filament\Resources\GameTableResource;
use Modules\GameTables\Filament\Resources\GameTableResource\Pages\CreateGameTable;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\Modules\InteractsWithModulePanel;
use Tests\Support\Modules\ModuleTestCase;

/**
 * The date and time fields of the panel ask for hours and minutes only: seconds mean nothing for
 * the start of a table or the opening of its sign-ups, and on a phone their row took room under
 * the calendar (game-tables#338).
 */
final class PanelDatePickersWithoutSecondsTest extends ModuleTestCase
{
    use InteractsWithModulePanel;

    protected ?string $moduleName = 'game-tables';

    protected bool $autoEnableModule = true;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registerOnAdminPanel([GameTableResource::class], []);
        $this->actingAsPanelAdmin();
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function tableFields(): iterable
    {
        yield 'start' => ['starts_at'];
        yield 'sign-ups open' => ['registration_opens_at'];
        yield 'sign-ups close' => ['registration_closes_at'];
    }

    #[DataProvider('tableFields')]
    public function test_a_table_date_field_has_no_seconds(string $field): void
    {
        Livewire::test(CreateGameTable::class)
            ->assertFormFieldExists($field, fn (DateTimePicker $picker): bool => ! $picker->hasSeconds());
    }

    public function test_the_opening_of_table_creation_for_an_event_has_no_seconds(): void
    {
        $event = EventModel::factory()->published()->create();

        Livewire::test(EventGameTableConfigRelationManager::class, ['ownerRecord' => $event, 'pageClass' => EditEvent::class])
            ->set('configData.tables_enabled', true)
            ->assertFormFieldExists('creation_opens_at', 'configForm', fn (DateTimePicker $picker): bool => ! $picker->hasSeconds());
    }
}
