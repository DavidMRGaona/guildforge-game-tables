<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Feature\Http\Controllers;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\GameTables\Domain\Enums\GameMasterRole;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\CampaignModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameMasterModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameSystemModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameTableModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\ParticipantModel;
use Tests\Support\Modules\ModuleTestCase;

/**
 * «Mis mesas» lists the drafts of the person and, apart, the published single tables they created or direct.
 */
final class MyTablesListingTest extends ModuleTestCase
{
    protected ?string $moduleName = 'game-tables';

    protected bool $autoEnableModule = true;

    private GameSystemModel $system;

    private UserModel $john;

    private UserModel $mary;

    protected function setUp(): void
    {
        parent::setUp();

        app('router')->getRoutes()->refreshNameLookups();
        config()->set('modules.settings.game-tables', []);
        $this->travelTo(CarbonImmutable::parse('2026-11-02 10:00:00'));

        $this->john = UserModel::factory()->create(['name' => 'John Doe', 'email' => 'john@example.com']);
        $this->mary = UserModel::factory()->create(['name' => 'Mary Major', 'email' => 'mary@example.com']);
        $this->system = GameSystemModel::create(['id' => (string) Str::uuid(), 'name' => 'Sistema', 'slug' => 'sistema', 'is_active' => true]);
    }

    public function test_the_creator_sees_their_published_table_without_managing_its_registrations_while_the_flag_is_off(): void
    {
        $table = $this->table('La cripta olvidada', $this->mary, delegates: false);
        $this->linkDirector($table, $this->mary);
        $this->pendingRow($table);

        $this->actingAs($this->mary)
            ->get(route('gametables.my-tables'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('GameTables/MyTables', false)
                ->has('drafts', 0)
                ->has('published', 1)
                ->where('published.0.id', $table->id)
                ->where('published.0.title', 'La cripta olvidada')
                ->where('published.0.slug', 'la-cripta-olvidada')
                ->where('published.0.status', 'scheduled')
                ->where('published.0.status_label', 'Programada')
                ->where('published.0.manages_registrations', false)
                ->where('published.0.pending_registrations', null));
    }

    public function test_a_linked_co_director_sees_the_table_with_its_pending_registrations_when_the_flag_is_on(): void
    {
        $table = $this->table('La cripta olvidada', $this->mary, delegates: true);
        $this->linkDirector($table, $this->mary);
        $this->linkDirector($table, $this->john);
        $this->pendingRow($table);
        $this->pendingRow($table);
        $this->pendingRow($table, 'confirmed');

        $this->actingAs($this->john)
            ->get(route('gametables.my-tables'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('published', 1)
                ->where('published.0.id', $table->id)
                ->where('published.0.manages_registrations', true)
                ->where('published.0.pending_registrations', 2));
    }

    public function test_the_creator_who_does_not_direct_the_table_cannot_manage_its_registrations(): void
    {
        $table = $this->table('La cripta olvidada', $this->mary, delegates: true);
        $this->linkDirector($table, $this->john);

        $this->actingAs($this->mary)
            ->get(route('gametables.my-tables'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('published', 1)
                ->where('published.0.manages_registrations', false)
                ->where('published.0.pending_registrations', null));
    }

    public function test_campaign_sessions_unpublished_tables_and_old_tables_never_appear_in_published(): void
    {
        $campaign = CampaignModel::create([
            'id' => (string) Str::uuid(),
            'title' => 'Campaña',
            'slug' => 'campana',
            'game_system_id' => $this->system->id,
            'created_by' => $this->john->id,
            'is_published' => true,
            'status' => 'recruiting',
        ]);
        $session = $this->table('Sesión de campaña', $this->john, delegates: true, attributes: ['campaign_id' => $campaign->id]);
        $this->linkDirector($session, $this->john);
        $draft = $this->table('Borrador', $this->john, delegates: true, attributes: ['is_published' => false]);
        $this->linkDirector($draft, $this->john);
        $old = $this->table('Mesa antigua', $this->john, delegates: true, attributes: ['starts_at' => CarbonImmutable::parse('2026-09-01 18:00:00')]);
        $this->linkDirector($old, $this->john);
        $recent = $this->table('Mesa reciente', $this->john, delegates: true, attributes: ['starts_at' => CarbonImmutable::parse('2026-10-20 18:00:00')]);
        $this->linkDirector($recent, $this->john);
        $upcoming = $this->table('Mesa próxima', $this->john, delegates: true);
        $this->linkDirector($upcoming, $this->john);

        $this->actingAs($this->john)
            ->get(route('gametables.my-tables'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('drafts', 1)
                ->where('drafts.0.title', 'Borrador')
                ->has('published', 2)
                ->where('published.0.title', 'Mesa reciente')
                ->where('published.1.title', 'Mesa próxima'));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function table(string $title, UserModel $creator, bool $delegates, array $attributes = []): GameTableModel
    {
        return GameTableModel::create([
            'id' => (string) Str::uuid(),
            'game_system_id' => $this->system->id,
            'created_by' => $creator->id,
            'title' => $title,
            'slug' => Str::slug($title),
            'starts_at' => CarbonImmutable::parse('2026-11-09 18:00:00'),
            'duration_minutes' => 240,
            'table_type' => 'one_shot',
            'table_format' => 'in_person',
            'status' => 'scheduled',
            'min_players' => 1,
            'max_players' => 3,
            'max_spectators' => 0,
            'language' => 'es',
            'registration_type' => 'everyone',
            'auto_confirm' => false,
            'is_published' => true,
            'delegates_registrations' => $delegates,
            ...$attributes,
        ]);
    }

    private function linkDirector(GameTableModel $table, UserModel $user): void
    {
        $gm = GameMasterModel::create([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'first_name' => 'GM',
            'role' => GameMasterRole::Main,
            'notify_by_email' => true,
            'is_name_public' => true,
        ]);
        $table->gameMasters()->attach($gm->id, ['source' => 'local', 'excluded' => false, 'sort_order' => 1]);
    }

    private function pendingRow(GameTableModel $table, string $status = 'pending'): void
    {
        ParticipantModel::create([
            'id' => (string) Str::uuid(),
            'game_table_id' => $table->id,
            'user_id' => UserModel::factory()->create()->id,
            'role' => 'player',
            'status' => $status,
            'confirmed_at' => $status === 'confirmed' ? now() : null,
        ]);
    }
}
