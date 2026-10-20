<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Feature\Http\Controllers;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameMasterModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameSystemModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameTableModel;
use Tests\Support\Modules\ModuleTestCase;

/**
 * Editing a draft on the web keeps its directors: the edit page carries them (with their email and
 * phone, it is the author's own table) and saving them back keeps the same records. A row's `id`
 * only ever refers to a director without an account already on this table: it never links, nor
 * changes, another table's or another person's director record.
 */
final class FrontendDraftGameMastersTest extends ModuleTestCase
{
    protected ?string $moduleName = 'game-tables';

    protected bool $autoEnableModule = true;

    private UserModel $author;

    private GameSystemModel $gameSystem;

    private GameTableModel $table;

    private GameMasterModel $authorRecord;

    private GameMasterModel $jane;

    private GameMasterModel $richard;

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

        $this->table = $this->draftTable($this->author, 'mesa-del-autor');
        $this->authorRecord = $this->record([
            'user_id' => $this->author->id,
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
            'phone' => '600 000 001',
            'custom_title' => 'Narrador',
            'role' => 'main',
        ]);
        $this->jane = $this->record([
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'email' => 'jane@example.com',
            'phone' => '600 000 002',
            'custom_title' => 'Ayudante',
            'is_name_public' => false,
            'role' => 'co_gm',
        ]);
        $this->richard = $this->record([
            'first_name' => 'Richard',
            'last_name' => 'Roe',
            'email' => 'richard@example.com',
            'role' => 'co_gm',
        ]);
        $this->link($this->table, $this->authorRecord, 0);
        $this->link($this->table, $this->jane, 1);
        $this->link($this->table, $this->richard, 2);
    }

    public function test_the_edit_page_carries_the_main_director_and_the_co_directors(): void
    {
        $this->actingAs($this->author)
            ->get("/mesas/mis-mesas/{$this->table->slug}/editar")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('GameTables/Edit', false)
                ->where('gameMasters', [
                    [
                        'id' => $this->authorRecord->id,
                        'user_id' => $this->author->id,
                        'first_name' => 'John',
                        'last_name' => 'Doe',
                        'email' => 'john@example.com',
                        'phone' => '600 000 001',
                        'custom_title' => 'Narrador',
                        'is_name_public' => true,
                        'role' => 'main',
                    ],
                    [
                        'id' => $this->jane->id,
                        'user_id' => null,
                        'first_name' => 'Jane',
                        'last_name' => 'Doe',
                        'email' => 'jane@example.com',
                        'phone' => '600 000 002',
                        'custom_title' => 'Ayudante',
                        'is_name_public' => false,
                        'role' => 'co_gm',
                    ],
                    [
                        'id' => $this->richard->id,
                        'user_id' => null,
                        'first_name' => 'Richard',
                        'last_name' => 'Roe',
                        'email' => 'richard@example.com',
                        'phone' => '',
                        'custom_title' => '',
                        'is_name_public' => true,
                        'role' => 'co_gm',
                    ],
                ]));
    }

    public function test_the_edit_page_offers_the_author_as_main_director_when_the_table_has_none(): void
    {
        $this->table->gameMasters()->detach();
        // Without any record of the author: their account and the defaults
        $this->authorRecord->delete();

        $this->actingAs($this->author)
            ->get("/mesas/mis-mesas/{$this->table->slug}/editar")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('gameMasters', [[
                    'id' => null,
                    'user_id' => $this->author->id,
                    'first_name' => 'John',
                    'last_name' => 'Doe',
                    'email' => 'john@example.com',
                    'phone' => '',
                    'custom_title' => '',
                    'is_name_public' => true,
                    'role' => 'main',
                ]]));
    }

    public function test_the_edit_form_fills_the_directors_from_the_page(): void
    {
        $source = (string) file_get_contents(dirname(__DIR__, 4).'/resources/js/pages/GameTables/Edit.vue');

        $this->assertStringContainsString('gameMasters: EditableGameMaster[];', $source);
        $this->assertStringContainsString('game_masters: props.gameMasters.map(', $source);
        $this->assertStringNotContainsString('props.table.game_masters', $source);
    }

    public function test_saving_the_directors_unchanged_keeps_the_same_records_and_data(): void
    {
        $rows = $this->rowsOnTheEditPage();
        $before = GameMasterModel::query()->count();

        $this->save($rows);

        $this->assertSame(
            [$this->authorRecord->id, $this->jane->id, $this->richard->id],
            $this->linkedIds($this->table),
        );
        $this->assertSame($before, GameMasterModel::query()->count());

        $jane = $this->jane->refresh();
        $this->assertSame(['Jane', 'Doe', 'jane@example.com', '600 000 002', 'Ayudante', false, null], [
            $jane->first_name, $jane->last_name, $jane->email, $jane->phone, $jane->custom_title, $jane->is_name_public, $jane->user_id,
        ]);
        $authorRecord = $this->authorRecord->refresh();
        $this->assertSame(['600 000 001', 'Narrador', $this->author->id], [$authorRecord->phone, $authorRecord->custom_title, $authorRecord->user_id]);
    }

    public function test_a_co_director_changed_on_the_form_keeps_its_record(): void
    {
        $rows = $this->rowsOnTheEditPage();
        $rows[1]['phone'] = '600 000 003';

        $this->save($rows);

        $this->assertSame([$this->authorRecord->id, $this->jane->id, $this->richard->id], $this->linkedIds($this->table));
        $this->assertSame('600 000 003', $this->jane->refresh()->phone);
    }

    public function test_removing_one_co_director_removes_only_that_one(): void
    {
        $rows = $this->rowsOnTheEditPage();
        unset($rows[2]);

        $this->save(array_values($rows));

        $this->assertSame([$this->authorRecord->id, $this->jane->id], $this->linkedIds($this->table));
        $this->assertNull(GameMasterModel::query()->find($this->richard->id));
        $this->assertSame('600 000 002', $this->jane->refresh()->phone);
    }

    public function test_the_id_of_a_director_of_another_table_is_not_linked(): void
    {
        $richardUser = UserModel::factory()->create(['name' => 'Richard Roe', 'email' => 'richard.roe@example.com']);
        $otherTable = $this->draftTable($richardUser, 'mesa-ajena');
        $foreign = $this->record([
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'email' => 'jane.doe@example.com',
            'phone' => '600 000 020',
            'role' => 'co_gm',
        ]);
        $this->link($otherTable, $foreign, 1);

        $rows = $this->rowsOnTheEditPage();
        $rows[] = $this->coRow('Jane', 'Doe', 'otra@example.com', '600 999 999', id: $foreign->id);

        $this->save($rows, consent: true);

        $this->assertNotContains($foreign->id, $this->linkedIds($this->table));
        $foreign->refresh();
        $this->assertSame(['jane.doe@example.com', '600 000 020'], [$foreign->email, $foreign->phone]);
        $this->assertSame([$foreign->id], $this->linkedIds($otherTable));

        // The row is kept as a new director without an account
        $added = $this->table->gameMasters()->where('email', 'otra@example.com')->firstOrFail();
        $this->assertNull($added->user_id);
        $this->assertSame('600 999 999', $added->phone);
    }

    public function test_the_id_of_another_persons_director_record_is_never_linked_nor_changed(): void
    {
        $janeUser = UserModel::factory()->create(['name' => 'Jane Doe', 'email' => 'jane.account@example.com']);
        $janeRecord = $this->record([
            'user_id' => $janeUser->id,
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'email' => 'jane.account@example.com',
            'phone' => '600 000 010',
            'role' => 'co_gm',
        ]);
        $forged = [...$this->coRow('Jane', 'Doe', 'forged@example.com', '600 999 999', id: $janeRecord->id), 'user_id' => $janeUser->id];

        // On create
        $this->actingAs($this->author)
            ->post('/mesas/crear', [
                ...$this->tableData('Mesa nueva'),
                'gm_gdpr_consent' => true,
                'game_masters' => [$this->mainRow(), $forged],
            ])
            ->assertSessionHasNoErrors();
        $created = GameTableModel::query()->where('title', 'Mesa nueva')->firstOrFail();

        // On update
        $this->save([...$this->rowsOnTheEditPage(), $forged], consent: true);

        $janeRecord->refresh();
        $this->assertSame(
            ['Jane', 'Doe', 'jane.account@example.com', '600 000 010', $janeUser->id],
            [$janeRecord->first_name, $janeRecord->last_name, $janeRecord->email, $janeRecord->phone, $janeRecord->user_id],
        );
        foreach ([$created, $this->table] as $table) {
            $this->assertNotContains($janeRecord->id, $this->linkedIds($table));
            $this->assertNull($table->gameMasters()->where('email', 'forged@example.com')->firstOrFail()->user_id);
        }
    }

    public function test_directors_linked_from_the_panel_that_the_form_does_not_show_are_kept_as_they_are(): void
    {
        $janeUser = UserModel::factory()->create(['name' => 'Jane Doe', 'email' => 'jane.account@example.com']);
        $janeRecord = $this->record([
            'user_id' => $janeUser->id,
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'email' => 'jane.account@example.com',
            'phone' => '600 000 010',
            'role' => 'co_gm',
        ]);
        $mary = $this->record([
            'first_name' => 'Mary',
            'last_name' => 'Major',
            'email' => 'mary@example.com',
            'phone' => '600 000 030',
            'role' => 'main',
        ]);
        $this->link($this->table, $janeRecord, 3);
        $this->link($this->table, $mary, 4);

        // Another account's contact details are not the author's to see or change, and the form
        // only edits the author as main director
        $rows = $this->rowsOnTheEditPage();
        $this->assertSame([$this->authorRecord->id, $this->jane->id, $this->richard->id], array_column($rows, 'id'));

        $this->save($rows);

        $this->assertSame(
            [$this->authorRecord->id, $this->jane->id, $this->richard->id, $janeRecord->id, $mary->id],
            $this->linkedIds($this->table),
        );
        $janeRecord->refresh();
        $this->assertSame(['jane.account@example.com', '600 000 010', $janeUser->id], [$janeRecord->email, $janeRecord->phone, $janeRecord->user_id]);
        $mary->refresh();
        $this->assertSame(['mary@example.com', '600 000 030', null, 'main'], [$mary->email, $mary->phone, $mary->user_id, $mary->role->value]);
    }

    public function test_the_main_row_never_takes_over_a_co_director_record(): void
    {
        $rows = $this->rowsOnTheEditPage();
        $rows[0]['id'] = $this->jane->id;

        $this->save($rows);

        $this->jane->refresh();
        $this->assertNull($this->jane->user_id);
        $this->assertSame('co_gm', $this->jane->role->value);
        $this->assertSame($this->author->id, $this->table->gameMasters()->where('role', 'main')->firstOrFail()->user_id);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function rowsOnTheEditPage(): array
    {
        $response = $this->actingAs($this->author)->get("/mesas/mis-mesas/{$this->table->slug}/editar")->assertOk();

        /** @var list<array<string, mixed>> $rows */
        $rows = $response->viewData('page')['props']['gameMasters'];

        return $rows;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  bool  $consent  the co-directors' consent, asked when a row adds a co-director
     */
    private function save(array $rows, bool $consent = false): void
    {
        $this->actingAs($this->author)
            ->put("/mesas/mis-mesas/{$this->table->slug}", [
                ...$this->tableData('Mesa corregida'),
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
     * @return array<string, mixed>
     */
    private function mainRow(): array
    {
        return [
            'user_id' => $this->author->id,
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
            'phone' => '',
            'is_name_public' => true,
            'role' => 'main',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function coRow(string $firstName, string $lastName, string $email, string $phone, ?string $id = null): array
    {
        return [
            ...($id !== null ? ['id' => $id] : []),
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $email,
            'phone' => $phone,
            'is_name_public' => true,
            'role' => 'co_gm',
        ];
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

    private function draftTable(UserModel $author, string $slug): GameTableModel
    {
        return GameTableModel::create([
            'id' => (string) Str::uuid(),
            'game_system_id' => $this->gameSystem->id,
            'created_by' => $author->id,
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
