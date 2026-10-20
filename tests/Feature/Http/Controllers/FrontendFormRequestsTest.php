<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Feature\Http\Controllers;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Modules\GameTables\Http\Requests\FrontendCreateCampaignRequest;
use Modules\GameTables\Http\Requests\FrontendCreateGameTableRequest;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\CampaignModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameMasterModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameSystemModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameTableModel;
use Tests\Support\Modules\ModuleTestCase;

/**
 * The web table and campaign forms: their messages read in Spanish with the form's labels, the
 * co-directors' consent is asked when a new co-director is added, a malformed campaign id is not
 * found, and an author whose item is locked gets the reason before any validation error.
 */
final class FrontendFormRequestsTest extends ModuleTestCase
{
    protected ?string $moduleName = 'game-tables';

    protected bool $autoEnableModule = true;

    private const CONSENT_MESSAGE = 'Confirma que tienes el consentimiento de los co-directores.';

    private UserModel $author;

    private GameSystemModel $gameSystem;

    protected function setUp(): void
    {
        parent::setUp();

        app('router')->getRoutes()->refreshNameLookups();
        app()->setLocale('es');
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
    }

    // Item: malformed campaign ids

    public function test_the_author_routes_of_a_campaign_only_match_a_uuid(): void
    {
        foreach (['campaigns.frontend-edit', 'campaigns.frontend-update', 'campaigns.submit-review', 'campaigns.frontend-destroy'] as $name) {
            $route = Route::getRoutes()->getByName($name);
            $this->assertNotNull($route, $name);
            $this->assertArrayHasKey('id', $route->wheres, $name);
        }
    }

    public function test_a_malformed_campaign_id_is_not_found(): void
    {
        $this->actingAs($this->author);

        $this->get('/campanas/mis-campanas/not-a-uuid/editar')->assertNotFound();
        $this->put('/campanas/mis-campanas/not-a-uuid', $this->campaignData())->assertNotFound();
        $this->post('/campanas/mis-campanas/not-a-uuid/enviar-revision')->assertNotFound();
        $this->delete('/campanas/mis-campanas/not-a-uuid')->assertNotFound();
    }

    // Item: Spanish messages with the form's labels

    public function test_a_missing_game_system_reads_with_its_label(): void
    {
        $this->actingAs($this->author)
            ->post('/mesas/crear', [...$this->tableData('Mesa nueva'), 'game_system_id' => null])
            ->assertSessionHasErrors(['game_system_id' => 'El campo sistema de juego es obligatorio.']);

        $this->actingAs($this->author)
            ->post('/campanas/crear', [...$this->campaignData(), 'game_system_id' => null])
            ->assertSessionHasErrors(['game_system_id' => 'El campo sistema de juego es obligatorio.']);
    }

    public function test_a_co_director_without_email_reads_in_spanish(): void
    {
        $this->actingAs($this->author)
            ->post('/mesas/crear', [
                ...$this->tableData('Mesa nueva'),
                'gm_gdpr_consent' => true,
                'game_masters' => [$this->mainRow(), ['first_name' => 'Jane', 'email' => '', 'is_name_public' => true, 'role' => 'co_gm']],
            ])
            ->assertSessionHasErrors(['game_masters.1.email' => 'El email del co-director es obligatorio.']);
    }

    public function test_a_co_director_without_name_reads_in_spanish(): void
    {
        $this->actingAs($this->author)
            ->post('/mesas/crear', [
                ...$this->tableData('Mesa nueva'),
                'gm_gdpr_consent' => true,
                'game_masters' => [$this->mainRow(), ['first_name' => '', 'email' => 'jane@example.com', 'is_name_public' => true, 'role' => 'co_gm']],
            ])
            ->assertSessionHasErrors(['game_masters.1.first_name' => 'El nombre del co-director es obligatorio.']);
    }

