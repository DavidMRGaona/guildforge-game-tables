<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Integration\Linking;

use App\Application\Linking\Contracts\LinkableRecordSourceInterface;
use App\Application\Linking\DTOs\LinkableRecordDTO;
use App\Application\Linking\Services\LinkableRecordLookupInterface;
use App\Infrastructure\Persistence\Eloquent\Models\PermissionModel;
use App\Infrastructure\Persistence\Eloquent\Models\RoleModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Support\Str;
use Modules\GameTables\Domain\Enums\GameMasterRole;
use Modules\GameTables\Filament\Linking\Sources\GameTableLinkableRecordSource;
use Modules\GameTables\Filament\Resources\GameTableResource;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\CampaignModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameMasterModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameSystemModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameTableModel;
use Tests\Support\Authorization\CreatesUsersWithPermissions;
use Tests\Support\Modules\InteractsWithModulePanel;
use Tests\Support\Modules\ModuleTestCase;

/**
 * Game tables as linkable records (core contract), read through the core's lookup as any consumer
 * does: what their page shows to everybody, drafts for who views tables in the panel, and the
 * tables each person organizes.
 */
final class GameTableLinkableRecordSourceTest extends ModuleTestCase
{
    use CreatesUsersWithPermissions;
    use InteractsWithModulePanel;

    protected ?string $moduleName = 'game-tables';

    protected bool $autoEnableModule = true;

    private GameSystemModel $system;

    private LinkableRecordLookupInterface $lookup;

    protected function setUp(): void
    {
        parent::setUp();

        $this->useUtcStorage();
        $this->system = GameSystemModel::create(['id' => (string) Str::uuid(), 'name' => 'Sistema de ejemplo', 'slug' => Str::random(10), 'is_active' => true]);
        $this->lookup = $this->app->make(LinkableRecordLookupInterface::class);
    }

    public function test_game_tables_are_offered_with_their_label(): void
    {
        $this->assertSame('Mesa de rol', $this->lookup->types()[GameTableLinkableRecordSource::TYPE] ?? null);
    }

    public function test_a_visitor_finds_published_tables_only(): void
    {
        $published = $this->table(['title' => 'La tumba de los horrores']);
        $draft = $this->table(['is_published' => false, 'status' => 'draft']);

        $record = $this->lookup->find('game-table', $published->id, LinkableRecordSourceInterface::VISITOR);

        $this->assertSame('La tumba de los horrores', $record?->title);
        $this->assertSame($published->starts_at->getTimestamp(), $record->date?->getTimestamp());
        $this->assertNull($record->panelUrl);
        $this->assertNull($this->lookup->find('game-table', $draft->id, LinkableRecordSourceInterface::VISITOR));
        $this->assertNull($this->lookup->find('game-table', 'not-a-uuid', LinkableRecordSourceInterface::VISITOR));
    }

    public function test_who_views_tables_in_the_panel_finds_drafts_too_and_gets_the_edit_page_when_allowed(): void
    {
        $this->registerOnAdminPanel([GameTableResource::class]);
        $draft = $this->table(['is_published' => false, 'status' => 'draft']);
        $viewer = $this->editorWithPermissions(['gametables:gametables.view']);
        $editor = $this->editorWithPermissions(['gametables:gametables.view', 'gametables:gametables.update']);

        $seen = $this->lookup->find('game-table', $draft->id, $viewer->id);
        $edited = $this->lookup->find('game-table', $draft->id, $editor->id);

        $this->assertSame($draft->id, $seen?->id);
        $this->assertNull($seen->panelUrl);
        $this->assertStringEndsWith("/{$draft->slug}/edit", (string) $edited?->panelUrl);
    }

    public function test_without_the_panel_routes_a_table_is_still_found_without_its_edit_page(): void
    {
        // A queued job or a request where the panel is not built: the record never hides for it
        $table = $this->table();
        $editor = $this->editorWithPermissions(['gametables:gametables.view', 'gametables:gametables.update']);

        $record = $this->lookup->find('game-table', $table->id, $editor->id);

        $this->assertSame($table->id, $record?->id);
        $this->assertNull($record->panelUrl);
    }

    public function test_whoever_created_a_draft_finds_it_from_the_source_itself(): void
    {
        // Through the lookup they are a visitor unless they open the panel: the source still
        // follows the policy, which lets the creator view their own table
        $creator = UserModel::factory()->create();
        $draft = $this->table(['is_published' => false, 'status' => 'draft', 'created_by' => $creator->id]);
        $source = $this->app->make(GameTableLinkableRecordSource::class);

        $this->assertSame($draft->id, $source->find($draft->id, $creator->id)?->id);
        $this->assertNull($source->find($draft->id, UserModel::factory()->create()->id));
    }

    public function test_finding_many_answers_the_visible_ones_in_one_go(): void
    {
        $one = $this->table();
        $two = $this->table();
        $hidden = $this->table(['is_published' => false]);

        $found = $this->lookup->findMany('game-table', [$one->id, $hidden->id, $two->id], LinkableRecordSourceInterface::VISITOR);

        $this->assertSame([$one->id, $two->id], array_keys($found));
    }

