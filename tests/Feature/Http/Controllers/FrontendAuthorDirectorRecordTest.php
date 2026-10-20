<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Feature\Http\Controllers;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\CampaignModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameMasterModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameSystemModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameTableModel;
use Tests\Support\Modules\ModuleTestCase;

/**
 * The author's custom title, phone and «Mostrar nombre públicamente» live on their own director
 * record, shared by every table they direct: the web form saves them there (create and edit) and
 * pre-fills them from it. The directors' rows always hold exactly one main director, the author.
 */
final class FrontendAuthorDirectorRecordTest extends ModuleTestCase
{
    protected ?string $moduleName = 'game-tables';

    protected bool $autoEnableModule = true;

    private UserModel $author;

    private GameSystemModel $gameSystem;

    private GameTableModel $table;

    private GameMasterModel $authorRecord;

    private GameMasterModel $jane;

    protected function setUp(): void
    {
        parent::setUp();

        app('router')->getRoutes()->refreshNameLookups();
        config()->set('modules.settings.game-tables', [
            'frontend_creation' => [
                'enabled' => true,
                'access_level' => 'registered',
                'allowed_content' => 'both',
                'publication' => ['mode' => 'approval'],
            ],
        ]);

        $this->author = UserModel::factory()->create(['name' => 'John Doe', 'email' => 'john@example.com']);
        $this->gameSystem = GameSystemModel::create([
            'id' => (string) Str::uuid(),
            'name' => 'Sistema de prueba',
            'slug' => 'sistema-de-prueba',
            'is_active' => true,
        ]);

        $this->table = $this->draftTable('mesa-del-autor');
        $this->authorRecord = $this->record([
            'user_id' => $this->author->id,
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
            'phone' => '600 000 001',
            'custom_title' => 'Narrador',
            'is_name_public' => true,
            'role' => 'main',
        ]);
        $this->jane = $this->record([
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'email' => 'jane@example.com',
            'role' => 'co_gm',
        ]);
        $this->link($this->table, $this->authorRecord, 0);
        $this->link($this->table, $this->jane, 1);
    }

    public function test_switching_off_the_name_visibility_on_the_web_hides_the_authors_name(): void
    {
        $rows = $this->rowsOnTheEditPage($this->table);
        $rows[0]['is_name_public'] = false;

        $this->save($this->table, $rows);

        $this->assertFalse($this->authorRecord->refresh()->is_name_public);
        $this->assertSame([$this->authorRecord->id, $this->jane->id], $this->linkedIds($this->table));
    }

    public function test_the_custom_title_and_phone_typed_on_edit_are_saved_on_the_authors_record(): void
    {
        $rows = $this->rowsOnTheEditPage($this->table);
        $rows[0]['custom_title'] = 'Máster';
        $rows[0]['phone'] = '600 111 222';

        $this->save($this->table, $rows);

        $record = $this->authorRecord->refresh();
        $this->assertSame(['Máster', '600 111 222'], [$record->custom_title, $record->phone]);
    }

    public function test_a_cleared_custom_title_and_phone_are_cleared_on_the_record(): void
    {
        $rows = $this->rowsOnTheEditPage($this->table);
        $rows[0]['custom_title'] = '';
        $rows[0]['phone'] = '';

        $this->save($this->table, $rows);

        $record = $this->authorRecord->refresh();
        $this->assertSame([null, null], [$record->custom_title, $record->phone]);
    }

    public function test_the_values_typed_on_create_are_saved_on_the_authors_record(): void
    {
        $this->actingAs($this->author)
            ->post('/mesas/crear', [
                ...$this->tableData('Mesa nueva'),
                'game_masters' => [[
                    'user_id' => $this->author->id,
                    'first_name' => 'John',
                    'last_name' => 'Doe',
                    'email' => 'john@example.com',
                    'phone' => '600 333 444',
                    'custom_title' => 'Cuentacuentos',
                    'is_name_public' => false,
                    'role' => 'main',
                ]],
            ])
            ->assertSessionHasNoErrors();

        $created = GameTableModel::query()->where('title', 'Mesa nueva')->firstOrFail();
        $this->assertSame([$this->authorRecord->id], $this->linkedIds($created));
        $record = $this->authorRecord->refresh();
        $this->assertSame(['Cuentacuentos', '600 333 444', false], [$record->custom_title, $record->phone, $record->is_name_public]);
    }

    public function test_another_table_of_the_author_shows_the_same_values(): void
    {
        $second = $this->draftTable('otra-mesa-del-autor');
        $this->link($second, $this->authorRecord, 0);

        $rows = $this->rowsOnTheEditPage($this->table);
        $rows[0]['custom_title'] = 'Máster';
        $rows[0]['phone'] = '600 111 222';
        $rows[0]['is_name_public'] = false;
        $this->save($this->table, $rows);

        $main = $this->rowsOnTheEditPage($second)[0];
        $this->assertSame(['Máster', '600 111 222', false], [$main['custom_title'], $main['phone'], $main['is_name_public']]);
    }