    public function test_a_co_director_phone_too_long_reads_with_its_label(): void
    {
        $this->actingAs($this->author)
            ->post('/mesas/crear', [
                ...$this->tableData('Mesa nueva'),
                'gm_gdpr_consent' => true,
                'game_masters' => [$this->mainRow(), [...$this->coRow('Jane', 'jane@example.com'), 'phone' => str_repeat('6', 31)]],
            ])
            ->assertSessionHasErrors(['game_masters.1.phone' => 'El campo teléfono del director no puede tener más de 30 caracteres.']);
    }

    public function test_every_rule_of_both_requests_has_a_label_in_spanish_and_english(): void
    {
        foreach ([FrontendCreateGameTableRequest::class, FrontendCreateCampaignRequest::class] as $class) {
            $request = new $class;
            $rules = array_keys($this->rulesOf($request));

            foreach (['es', 'en'] as $locale) {
                app()->setLocale($locale);
                $attributes = $request->attributes();

                foreach ($rules as $key) {
                    $this->assertArrayHasKey($key, $attributes, "{$class} {$locale}: {$key}");
                    $this->assertStringNotContainsString('game-tables::', $attributes[$key], "{$class} {$locale}: {$key}");
                    $this->assertStringNotContainsString('_', $attributes[$key], "{$class} {$locale}: {$key}");
                }
            }
        }
        app()->setLocale('es');
    }

    // Item: the co-directors' consent

    public function test_a_new_co_director_without_consent_is_refused(): void
    {
        $this->actingAs($this->author)
            ->post('/mesas/crear', [
                ...$this->tableData('Mesa nueva'),
                'gm_gdpr_consent' => false,
                'game_masters' => [$this->mainRow(), $this->coRow('Jane', 'jane@example.com')],
            ])
            ->assertSessionHasErrors(['gm_gdpr_consent' => self::CONSENT_MESSAGE]);

        $this->assertFalse(GameTableModel::query()->where('title', 'Mesa nueva')->exists());
    }

    public function test_a_table_without_co_directors_needs_no_consent(): void
    {
        $this->actingAs($this->author)
            ->post('/mesas/crear', [...$this->tableData('Mesa nueva'), 'game_masters' => [$this->mainRow()]])
            ->assertSessionHasNoErrors();
    }

    public function test_editing_a_table_whose_co_directors_were_already_there_needs_no_consent(): void
    {
        [$table, $jane] = $this->draftWithCoDirector();

        $this->actingAs($this->author)
            ->put("/mesas/mis-mesas/{$table->slug}", [
                ...$this->tableData('Mesa corregida'),
                'gm_gdpr_consent' => false,
                'game_masters' => [$this->mainRow(), [...$this->coRow('Jane', 'jane@example.com'), 'id' => $jane->id]],
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('gametables.my-tables'));

        $this->assertSame('Mesa corregida', $table->refresh()->title);
    }

    public function test_editing_a_table_to_add_a_co_director_asks_for_consent(): void
    {
        [$table, $jane] = $this->draftWithCoDirector();

        $this->actingAs($this->author)
            ->put("/mesas/mis-mesas/{$table->slug}", [
                ...$this->tableData('Mesa corregida'),
                'game_masters' => [
                    $this->mainRow(),
                    [...$this->coRow('Jane', 'jane@example.com'), 'id' => $jane->id],
                    $this->coRow('Richard', 'richard@example.com'),
                ],
            ])
            ->assertSessionHasErrors(['gm_gdpr_consent' => self::CONSENT_MESSAGE]);
    }

    public function test_an_id_that_is_not_a_kept_co_director_counts_as_a_new_one(): void
    {
        [$table] = $this->draftWithCoDirector();
        $foreign = GameMasterModel::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Mary',
            'email' => 'mary@example.com',
            'role' => 'co_gm',
            'notify_by_email' => true,
            'is_name_public' => true,
        ]);

