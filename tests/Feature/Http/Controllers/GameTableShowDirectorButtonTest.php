<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Feature\Http\Controllers;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\GameTables\Domain\Enums\GameMasterRole;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameMasterModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameSystemModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameTableModel;
use Tests\Support\Modules\ModuleTestCase;

/**
 * The table page offers «Gestionar inscritos» to the directors of a table that delegates its registrations.
 */
final class GameTableShowDirectorButtonTest extends ModuleTestCase
{
    protected ?string $moduleName = 'game-tables';

    protected bool $autoEnableModule = true;

    private GameTableModel $table;

    private UserModel $john;

    protected function setUp(): void
    {
        parent::setUp();

        app('router')->getRoutes()->refreshNameLookups();
        config()->set('modules.settings.game-tables', []);

        $this->john = UserModel::factory()->create(['name' => 'John Doe', 'email' => 'john@example.com']);
        $system = GameSystemModel::create(['id' => (string) Str::uuid(), 'name' => 'Sistema', 'slug' => 'sistema', 'is_active' => true]);
        $this->table = GameTableModel::create([
            'id' => (string) Str::uuid(),
            'game_system_id' => $system->id,
            'created_by' => UserModel::factory()->create()->id,
            'title' => 'La cripta olvidada',
            'slug' => 'la-cripta-olvidada',
            'starts_at' => now()->addWeek(),
            'duration_minutes' => 240,
            'table_type' => 'one_shot',
            'table_format' => 'in_person',
            'status' => 'scheduled',
            'min_players' => 1,
            'max_players' => 3,
            'language' => 'es',
            'registration_type' => 'everyone',
            'auto_confirm' => false,
            'is_published' => true,
            'delegates_registrations' => true,
        ]);
        $gm = GameMasterModel::create([
            'id' => (string) Str::uuid(),
            'user_id' => $this->john->id,
            'first_name' => 'GM',
            'role' => GameMasterRole::Main,
            'notify_by_email' => true,
            'is_name_public' => true,
        ]);
        $this->table->gameMasters()->attach($gm->id, ['source' => 'local', 'excluded' => false, 'sort_order' => 1]);
    }

    public function test_a_director_of_a_delegating_table_gets_the_button(): void
    {
        $this->actingAs($this->john)
            ->get(route('gametables.show', $this->table->slug))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('canManageRegistrations', true));
    }

    public function test_an_admin_who_is_not_a_director_does_not_get_it(): void
    {
        $this->actingAs(UserModel::factory()->admin()->create())
            ->get(route('gametables.show', $this->table->slug))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('canManageRegistrations', false));
    }

    public function test_a_director_does_not_get_it_while_the_table_keeps_its_registrations(): void
    {
        $this->table->update(['delegates_registrations' => false]);

        $this->actingAs($this->john)
            ->get(route('gametables.show', $this->table->slug))
            ->assertInertia(fn (Assert $page) => $page->where('canManageRegistrations', false));
    }

    public function test_a_visitor_does_not_get_it(): void
    {
        $this->get(route('gametables.show', $this->table->slug))
            ->assertInertia(fn (Assert $page) => $page->where('canManageRegistrations', false));
    }
}
