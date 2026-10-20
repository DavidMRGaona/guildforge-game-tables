<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Feature\Filament;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Modules\GameTables\Application\Services\GameTableServiceInterface;
use Modules\GameTables\Domain\Enums\TableStatus;
use Modules\GameTables\Domain\Events\GameTableCompleted;
use Modules\GameTables\Domain\Events\GameTablePublished;
use Modules\GameTables\Domain\Exceptions\InvalidStatusTransitionException;
use Modules\GameTables\Filament\Resources\GameTableResource;
use Modules\GameTables\Filament\Resources\GameTableResource\Pages\EditGameTable;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameSystemModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameTableModel;
use Tests\Support\Modules\InteractsWithModulePanel;
use Tests\Support\Modules\ModuleTestCase;

/**
 * «Publicar», «Iniciar» and «Completar» go through the service, as the automatic update and the
 * web do, so both give the same result: publishing announces GameTablePublished and completing
 * announces GameTableCompleted.
 */
final class GameTableStatusActionsTest extends ModuleTestCase
{
    use InteractsWithModulePanel;

    protected ?string $moduleName = 'game-tables';

    protected bool $autoEnableModule = true;

    private UserModel $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registerOnAdminPanel([GameTableResource::class], []);
        $this->admin = $this->actingAsPanelAdmin();
    }

    public function test_publishing_a_table_from_the_panel_announces_it(): void
    {
        Event::fake([GameTablePublished::class]);
        $table = $this->table(TableStatus::Scheduled, published: false);

        Livewire::test(EditGameTable::class, ['record' => $table->getRouteKey()])
            ->callAction('publish')
            ->assertFormSet(['is_published' => true, 'status' => TableStatus::Scheduled->value])
            ->assertActionHidden('publish')
            ->assertActionVisible('unpublish');

        $table->refresh();
        $this->assertTrue($table->is_published);
        $this->assertNotNull($table->published_at);
        Event::assertDispatchedTimes(GameTablePublished::class, 1);
        Event::assertDispatched(GameTablePublished::class, fn (GameTablePublished $event): bool => $event->gameTableId === $table->id);
    }

    public function test_starting_a_table_from_the_panel_puts_it_in_progress(): void
    {
        $table = $this->table(TableStatus::Full);

        Livewire::test(EditGameTable::class, ['record' => $table->getRouteKey()])
            ->callAction('start')
            ->assertFormSet(['status' => TableStatus::InProgress->value])
            ->assertActionHidden('start')
            ->assertActionVisible('complete');

        $this->assertSame(TableStatus::InProgress, $table->refresh()->status);
    }

    public function test_completing_a_table_from_the_panel_announces_it(): void
    {
        Event::fake([GameTableCompleted::class]);
        $table = $this->table(TableStatus::InProgress);

        Livewire::test(EditGameTable::class, ['record' => $table->getRouteKey()])
            ->callAction('complete')
            ->assertFormSet(['status' => TableStatus::Completed->value])
            ->assertActionHidden('complete');

        $this->assertSame(TableStatus::Completed, $table->refresh()->status);
        Event::assertDispatchedTimes(GameTableCompleted::class, 1);
        Event::assertDispatched(GameTableCompleted::class, fn (GameTableCompleted $event): bool => $event->gameTableId === $table->id);
    }

    // The automatic update (or another user) changed the table between the page load and the click
    public function test_a_status_already_changed_shows_a_warning_instead_of_failing(): void
    {
        $table = $this->table(TableStatus::InProgress);
        $service = $this->createStub(GameTableServiceInterface::class);
        $service->method('complete')->willThrowException(InvalidStatusTransitionException::fromTo('completed', 'completed'));
        $this->app->instance(GameTableServiceInterface::class, $service);

        Livewire::test(EditGameTable::class, ['record' => $table->getRouteKey()])
            ->callAction('complete')
            ->assertNotified('La mesa ya no está en un estado que permita este cambio');

        $this->assertSame(TableStatus::InProgress, $table->refresh()->status);
    }

    public function test_the_warning_is_translated_in_both_languages(): void
    {
        $this->assertSame('La mesa ya no está en un estado que permita este cambio', trans('game-tables::messages.notifications.status_already_changed', [], 'es'));
        $this->assertSame('The table is no longer in a status that allows this change', trans('game-tables::messages.notifications.status_already_changed', [], 'en'));
    }

    private function table(TableStatus $status, bool $published = true): GameTableModel
    {
        $system = GameSystemModel::create([
            'id' => (string) Str::uuid(),
            'name' => 'Sistema de prueba',
            'slug' => 'sistema-de-prueba',
            'is_active' => true,
        ]);

        return GameTableModel::create([
            'id' => (string) Str::uuid(),
            'game_system_id' => $system->id,
            'created_by' => $this->admin->id,
            'title' => 'Mesa de prueba',
            'slug' => 'mesa-de-prueba',
            'starts_at' => now()->subHour(),
            'duration_minutes' => 240,
            'table_type' => 'one_shot',
            'table_format' => 'in_person',
            'status' => $status->value,
            'min_players' => 2,
            'max_players' => 5,
            'language' => 'es',
            'is_published' => $published,
        ]);
    }
}