        $this->actingAs($this->author)
            ->put("/mesas/mis-mesas/{$table->slug}", [
                ...$this->tableData('Mesa corregida'),
                'game_masters' => [$this->mainRow(), [...$this->coRow('Mary', 'mary@example.com'), 'id' => $foreign->id]],
            ])
            ->assertSessionHasErrors(['gm_gdpr_consent' => self::CONSENT_MESSAGE]);
    }

    // Item: a locked item gets the lock flash before validation

    public function test_a_stale_edit_of_a_table_in_review_gets_the_lock_flash_not_validation_errors(): void
    {
        [$table] = $this->draftWithCoDirector();
        $table->update(['frontend_creation_status' => 'pending_review']);

        $this->actingAs($this->author)
            ->put("/mesas/mis-mesas/{$table->slug}", [...$this->tableData('Mesa corregida'), 'starts_at' => now()->subDay()->toIso8601String(), 'title' => ''])
            ->assertRedirect(route('gametables.my-tables'))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('error', 'Esta mesa ya se ha enviado a revisión y ya no se puede modificar.');

        $this->assertSame('Mesa del autor', $table->refresh()->title);
    }

    public function test_a_stale_edit_of_a_campaign_in_review_gets_the_lock_flash_not_validation_errors(): void
    {
        $campaign = CampaignModel::create([
            'id' => (string) Str::uuid(),
            'game_system_id' => $this->gameSystem->id,
            'created_by' => $this->author->id,
            'title' => 'Campaña del autor',
            'slug' => 'campana-del-autor',
            'frequency' => 'weekly',
            'status' => 'recruiting',
            'max_players' => 5,
            'is_published' => false,
            'frontend_creation_status' => 'pending_review',
        ]);

        $this->actingAs($this->author)
            ->put("/campanas/mis-campanas/{$campaign->id}", [...$this->campaignData(), 'title' => ''])
            ->assertRedirect(route('campaigns.my-campaigns'))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('error', 'Esta campaña ya se ha enviado a revisión y ya no se puede modificar.');

        $this->assertSame('Campaña del autor', $campaign->refresh()->title);
    }

    public function test_someone_else_still_meets_the_validation_and_the_policy(): void
    {
        [$table] = $this->draftWithCoDirector();
        $mary = UserModel::factory()->create(['name' => 'Mary Major', 'email' => 'mary@example.com']);

        $this->actingAs($mary)
            ->put("/mesas/mis-mesas/{$table->slug}", $this->tableData('Mesa ajena'))
            ->assertForbidden();
    }

    /**
     * @return array<string, mixed>
     */
    private function rulesOf(FrontendCreateGameTableRequest|FrontendCreateCampaignRequest $request): array
    {
        $request->setUserResolver(fn () => $this->author);
        $request->setRouteResolver(fn () => null);

        return $request->rules();
    }

    /**
     * @return array{0: GameTableModel, 1: GameMasterModel}
     */
    private function draftWithCoDirector(): array
    {
        $table = GameTableModel::create([
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
        $jane = GameMasterModel::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Jane',
            'email' => 'jane@example.com',
            'role' => 'co_gm',
            'notify_by_email' => true,
            'is_name_public' => true,
        ]);
        $table->gameMasters()->attach($jane->id, ['source' => 'local', 'excluded' => false, 'sort_order' => 1]);

        return [$table, $jane];
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
            'is_name_public' => true,
            'role' => 'main',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function coRow(string $firstName, string $email): array
    {
        return ['first_name' => $firstName, 'last_name' => 'Doe', 'email' => $email, 'is_name_public' => true, 'role' => 'co_gm'];
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

    /**
     * @return array<string, mixed>
     */
    private function campaignData(): array
    {
        return [
            'game_system_id' => $this->gameSystem->id,
            'title' => 'Campaña nueva',
            'frequency' => 'weekly',
            'min_players' => 2,
            'max_players' => 5,
            'table_format' => 'in_person',
        ];
    }
}