    public function test_a_search_lists_upcoming_tables_by_start_then_the_latest_played(): void
    {
        $later = $this->table(['title' => 'Más adelante', 'starts_at' => now()->addWeeks(2)]);
        $next = $this->table(['title' => 'La próxima', 'starts_at' => now()->addDay()]);
        $old = $this->table(['title' => 'Hace tiempo', 'starts_at' => now()->subMonth()]);
        $recent = $this->table(['title' => 'Ayer', 'starts_at' => now()->subDay()]);

        $this->assertSame(
            [$next->id, $later->id, $recent->id, $old->id],
            $this->ids($this->lookup->search('game-table', '', LinkableRecordSourceInterface::VISITOR)),
        );
        $this->assertSame([$next->id, $later->id], $this->ids($this->lookup->search('game-table', '', LinkableRecordSourceInterface::VISITOR, 2)));
        $this->assertSame([$next->id], $this->ids($this->lookup->search('game-table', 'PRÓXIMA', LinkableRecordSourceInterface::VISITOR)));
        $this->assertSame([], $this->lookup->search('game-table', '100%', LinkableRecordSourceInterface::VISITOR));
    }

    public function test_the_tables_a_person_organizes_are_the_ones_they_created_or_direct(): void
    {
        $person = UserModel::factory()->create();
        $created = $this->table(['created_by' => $person->id, 'starts_at' => now()->addDays(3)]);
        $directed = $this->table(['starts_at' => now()->addDay()]);
        $directed->gameMasters()->attach($this->gameMaster($person, namePublic: false)->id, ['source' => 'local', 'excluded' => false, 'sort_order' => 1]);
        $campaign = $this->campaign();
        $campaign->gameMasters()->attach($this->gameMaster($person)->id, ['sort_order' => 1]);
        $session = $this->table(['campaign_id' => $campaign->id, 'starts_at' => now()->addDays(2)]);
        $played = $this->table(['created_by' => $person->id, 'starts_at' => now()->subWeek()]);
        $this->table();

        $this->assertSame(
            [$directed->id, $session->id, $created->id, $played->id],
            $this->ids($this->lookup->organizedBy('game-table', $person)),
        );
    }

    public function test_drafts_cancelled_tables_and_excluded_sessions_are_not_organized(): void
    {
        $person = UserModel::factory()->create();
        $this->table(['created_by' => $person->id, 'is_published' => false, 'frontend_creation_status' => 'pending_review']);
        $this->table(['created_by' => $person->id, 'status' => 'draft']);
        $this->table(['created_by' => $person->id, 'status' => 'cancelled']);
        $campaign = $this->campaign();
        $gm = $this->gameMaster($person);
        $campaign->gameMasters()->attach($gm->id, ['sort_order' => 1]);
        $excluded = $this->table(['campaign_id' => $campaign->id]);
        $excluded->gameMasters()->attach($gm->id, ['source' => 'inherited', 'excluded' => true, 'sort_order' => 1]);

        $this->assertSame([], $this->lookup->organizedBy('game-table', $person));
    }

    public function test_who_views_any_table_without_the_panel_does_not_organize_their_drafts(): void
    {
        // organizedBy() gets the real id of anybody, with no panel check: the view permission that
        // shows drafts in the panel must not show them on the site
        $this->registerOnAdminPanel([GameTableResource::class]);
        $person = UserModel::factory()->create();
        $role = RoleModel::create(['name' => 'tester-'.uniqid(), 'display_name' => 'Ve todas las mesas']);
        foreach (['gametables:gametables.view_any', 'gametables:gametables.view', 'gametables:gametables.update'] as $key) {
            [$resource, $action] = explode('.', $key, 2);
            $role->permissions()->attach(PermissionModel::firstOrCreate(['key' => $key], ['label' => $key, 'resource' => $resource, 'action' => $action])->id);
        }
        $person->roles()->attach($role->id);
        $published = $this->table(['created_by' => $person->id]);
        $this->table(['created_by' => $person->id, 'is_published' => false, 'status' => 'draft']);
        $this->table(['created_by' => $person->id, 'is_published' => false, 'frontend_creation_status' => 'pending_review']);

        $organized = $this->lookup->organizedBy('game-table', $person);

        $this->assertSame([$published->id], $this->ids($organized));
        $this->assertNull($organized[0]->panelUrl ?? null);
    }

    /**
     * @param  list<LinkableRecordDTO>|array<string, LinkableRecordDTO>  $records
     * @return list<string>
     */
    private function ids(array $records): array
    {
        return array_values(array_map(static fn (LinkableRecordDTO $record): string => $record->id, $records));
    }

    private function gameMaster(UserModel $user, bool $namePublic = true): GameMasterModel
    {
        return GameMasterModel::create([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'first_name' => 'Nombre',
            'last_name' => 'Apellido',
            'role' => GameMasterRole::Main,
            'notify_by_email' => false,
            'is_name_public' => $namePublic,
        ]);
    }

    private function campaign(): CampaignModel
    {
        return CampaignModel::create([
            'id' => (string) Str::uuid(),
            'game_system_id' => $this->system->id,
            'created_by' => UserModel::factory()->create()->id,
            'title' => 'Campaña de ejemplo',
            'slug' => 'campana-'.Str::lower(Str::random(8)),
            'status' => 'active',
            'is_published' => true,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function table(array $attributes = []): GameTableModel
    {
        return GameTableModel::create(array_merge([
            'id' => (string) Str::uuid(),
            'game_system_id' => $this->system->id,
            'created_by' => UserModel::factory()->create()->id,
            'title' => 'Mesa de ejemplo',
            'slug' => 'mesa-'.Str::lower(Str::random(8)),
            'starts_at' => now()->addWeek(),
            'duration_minutes' => 240,
            'table_type' => 'one_shot',
            'table_format' => 'online',
            'status' => 'scheduled',
            'min_players' => 1,
            'max_players' => 5,
            'max_spectators' => 2,
            'language' => 'es',
            'registration_type' => 'everyone',
            'is_published' => true,
        ], $attributes));
    }
}