    public function test_the_create_page_pre_fills_the_authors_record(): void
    {
        $this->actingAs($this->author)
            ->get('/mesas/crear')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('GameTables/Create', false)
                ->where('currentUser.phone', '600 000 001')
                ->where('currentUser.custom_title', 'Narrador')
                ->where('currentUser.is_name_public', true));
    }

    public function test_the_create_page_of_an_author_without_a_record_offers_the_defaults(): void
    {
        $mary = UserModel::factory()->create(['name' => 'Mary Major', 'email' => 'mary@example.com']);

        $this->actingAs($mary)
            ->get('/mesas/crear')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('currentUser.phone', '')
                ->where('currentUser.custom_title', '')
                ->where('currentUser.is_name_public', true));
    }

    public function test_the_edit_page_of_a_table_without_the_authors_record_pre_fills_it(): void
    {
        $this->table->gameMasters()->detach($this->authorRecord->id);

        $main = $this->rowsOnTheEditPage($this->table)[0];

        $this->assertSame(
            [null, $this->author->id, '600 000 001', 'Narrador', true, 'main'],
            [$main['id'], $main['user_id'], $main['phone'], $main['custom_title'], $main['is_name_public'], $main['role']],
        );
    }

    public function test_another_accounts_main_record_is_left_untouched(): void
    {
        $janeUser = UserModel::factory()->create(['name' => 'Jane Doe', 'email' => 'jane.account@example.com']);
        $janeRecord = $this->record([
            'user_id' => $janeUser->id,
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'email' => 'jane.account@example.com',
            'phone' => '600 000 010',
            'custom_title' => 'Directora',
            'is_name_public' => true,
            'role' => 'main',
        ]);
        // Linked by staff: the form does not show it
        $this->link($this->table, $janeRecord, 2);

        $rows = $this->rowsOnTheEditPage($this->table);
        $rows[0]['custom_title'] = 'Máster';
        $rows[0]['phone'] = '600 111 222';
        $rows[0]['is_name_public'] = false;
        // A forged row naming Jane's record goes nowhere near it
        $rows[] = [...$rows[0], 'id' => $janeRecord->id, 'user_id' => $janeUser->id, 'first_name' => 'Jane', 'email' => 'forged@example.com', 'role' => 'main'];
        $this->save($this->table, $rows, consent: true);

        $janeRecord->refresh();
        $this->assertSame(
            ['600 000 010', 'Directora', true, $janeUser->id, 'main'],
            [$janeRecord->phone, $janeRecord->custom_title, $janeRecord->is_name_public, $janeRecord->user_id, $janeRecord->role->value],
        );
        $this->assertSame('Máster', $this->authorRecord->refresh()->custom_title);
    }

    public function test_a_payload_without_a_main_row_keeps_the_author_as_main_director(): void
    {
        $rows = $this->rowsOnTheEditPage($this->table);
        array_shift($rows);

        $this->save($this->table, $rows);

        $this->assertSame([$this->authorRecord->id, $this->jane->id], $this->linkedIds($this->table));
        $this->assertSame('main', $this->authorRecord->refresh()->role->value);
        $this->assertSame(['co_gm', null], [$this->jane->refresh()->role->value, $this->jane->user_id]);
        // Nothing the payload did not carry changed on the author's record
        $this->assertSame(['600 000 001', 'Narrador', true], [$this->authorRecord->phone, $this->authorRecord->custom_title, $this->authorRecord->is_name_public]);
    }

    public function test_a_create_payload_without_a_main_row_attaches_the_author_as_main_director(): void
    {
        $this->actingAs($this->author)
            ->post('/mesas/crear', [
                ...$this->tableData('Mesa sin principal'),
                'gm_gdpr_consent' => true,
                'game_masters' => [[
                    'first_name' => 'Richard',
                    'last_name' => 'Roe',
                    'email' => 'richard@example.com',
                    'is_name_public' => true,
                    'role' => 'co_gm',
                ]],
            ])
            ->assertSessionHasNoErrors();

        $created = GameTableModel::query()->where('title', 'Mesa sin principal')->firstOrFail();
        $mains = $created->gameMasters()->where('role', 'main')->get();
        $this->assertSame([$this->authorRecord->id], $mains->modelKeys());
        $this->assertSame(1, $created->gameMasters()->where('role', 'co_gm')->where('email', 'richard@example.com')->count());
    }

    public function test_a_second_main_row_becomes_a_co_director(): void
    {
        $rows = $this->rowsOnTheEditPage($this->table);
        $rows[] = [
            'first_name' => 'Richard',
            'last_name' => 'Roe',
            'email' => 'richard@example.com',
            'is_name_public' => true,
            'role' => 'main',
        ];

        $this->save($this->table, $rows, consent: true);

        $richard = $this->table->gameMasters()->where('email', 'richard@example.com')->firstOrFail();
        $this->assertSame(['co_gm', null], [$richard->role->value, $richard->user_id]);
        $this->assertSame([$this->authorRecord->id], $this->table->gameMasters()->where('role', 'main')->get()->modelKeys());
    }

    public function test_a_campaign_session_keeps_the_campaign_directors_as_they_are(): void
    {
        $maryUser = UserModel::factory()->create(['name' => 'Mary Major', 'email' => 'mary.account@example.com']);
        $mary = $this->record([
            'user_id' => $maryUser->id,
            'first_name' => 'Mary',
            'last_name' => 'Major',
            'email' => 'mary.account@example.com',
            'role' => 'co_gm',
        ]);
        $campaign = CampaignModel::create([
            'id' => (string) Str::uuid(),
            'game_system_id' => $this->gameSystem->id,
            'created_by' => $this->author->id,
            'title' => 'Campaña del autor',
            'slug' => 'campana-del-autor',
            'frequency' => 'weekly',
            'status' => 'recruiting',
            'max_players' => 5,
            'is_published' => true,
            'frontend_creation_status' => 'approved',
        ]);
        // An inherited account director, and the author's main record shared with the campaign
        $campaign->gameMasters()->attach($this->authorRecord->id, ['sort_order' => 0]);
        $campaign->gameMasters()->attach($mary->id, ['sort_order' => 1]);
        $this->table->update(['table_type' => 'campaign_session', 'campaign_id' => $campaign->id]);

        $effectiveBefore = $this->effectiveIds($this->table);
        $this->assertContains($mary->id, $effectiveBefore);

        $rows = $this->rowsOnTheEditPage($this->table);
        $this->assertSame([$this->authorRecord->id, $this->jane->id], array_column($rows, 'id'));

        $this->save($this->table, $rows, data: ['table_type' => 'campaign_session', 'campaign_id' => $campaign->id]);

        $this->assertSame($effectiveBefore, $this->effectiveIds($this->table->refresh()));
        $this->assertFalse(
            $this->table->gameMasters()->where('gametables_game_masters.id', $mary->id)->wherePivot('excluded', true)->exists(),
        );
        $this->assertSame([$this->authorRecord->id, $mary->id], $campaign->gameMasters()->pluck('gametables_game_masters.id')->all());
    }

    /**
     * @return list<string>
     */
    private function effectiveIds(GameTableModel $table): array
    {
        $ids = $table->effective_game_masters->modelKeys();
        sort($ids);

        return $ids;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function rowsOnTheEditPage(GameTableModel $table): array
    {
        $response = $this->actingAs($this->author)->get("/mesas/mis-mesas/{$table->slug}/editar")->assertOk();

        /** @var list<array<string, mixed>> $rows */
        $rows = $response->viewData('page')['props']['gameMasters'];

        return $rows;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  array<string, mixed>  $data
     */
    private function save(GameTableModel $table, array $rows, bool $consent = false, array $data = []): void
    {
        $this->actingAs($this->author)
            ->put("/mesas/mis-mesas/{$table->slug}", [
                ...$this->tableData('Mesa corregida'),
                ...$data,
                'gm_gdpr_consent' => $consent,
                'game_masters' => $rows,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('gametables.my-tables'));
    }

    /**
     * @return list<string> the directors linked to the table, in their order
     */
    private function linkedIds(GameTableModel $table): array
    {
        return $table->gameMasters()->pluck('gametables_game_masters.id')->values()->all();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function record(array $attributes): GameMasterModel
    {
        return GameMasterModel::create([
            'id' => (string) Str::uuid(),
            'notify_by_email' => true,
            'is_name_public' => true,
            ...$attributes,
        ]);
    }

    private function link(GameTableModel $table, GameMasterModel $gameMaster, int $order): void
    {
        $table->gameMasters()->attach($gameMaster->id, ['source' => 'local', 'excluded' => false, 'sort_order' => $order]);
    }

    /**
     * @return array<string, mixed>
     */
    private function tableData(string $title): array
    {
        return [
            'game_system_id' => $this->gameSystem->id,
            'title' => $title,
            'starts_at' => now()->addWeeks(2)->toIso8601String(),
            'duration_minutes' => 240,
            'table_type' => 'one_shot',
            'table_format' => 'in_person',
            'min_players' => 2,
            'max_players' => 5,
            'language' => 'es',
            'experience_level' => 'none',
            'character_creation' => 'pre_generated',
        ];
    }

    private function draftTable(string $slug): GameTableModel
    {
        return GameTableModel::create([
            'id' => (string) Str::uuid(),
            'game_system_id' => $this->gameSystem->id,
            'created_by' => $this->author->id,
            'title' => 'Mesa del autor',
            'slug' => $slug,
            'starts_at' => now()->addWeek(),
            'duration_minutes' => 240,
            'table_type' => 'one_shot',
            'table_format' => 'in_person',
            'status' => 'draft',
            'min_players' => 1,
            'max_players' => 5,
            'language' => 'es',
            'registration_type' => 'everyone',
            'auto_confirm' => true,
            'is_published' => false,
            'frontend_creation_status' => 'draft',
        ]);
    }
}
