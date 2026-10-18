<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Feature\Http\Controllers;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Support\Str;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\CampaignModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameSystemModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameTableModel;
use Tests\Support\Modules\ModuleTestCase;

/**
 * The web form offers only the author's own campaigns; the server applies the same rule, so a
 * forged campaign_id never links a table to someone else's campaign.
 */
final class FrontendTableCampaignOwnershipTest extends ModuleTestCase
{
    private const string REFUSED = 'Solo puedes vincular la mesa a una campaña creada por ti';

    protected ?string $moduleName = 'game-tables';

    protected bool $autoEnableModule = true;

    private UserModel $author;

    private GameSystemModel $gameSystem;

    protected function setUp(): void
    {
        parent::setUp();

        app('router')->getRoutes()->refreshNameLookups();
        config()->set('modules.settings.game-tables', [
            'frontend_creation' => [
                'enabled' => true,
                'access_level' => 'registered',
                'allowed_content' => 'both',
            ],
        ]);

        $this->author = UserModel::factory()->create();
        $this->gameSystem = GameSystemModel::create([
            'id' => (string) Str::uuid(),
            'name' => 'Sistema de prueba',
            'slug' => 'sistema-de-prueba',
            'is_active' => true,
        ]);
    }

    public function test_creating_a_table_in_someone_elses_campaign_is_refused(): void
    {
        $foreign = $this->campaign(UserModel::factory()->create(), 'campana-ajena');

        $this->actingAs($this->author)
            ->post(route('gametables.store'), $this->tableData('Mesa intrusa', $foreign->id))
            ->assertSessionHasErrors(['campaign_id' => self::REFUSED]);

        $this->assertFalse(GameTableModel::query()->where('title', 'Mesa intrusa')->exists());
    }

    public function test_creating_a_table_in_an_own_campaign_or_without_one_is_accepted(): void
    {
        $own = $this->campaign($this->author, 'campana-propia');

        $this->actingAs($this->author)
            ->post(route('gametables.store'), $this->tableData('Mesa de mi campaña', $own->id))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('gametables.my-tables'));
        $this->actingAs($this->author)
            ->post(route('gametables.store'), $this->tableData('Mesa suelta', null))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('gametables.my-tables'));

        $this->assertSame($own->id, GameTableModel::query()->where('title', 'Mesa de mi campaña')->value('campaign_id'));
        $this->assertNull(GameTableModel::query()->where('title', 'Mesa suelta')->value('campaign_id'));
    }

    public function test_editing_a_draft_into_someone_elses_campaign_is_refused(): void
    {
        $foreign = $this->campaign(UserModel::factory()->create(), 'campana-ajena');
        $draft = $this->tableDraft();

        $this->actingAs($this->author)
            ->put(route('gametables.update', $draft), $this->tableData('Mesa corregida', $foreign->id))
            ->assertSessionHasErrors(['campaign_id' => self::REFUSED]);

        $draft->refresh();
        $this->assertNull($draft->campaign_id);
        $this->assertSame('Mesa en borrador', $draft->title);
    }

    public function test_editing_a_draft_into_an_own_campaign_is_accepted(): void
    {
        $own = $this->campaign($this->author, 'campana-propia');
        $draft = $this->tableDraft();

        $this->actingAs($this->author)
            ->put(route('gametables.update', $draft), $this->tableData('Mesa corregida', $own->id))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('gametables.my-tables'));

        $this->assertSame($own->id, $draft->refresh()->campaign_id);
    }

    /**
     * An admin may link a table to any campaign from the panel; its author can still edit the
     * table from the web while keeping that campaign, but cannot move it to another foreign one.
     */
    public function test_editing_a_table_keeps_a_campaign_an_admin_linked_but_refuses_another_foreign_one(): void
    {
        $linkedByAdmin = $this->campaign(UserModel::factory()->create(), 'campana-del-panel');
        $otherForeign = $this->campaign(UserModel::factory()->create(), 'otra-campana-ajena');
        $draft = $this->tableDraft($linkedByAdmin->id);

        $this->actingAs($this->author)
            ->put(route('gametables.update', $draft), $this->tableData('Mesa corregida', $linkedByAdmin->id))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('gametables.my-tables'));

        $draft->refresh();
        $this->assertSame($linkedByAdmin->id, $draft->campaign_id);
        $this->assertSame('Mesa corregida', $draft->title);

        $this->actingAs($this->author)
            ->put(route('gametables.update', $draft), $this->tableData('Mesa movida', $otherForeign->id))
            ->assertSessionHasErrors(['campaign_id' => self::REFUSED]);

        $draft->refresh();
        $this->assertSame($linkedByAdmin->id, $draft->campaign_id);
        $this->assertSame('Mesa corregida', $draft->title);
    }

    public function test_the_refusal_is_translated_in_both_languages(): void
    {
        $this->assertSame(self::REFUSED, trans('game-tables::messages.validation.campaign_not_owned', [], 'es'));
        $this->assertSame('You can only link the table to a campaign you created', trans('game-tables::messages.validation.campaign_not_owned', [], 'en'));
    }

    /**
     * @return array<string, mixed>
     */
    private function tableData(string $title, ?string $campaignId): array
    {
        return [
            'game_system_id' => $this->gameSystem->id,
            'campaign_id' => $campaignId,
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

    private function campaign(UserModel $creator, string $slug): CampaignModel
    {
        return CampaignModel::create([
            'id' => (string) Str::uuid(),
            'game_system_id' => $this->gameSystem->id,
            'created_by' => $creator->id,
            'title' => 'Campaña '.$slug,
            'slug' => $slug,
            'frequency' => 'weekly',
            'status' => 'recruiting',
            'max_players' => 5,
            'is_published' => true,
        ]);
    }

    private function tableDraft(?string $campaignId = null): GameTableModel
    {
        return GameTableModel::create([
            'id' => (string) Str::uuid(),
            'game_system_id' => $this->gameSystem->id,
            'campaign_id' => $campaignId,
            'created_by' => $this->author->id,
            'title' => 'Mesa en borrador',
            'slug' => 'mesa-en-borrador',
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
