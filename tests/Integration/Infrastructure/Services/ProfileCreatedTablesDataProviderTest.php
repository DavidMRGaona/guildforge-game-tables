<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Integration\Infrastructure\Services;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Support\Str;
use Modules\GameTables\Domain\Enums\TableFormat;
use Modules\GameTables\Domain\Enums\TableStatus;
use Modules\GameTables\Domain\Enums\TableType;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameSystemModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameTableModel;
use Modules\GameTables\Domain\Enums\ParticipantRole;
use Modules\GameTables\Domain\Enums\ParticipantStatus;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\ParticipantModel;
use Modules\GameTables\Infrastructure\Services\ProfileCreatedTablesDataProvider;
use Tests\Support\Modules\ModuleTestCase;

final class ProfileCreatedTablesDataProviderTest extends ModuleTestCase
{
    protected ?string $moduleName = 'game-tables';

    protected bool $autoEnableModule = true;

    public function test_created_tables_show_how_many_seats_are_taken(): void
    {
        $creator = $this->creator();
        $table = $this->table($creator);
        $this->participant($table, ParticipantRole::GameMaster, ParticipantStatus::Confirmed);
        $this->participant($table, ParticipantRole::Player, ParticipantStatus::Confirmed);
        $this->participant($table, ParticipantRole::Player, ParticipantStatus::Confirmed);
        $this->participant($table, ParticipantRole::Player, ParticipantStatus::Pending);
        $this->participant($table, ParticipantRole::Player, ParticipantStatus::WaitingList);
        $this->participant($table, ParticipantRole::Player, ParticipantStatus::Cancelled);

        $data = app(ProfileCreatedTablesDataProvider::class)->getDataForUser((string) $creator->id);

        // Same count as the table's capacity check: confirmed players only (pending, waiting list and GMs do not take a seat)
        $this->assertSame(2, $data['tables'][0]['currentPlayers']);
    }

    public function test_an_unpublished_table_shows_its_review_state_and_is_editable_only_as_a_draft(): void
    {
        $creator = $this->creator();
        $this->table($creator, ['is_published' => false, 'published_at' => null, 'frontend_creation_status' => 'pending_review', 'title' => 'En revisión']);
        $this->table($creator, ['is_published' => false, 'published_at' => null, 'frontend_creation_status' => 'draft', 'title' => 'Borrador']);

        $drafts = collect(app(ProfileCreatedTablesDataProvider::class)->getDataForUser((string) $creator->id)['drafts'])->keyBy('title');

        // As «Mis mesas»: a table sent to review is waiting for the moderators, not editable
        $this->assertSame('pending_review', $drafts['En revisión']['status']);
        $this->assertSame(__('game-tables::messages.enums.frontend_creation_status.pending_review'), $drafts['En revisión']['statusLabel']);
        $this->assertSame('warning', $drafts['En revisión']['statusColor']);
        $this->assertFalse($drafts['En revisión']['canEdit']);
        $this->assertSame('draft', $drafts['Borrador']['status']);
        $this->assertTrue($drafts['Borrador']['canEdit']);
    }

    private function participant(GameTableModel $table, ParticipantRole $role, ParticipantStatus $status): void
    {
        ParticipantModel::create([
            'id' => (string) Str::uuid(),
            'game_table_id' => $table->id,
            'user_id' => $this->creator()->id,
            'role' => $role,
            'status' => $status,
        ]);
    }

    private function creator(): UserModel
    {
        return UserModel::create([
            'id' => (string) Str::uuid(),
            'name' => 'Table Creator',
            'email' => 'creator-'.bin2hex(random_bytes(4)).'@example.com',
            'password' => 'password',
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function table(UserModel $creator, array $overrides = []): GameTableModel
    {
        $system = GameSystemModel::create([
            'id' => (string) Str::uuid(),
            'name' => 'Fixture System',
            'slug' => 'fixture-system-'.bin2hex(random_bytes(4)),
            'game_master_title' => 'Game Master',
            'is_active' => true,
        ]);

        return GameTableModel::create([
            'id' => (string) Str::uuid(),
            'game_system_id' => $system->id,
            'created_by' => $creator->id,
            'title' => 'La cripta olvidada',
            'starts_at' => '2026-05-09 17:00:00',
            'duration_minutes' => 240,
            'table_type' => TableType::OneShot,
            'table_format' => TableFormat::InPerson,
            'status' => TableStatus::Scheduled,
            'min_players' => 2,
            'max_players' => 6,
            'language' => 'es',
            'auto_confirm' => true,
            'is_published' => true,
            'published_at' => now(),
            ...$overrides,
        ]);
    }
}
