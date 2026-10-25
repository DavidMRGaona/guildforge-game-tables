<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Feature\Policies;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Support\Str;
use Modules\GameTables\Domain\Enums\GameMasterRole;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\CampaignModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameMasterModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameSystemModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameTableModel;
use Tests\Support\Modules\ModuleTestCase;

final class ManageRegistrationsPolicyTest extends ModuleTestCase
{
    protected ?string $moduleName = 'game-tables';

    protected bool $autoEnableModule = true;

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function tableDirectedBy(UserModel $user, array $attributes = []): GameTableModel
    {
        $system = GameSystemModel::create(['id' => (string) Str::uuid(), 'name' => 'Sistema', 'slug' => Str::random(10), 'is_active' => true]);

        $table = GameTableModel::create([
            'id' => (string) Str::uuid(),
            'game_system_id' => $system->id,
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
            'delegates_registrations' => true,
            ...$attributes,
        ]);
        $gm = GameMasterModel::create([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'first_name' => 'GM',
            'role' => GameMasterRole::Main,
            'notify_by_email' => true,
            'is_name_public' => true,
        ]);
        $table->gameMasters()->attach($gm->id, ['source' => 'local', 'excluded' => false, 'sort_order' => 1]);

        return $table;
    }

    public function test_a_linked_game_master_manages_a_published_delegating_table(): void
    {
        $john = UserModel::factory()->create();

        $this->assertTrue($john->can('manageRegistrations', $this->tableDirectedBy($john)));
    }

    public function test_it_is_denied_when_the_table_does_not_delegate(): void
    {
        $john = UserModel::factory()->create();

        $this->assertFalse($john->can('manageRegistrations', $this->tableDirectedBy($john, ['delegates_registrations' => false])));
    }

    public function test_it_is_denied_for_an_unpublished_table(): void
    {
        $john = UserModel::factory()->create();

        $this->assertFalse($john->can('manageRegistrations', $this->tableDirectedBy($john, ['is_published' => false])));
    }

    public function test_it_is_denied_for_a_campaign_session(): void
    {
        $john = UserModel::factory()->create();
        $system = GameSystemModel::create(['id' => (string) Str::uuid(), 'name' => 'Otro', 'slug' => Str::random(10), 'is_active' => true]);
        $campaign = CampaignModel::create([
            'id' => (string) Str::uuid(),
            'game_system_id' => $system->id,
            'created_by' => $john->id,
            'title' => 'Campaña',
            'slug' => Str::random(10),
            'status' => 'recruiting',
        ]);

        $this->assertFalse($john->can('manageRegistrations', $this->tableDirectedBy($john, ['campaign_id' => $campaign->id])));
    }

    public function test_it_is_denied_to_someone_who_is_not_a_game_master(): void
    {
        $john = UserModel::factory()->create();
        $table = $this->tableDirectedBy($john);

        $this->assertFalse(UserModel::factory()->create()->can('manageRegistrations', $table));
    }

    public function test_it_is_denied_to_an_anonymized_game_master(): void
    {
        $john = UserModel::factory()->create();
        $table = $this->tableDirectedBy($john);
        $john->forceFill(['anonymized_at' => now()])->save();

        $this->assertFalse($john->refresh()->can('manageRegistrations', $table));
    }

    public function test_an_admin_who_is_not_a_game_master_is_denied(): void
    {
        $table = $this->tableDirectedBy(UserModel::factory()->create());

        $this->assertFalse(UserModel::factory()->admin()->create()->can('manageRegistrations', $table));
    }
}
