<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Integration\Services;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Support\Str;
use Modules\GameTables\Application\Services\TableDirectionInterface;
use Modules\GameTables\Domain\Enums\GameMasterRole;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\CampaignModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameMasterModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameSystemModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameTableModel;
use Tests\Support\Modules\ModuleTestCase;

final class TableDirectionTest extends ModuleTestCase
{
    protected ?string $moduleName = 'game-tables';

    protected bool $autoEnableModule = true;

    private TableDirectionInterface $direction;

    private GameSystemModel $system;

    protected function setUp(): void
    {
        parent::setUp();

        $this->direction = $this->app->make(TableDirectionInterface::class);
        $this->system = GameSystemModel::create(['id' => (string) Str::uuid(), 'name' => 'Sistema', 'slug' => Str::random(10), 'is_active' => true]);
    }

    private function table(?CampaignModel $campaign = null): GameTableModel
    {
        return GameTableModel::create([
            'id' => (string) Str::uuid(),
            'game_system_id' => $this->system->id,
            'campaign_id' => $campaign?->id,
            'created_by' => UserModel::factory()->create()->id,
            'title' => 'Mesa de prueba',
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

    private function campaign(): CampaignModel
    {
        return CampaignModel::create([
            'id' => (string) Str::uuid(),
            'game_system_id' => $this->system->id,
            'created_by' => UserModel::factory()->create()->id,
            'title' => 'Campaña',
            'slug' => Str::random(10),
            'status' => 'recruiting',
        ]);
    }

    private function gm(?UserModel $user, GameMasterRole $role = GameMasterRole::Main): GameMasterModel
    {
        return GameMasterModel::create([
            'id' => (string) Str::uuid(),
            'user_id' => $user?->id,
            'first_name' => 'GM',
            'last_name' => 'Prueba',
            'email' => $user === null ? 'external@example.com' : null,
            'role' => $role,
            'notify_by_email' => true,
            'is_name_public' => true,
        ]);
    }

    private function attach(GameTableModel $table, GameMasterModel $gm, string $source = 'local', bool $excluded = false): void
    {
        $table->gameMasters()->attach($gm->id, ['source' => $source, 'excluded' => $excluded, 'sort_order' => 1]);
    }

    public function test_main_and_co_game_masters_with_an_account_direct(): void
    {
        $john = UserModel::factory()->create();
        $mary = UserModel::factory()->create();
        $table = $this->table();
        $this->attach($table, $this->gm($john));
        $this->attach($table, $this->gm($mary, GameMasterRole::CoGm));

        $this->assertTrue($this->direction->isDirector($table->id, $john->id));
        $this->assertTrue($this->direction->isDirector($table->id, $mary->id));
        $this->assertEqualsCanonicalizing([$john->id, $mary->id], $this->direction->directorUserIds($table->id));
    }

    public function test_an_external_game_master_does_not_direct(): void
    {
        $table = $this->table();
        $this->attach($table, $this->gm(null));

        $this->assertSame([], $this->direction->directorUserIds($table->id));
        $this->assertFalse($this->direction->isDirector($table->id, UserModel::factory()->create()->id));
    }

    public function test_an_anonymized_user_does_not_direct(): void
    {
        $john = UserModel::factory()->create();
        $table = $this->table();
        $this->attach($table, $this->gm($john));
        $john->forceFill(['anonymized_at' => now()])->save();

        $this->assertFalse($this->direction->isDirector($table->id, $john->id));
        $this->assertSame([], $this->direction->tableIdsDirectedBy($john->id));
    }

    public function test_an_inherited_game_master_directs_unless_excluded(): void
    {
        $john = UserModel::factory()->create();
        $mary = UserModel::factory()->create();
        $campaign = $this->campaign();
        $johnGm = $this->gm($john);
        $maryGm = $this->gm($mary);
        $campaign->gameMasters()->attach($johnGm->id, ['sort_order' => 1]);
        $campaign->gameMasters()->attach($maryGm->id, ['sort_order' => 2]);
        $session = $this->table($campaign);
        $this->attach($session, $maryGm, 'inherited', true);

        $this->assertTrue($this->direction->isDirector($session->id, $john->id));
        $this->assertFalse($this->direction->isDirector($session->id, $mary->id));
        $this->assertSame([$session->id], $this->direction->tableIdsDirectedBy($john->id));
        $this->assertSame([], $this->direction->tableIdsDirectedBy($mary->id));
    }

    public function test_tables_directed_by_lists_only_tables_the_user_directs(): void
    {
        $john = UserModel::factory()->create();
        $mine = $this->table();
        $this->attach($mine, $this->gm($john));
        $other = $this->table();
        $this->attach($other, $this->gm(UserModel::factory()->create()));
        $this->table();

        $this->assertSame([$mine->id], $this->direction->tableIdsDirectedBy($john->id));
    }
}
