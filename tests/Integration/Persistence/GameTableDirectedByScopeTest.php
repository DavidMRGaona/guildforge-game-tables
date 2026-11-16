<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Integration\Persistence;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Support\Str;
use Modules\GameTables\Domain\Enums\GameMasterRole;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\CampaignModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameMasterModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameSystemModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameTableModel;
use Tests\Support\Modules\ModuleTestCase;

/**
 * GameTableModel::scopeDirectedBy() is the SQL form of the one rule for a table's directors
 * (effective_game_masters): both must name the same tables.
 */
final class GameTableDirectedByScopeTest extends ModuleTestCase
{
    protected ?string $moduleName = 'game-tables';

    protected bool $autoEnableModule = true;

    private GameSystemModel $system;

    protected function setUp(): void
    {
        parent::setUp();

        $this->system = GameSystemModel::create(['id' => (string) Str::uuid(), 'name' => 'Sistema', 'slug' => Str::random(10), 'is_active' => true]);
    }

    public function test_the_scope_names_the_same_tables_as_the_effective_game_masters(): void
    {
        $user = UserModel::factory()->create();
        $other = UserModel::factory()->create();
        $public = $this->gameMaster($user, true);
        $hidden = $this->gameMaster($user, false);
        $campaign = $this->campaign([$public]);
        $hiddenCampaign = $this->campaign([$hidden]);

        $local = $this->table();
        $local->gameMasters()->attach($public->id, ['source' => 'local', 'excluded' => false, 'sort_order' => 1]);
        $localExcluded = $this->table();
        $localExcluded->gameMasters()->attach($public->id, ['source' => 'local', 'excluded' => true, 'sort_order' => 1]);
        $inheritedRowOnly = $this->table();
        $inheritedRowOnly->gameMasters()->attach($public->id, ['source' => 'inherited', 'excluded' => false, 'sort_order' => 1]);
        $session = $this->table($campaign);
        $excludedSession = $this->table($campaign);
        $excludedSession->gameMasters()->attach($public->id, ['source' => 'inherited', 'excluded' => true, 'sort_order' => 0]);
        $hiddenLocal = $this->table();
        $hiddenLocal->gameMasters()->attach($hidden->id, ['source' => 'local', 'excluded' => false, 'sort_order' => 1]);
        $hiddenSession = $this->table($hiddenCampaign);
        $someoneElse = $this->table();
        $someoneElse->gameMasters()->attach($this->gameMaster($other, true)->id, ['source' => 'local', 'excluded' => false, 'sort_order' => 1]);

        $tables = [$local, $localExcluded, $inheritedRowOnly, $session, $excludedSession, $hiddenLocal, $hiddenSession, $someoneElse];

        foreach ([null, true, false] as $namePublic) {
            $expected = [];
            foreach ($tables as $table) {
                $directs = $table->fresh()?->effective_game_masters->contains(
                    fn (GameMasterModel $gm): bool => $gm->user_id === $user->id && ($namePublic === null || $gm->is_name_public === $namePublic),
                );
                if ($directs === true) {
                    $expected[] = $table->id;
                }
            }

            $this->assertEqualsCanonicalizing(
                $expected,
                GameTableModel::query()->directedBy($user->id, $namePublic)->pluck('id')->all(),
                var_export($namePublic, true),
            );
        }

        $this->assertEqualsCanonicalizing(
            [$local->id, $session->id],
            GameTableModel::query()->directedBy($user->id, true)->pluck('id')->all(),
        );
    }

    private function gameMaster(UserModel $user, bool $namePublic): GameMasterModel
    {
        return GameMasterModel::create([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'first_name' => 'Director',
            'role' => GameMasterRole::Main,
            'notify_by_email' => false,
            'is_name_public' => $namePublic,
        ]);
    }

    /**
     * @param  list<GameMasterModel>  $gameMasters
     */
    private function campaign(array $gameMasters): CampaignModel
    {
        $campaign = CampaignModel::create([
            'id' => (string) Str::uuid(),
            'game_system_id' => $this->system->id,
            'created_by' => UserModel::factory()->create()->id,
            'title' => 'Campaña',
            'slug' => 'campana-'.Str::lower(Str::random(8)),
            'status' => 'active',
            'is_published' => true,
        ]);

        foreach ($gameMasters as $gameMaster) {
            $campaign->gameMasters()->attach($gameMaster->id, ['sort_order' => 1]);
        }

        return $campaign;
    }

    private function table(?CampaignModel $campaign = null): GameTableModel
    {
        return GameTableModel::create([
            'id' => (string) Str::uuid(),
            'game_system_id' => $this->system->id,
            'campaign_id' => $campaign?->id,
            'created_by' => UserModel::factory()->create()->id,
            'title' => 'Mesa',
            'slug' => 'mesa-'.Str::lower(Str::random(8)),
            'starts_at' => now()->addWeek(),
            'duration_minutes' => 240,
            'table_type' => 'one_shot',
            'table_format' => 'in_person',
            'status' => 'scheduled',
            'min_players' => 1,
            'max_players' => 5,
            'max_spectators' => 0,
            'language' => 'es',
            'registration_type' => 'everyone',
            'is_published' => true,
        ]);
    }
}
