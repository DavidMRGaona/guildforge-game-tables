<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Feature\Http\Controllers;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\ContentWarningModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameMasterModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameSystemModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameTableModel;
use Tests\Support\Modules\ModuleTestCase;

/**
 * What a person types on the web table form reaches the table: every field the form sends is one
 * the server validates (anything else is dropped by `validated()`), the content warnings ticked on
 * create and edit are attached, and the phone of each director is stored.
 */
final class FrontendTableFormDataTest extends ModuleTestCase
{
    private const PAGES = ['pages/GameTables/Create.vue', 'pages/GameTables/Edit.vue'];

    // Sent by the form but checked only in the browser
    private const CLIENT_ONLY_FIELDS = ['gm_gdpr_consent'];

    protected ?string $moduleName = 'game-tables';

    protected bool $autoEnableModule = true;

    private UserModel $author;

    private GameSystemModel $gameSystem;

    private ContentWarningModel $violence;

    private ContentWarningModel $horror;

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
        $this->violence = $this->warning('violencia', 'Violencia');
        $this->horror = $this->warning('terror', 'Terror');
    }

    public function test_the_table_forms_send_the_content_warnings_under_the_key_the_server_reads(): void
    {
        foreach (self::PAGES as $page) {
            $keys = $this->formKeys($page);

            $this->assertContains('content_warning_ids', $keys, $page);
            $this->assertNotContains('content_warnings', $keys, $page);
            $this->assertStringContainsString('v-model="form.content_warning_ids"', $this->source('resources/js/'.$page), $page);
        }

        $this->assertStringContainsString(
            'content_warning_ids: props.table.content_warnings,',
            $this->source('resources/js/pages/GameTables/Edit.vue'),
        );
    }

    public function test_every_field_the_table_forms_send_is_one_the_server_keeps(): void
    {
        $request = $this->source('src/Http/Requests/FrontendCreateGameTableRequest.php');
        preg_match_all("/^            '([a-z_]+)(?:\.[a-z_*.]+)?' => \[/m", $request, $matches);
        $fields = array_values(array_unique($matches[1]));

        foreach (self::PAGES as $page) {
            foreach (array_diff($this->formKeys($page), self::CLIENT_ONLY_FIELDS) as $key) {
                $this->assertContains($key, $fields, "{$page}: {$key} is sent but never validated");
            }
        }

        // Each director's row: the inputs of GameMasterSection
        foreach (['first_name', 'last_name', 'email', 'phone', 'custom_title', 'is_name_public', 'role', 'user_id'] as $field) {
            $this->assertStringContainsString("'game_masters.*.{$field}' => [", $request, $field);
        }
    }

    public function test_the_content_warnings_ticked_on_create_reach_the_table(): void
    {
        $this->actingAs($this->author)
            ->post('/mesas/crear', [
                ...$this->tableData('Mesa con avisos'),
                'content_warning_ids' => [$this->violence->id, $this->horror->id],
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('gametables.my-tables'));

        $table = GameTableModel::query()->where('title', 'Mesa con avisos')->firstOrFail();
        $this->assertEqualsCanonicalizing([$this->violence->id, $this->horror->id], $table->contentWarnings()->pluck('id')->all());
    }

    public function test_the_edit_form_shows_the_stored_content_warnings_and_saves_the_ticked_ones(): void
    {
        $table = $this->draftTable();
        $table->contentWarnings()->attach($this->violence->id);

        $this->actingAs($this->author)
            ->get("/mesas/mis-mesas/{$table->slug}/editar")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('GameTables/Edit', false)
                ->where('table.content_warnings', [$this->violence->id]));

        $this->actingAs($this->author)
            ->put("/mesas/mis-mesas/{$table->slug}", [
                ...$this->tableData('Mesa corregida'),
                'content_warning_ids' => [$this->violence->id, $this->horror->id],
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('gametables.my-tables'));

        $this->assertEqualsCanonicalizing([$this->violence->id, $this->horror->id], $table->contentWarnings()->pluck('id')->all());
    }

    public function test_the_phone_of_each_director_is_kept_on_create(): void
    {
        $this->actingAs($this->author)
            ->post('/mesas/crear', [
                ...$this->tableData('Mesa con teléfonos'),
                'game_masters' => [
                    $this->mainGameMaster('600 000 001'),
                    $this->coGameMaster('Jane', 'Doe', 'jane@example.com', '600 000 002'),
                ],
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('gametables.my-tables'));

        $this->assertSame('600 000 001', GameMasterModel::query()->where('user_id', $this->author->id)->firstOrFail()->phone);
        $this->assertSame('600 000 002', GameMasterModel::query()->where('email', 'jane@example.com')->firstOrFail()->phone);
    }

    public function test_the_phone_of_a_director_who_already_directs_is_kept(): void
    {
        // The author's director record from an earlier table, without a phone
        $gameMaster = GameMasterModel::create([
            'id' => (string) Str::uuid(),
            'user_id' => $this->author->id,
            'first_name' => 'John',
            'last_name' => 'Doe',
            'role' => 'main',
            'notify_by_email' => true,
            'is_name_public' => true,
        ]);

        $this->actingAs($this->author)
            ->post('/mesas/crear', [
                ...$this->tableData('Segunda mesa'),
                'game_masters' => [$this->mainGameMaster('600 000 003')],
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('600 000 003', $gameMaster->refresh()->phone);

        // A later form left blank keeps the stored phone
        $this->actingAs($this->author)
            ->post('/mesas/crear', [
                ...$this->tableData('Tercera mesa'),
                'game_masters' => [$this->mainGameMaster('')],
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('600 000 003', $gameMaster->refresh()->phone);
    }

    public function test_the_phone_of_each_director_is_kept_on_edit(): void
    {
        $table = $this->draftTable();

        $this->actingAs($this->author)
            ->put("/mesas/mis-mesas/{$table->slug}", [
                ...$this->tableData('Mesa corregida'),
                'game_masters' => [
                    $this->mainGameMaster('600 000 004'),
                    $this->coGameMaster('Richard', 'Roe', 'richard@example.com', '600 000 005'),
                ],
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('gametables.my-tables'));

        $this->assertSame('600 000 004', GameMasterModel::query()->where('user_id', $this->author->id)->firstOrFail()->phone);
        $this->assertSame('600 000 005', GameMasterModel::query()->where('email', 'richard@example.com')->firstOrFail()->phone);
    }

    public function test_a_phone_longer_than_thirty_characters_is_refused(): void
    {
        $this->actingAs($this->author)
            ->post('/mesas/crear', [
                ...$this->tableData('Mesa con teléfono largo'),
                'game_masters' => [
                    $this->mainGameMaster(''),
                    $this->coGameMaster('Mary', 'Major', 'mary@example.com', str_repeat('6', 31)),
                ],
            ])
            ->assertSessionHasErrors('game_masters.1.phone');

        $this->assertNull(GameTableModel::query()->where('title', 'Mesa con teléfono largo')->first());
    }

    public function test_a_co_director_row_with_another_users_id_does_not_reach_that_users_record(): void
    {
        $jane = UserModel::factory()->create(['name' => 'Jane Doe', 'email' => 'jane@example.com']);
        $janeRecord = $this->accountGameMaster($jane, 'co_gm', 'Jane', '600 000 010');
        $forgedRow = [...$this->coGameMaster('Jane', 'Doe', 'jane@example.com', '600 999 999'), 'user_id' => $jane->id];

        $this->actingAs($this->author)
            ->post('/mesas/crear', [
                ...$this->tableData('Mesa con fila ajena'),
                'game_masters' => [$this->mainGameMaster(''), $forgedRow],
            ])
            ->assertSessionHasNoErrors();
        $created = GameTableModel::query()->where('title', 'Mesa con fila ajena')->firstOrFail();

        $draft = $this->draftTable();
        $this->actingAs($this->author)
            ->put("/mesas/mis-mesas/{$draft->slug}", [
                ...$this->tableData('Mesa corregida'),
                'game_masters' => [$this->mainGameMaster(''), $forgedRow],
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('600 000 010', $janeRecord->refresh()->phone);
        foreach ([$created, $draft] as $table) {
            $this->assertNotContains($janeRecord->id, $table->gameMasters()->pluck('gametables_game_masters.id')->all());
            // The row stays, as a co-director without an account
            $coDirector = $table->gameMasters()->where('role', 'co_gm')->firstOrFail();
            $this->assertNull($coDirector->user_id);
            $this->assertSame('jane@example.com', $coDirector->email);
        }
    }

    public function test_the_main_director_row_is_always_the_author(): void
    {
        $jane = UserModel::factory()->create(['name' => 'Jane Doe', 'email' => 'jane@example.com']);
        $janeRecord = $this->accountGameMaster($jane, 'main', 'Jane', '600 000 011');

        $this->actingAs($this->author)
            ->post('/mesas/crear', [
                ...$this->tableData('Mesa con director ajeno'),
                'game_masters' => [[...$this->mainGameMaster('600 000 012'), 'user_id' => $jane->id]],
            ])
            ->assertSessionHasNoErrors();

        $table = GameTableModel::query()->where('title', 'Mesa con director ajeno')->firstOrFail();
        $main = $table->gameMasters()->where('role', 'main')->firstOrFail();
        $this->assertSame($this->author->id, $main->user_id);
        $this->assertSame('600 000 012', $main->phone);
        $this->assertSame('600 000 011', $janeRecord->refresh()->phone);
        $this->assertNotContains($janeRecord->id, $table->gameMasters()->pluck('gametables_game_masters.id')->all());
    }

    public function test_the_authors_own_record_still_takes_the_phone_even_without_an_id_in_the_row(): void
    {
        $record = $this->accountGameMaster($this->author, 'main', 'John', null);

        $this->actingAs($this->author)
            ->post('/mesas/crear', [
                ...$this->tableData('Mesa sin id'),
                'game_masters' => [[...$this->mainGameMaster('600 000 013'), 'user_id' => null]],
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('600 000 013', $record->refresh()->phone);
        $table = GameTableModel::query()->where('title', 'Mesa sin id')->firstOrFail();
        $this->assertContains($record->id, $table->gameMasters()->pluck('gametables_game_masters.id')->all());
    }

    private function accountGameMaster(UserModel $user, string $role, string $firstName, ?string $phone): GameMasterModel
    {
        return GameMasterModel::create([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'first_name' => $firstName,
            'last_name' => 'Doe',
            'phone' => $phone,
            'role' => $role,
            'notify_by_email' => true,
            'is_name_public' => true,
        ]);
    }

    private function source(string $file): string
    {
        return (string) file_get_contents(dirname(__DIR__, 4).'/'.$file);
    }

    /**
     * @return list<string> the top-level keys of the page's `useForm({...})`
     */
    private function formKeys(string $page): array
    {
        $found = preg_match('/^const form = useForm\(\{\n(.*?)^\}\);/ms', $this->source('resources/js/'.$page), $match);
        $this->assertSame(1, $found, "useForm not found in {$page}");

        preg_match_all('/^    ([a-z_]+):/m', $match[1], $keys);

        return $keys[1];
    }

    /**
     * @return array<string, mixed>
     */
    private function mainGameMaster(string $phone): array
    {
        return [
            'user_id' => $this->author->id,
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
            'phone' => $phone,
            'is_name_public' => true,
            'role' => 'main',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function coGameMaster(string $firstName, string $lastName, string $email, string $phone): array
    {
        return [
            'user_id' => null,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $email,
            'phone' => $phone,
            'is_name_public' => true,
            'role' => 'co_gm',
        ];
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

    private function draftTable(): GameTableModel
    {
        return GameTableModel::create([
            'id' => (string) Str::uuid(),
            'game_system_id' => $this->gameSystem->id,
            'created_by' => $this->author->id,
            'title' => 'Mesa del autor',
            'slug' => 'mesa-del-autor',
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

    private function warning(string $slug, string $label): ContentWarningModel
    {
        return ContentWarningModel::create([
            'id' => (string) Str::uuid(),
            'name' => $slug,
            'slug' => $slug,
            'label' => $label,
            'severity' => 'mild',
            'is_active' => true,
        ]);
    }
}
