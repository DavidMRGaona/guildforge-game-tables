<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Feature\Infrastructure\Services;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Modules\GameTables\Domain\Enums\GameMasterRole;
use Modules\GameTables\Domain\Enums\TableFormat;
use Modules\GameTables\Domain\Enums\TableStatus;
use Modules\GameTables\Domain\Enums\TableType;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameMasterModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameSystemModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameTableModel;
use Modules\GameTables\Infrastructure\Services\NotificationRecipientResolver;
use Tests\TestCase;

final class NotificationRecipientResolverManyToManyTest extends TestCase
{
    use RefreshDatabase;

    private NotificationRecipientResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->resolver = new NotificationRecipientResolver();
    }

    public function test_get_game_master_emails_returns_correct_emails_through_pivot_relationship(): void
    {
        $gameSystem = GameSystemModel::create([
            'id' => (string) Str::uuid(),
            'name' => 'Dungeons & Dragons',
            'slug' => 'dnd',
            'game_master_title' => 'Dungeon Master',
            'is_active' => true,
        ]);

        $gameTable = GameTableModel::create([
            'id' => (string) Str::uuid(),
            'game_system_id' => $gameSystem->id,
            'created_by' => (string) Str::uuid(),
            'title' => 'Test Table',
            'starts_at' => now()->addWeek(),
            'duration_minutes' => 240,
            'table_type' => TableType::OneShot,
            'table_format' => TableFormat::InPerson,
            'status' => TableStatus::Draft,
            'min_players' => 2,
            'max_players' => 6,
            'language' => 'es',
            'auto_confirm' => true,
        ]);

        $gameMaster = GameMasterModel::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'John',
            'last_name' => 'Master',
            'email' => 'gm@example.com',
            'role' => GameMasterRole::Main,
            'notify_by_email' => true,
            'is_name_public' => true,
        ]);

        // Associate game master with game table through pivot table
        $gameTable->gameMasters()->attach($gameMaster->id, [
            'source' => 'local',
            'excluded' => false,
            'sort_order' => 1,
        ]);

        $result = $this->resolver->getGameMasterEmails($gameTable->id);

        $this->assertCount(1, $result);
        $this->assertContains('gm@example.com', $result);
    }

    public function test_get_game_master_emails_returns_multiple_emails_through_pivot_relationship(): void
    {
        $gameSystem = GameSystemModel::create([
            'id' => (string) Str::uuid(),
            'name' => 'Pathfinder',
            'slug' => 'pathfinder',
            'game_master_title' => 'Game Master',
            'is_active' => true,
        ]);

        $gameTable = GameTableModel::create([
            'id' => (string) Str::uuid(),
            'game_system_id' => $gameSystem->id,
            'created_by' => (string) Str::uuid(),
            'title' => 'Multiple GMs Table',
            'starts_at' => now()->addWeek(),
            'duration_minutes' => 240,
            'table_type' => TableType::Campaign,
            'table_format' => TableFormat::Online,
            'status' => TableStatus::Open,
            'min_players' => 3,
            'max_players' => 6,
            'language' => 'es',
            'auto_confirm' => true,
        ]);

        $gameMaster1 = GameMasterModel::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Alice',
            'last_name' => 'GM',
            'email' => 'alice@example.com',
            'role' => GameMasterRole::Main,
            'notify_by_email' => true,
            'is_name_public' => true,
        ]);

        $gameMaster2 = GameMasterModel::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Bob',
            'last_name' => 'GM',
            'email' => 'bob@example.com',
            'role' => GameMasterRole::Assistant,
            'notify_by_email' => true,
            'is_name_public' => true,
        ]);

        // Associate both game masters through pivot table
        $gameTable->gameMasters()->attach($gameMaster1->id, [
            'source' => 'local',
            'excluded' => false,
            'sort_order' => 1,
        ]);

        $gameTable->gameMasters()->attach($gameMaster2->id, [
            'source' => 'local',
            'excluded' => false,
            'sort_order' => 2,
        ]);

        $result = $this->resolver->getGameMasterEmails($gameTable->id);

        $this->assertCount(2, $result);
        $this->assertContains('alice@example.com', $result);
        $this->assertContains('bob@example.com', $result);
    }

    public function test_get_game_master_emails_excludes_gms_without_notify_through_pivot(): void
    {
        $gameSystem = GameSystemModel::create([
            'id' => (string) Str::uuid(),
            'name' => 'Call of Cthulhu',
            'slug' => 'coc',
            'game_master_title' => 'Keeper',
            'is_active' => true,
        ]);

        $gameTable = GameTableModel::create([
            'id' => (string) Str::uuid(),
            'game_system_id' => $gameSystem->id,
            'created_by' => (string) Str::uuid(),
            'title' => 'Notification Test Table',
            'starts_at' => now()->addWeek(),
            'duration_minutes' => 300,
            'table_type' => TableType::OneShot,
            'table_format' => TableFormat::Hybrid,
            'status' => TableStatus::Open,
            'min_players' => 2,
            'max_players' => 4,
            'language' => 'es',
            'auto_confirm' => false,
        ]);

        $gmWithNotify = GameMasterModel::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Notify',
            'last_name' => 'Enabled',
            'email' => 'notify@example.com',
            'role' => GameMasterRole::Main,
            'notify_by_email' => true,
            'is_name_public' => true,
        ]);

        $gmWithoutNotify = GameMasterModel::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Notify',
            'last_name' => 'Disabled',
            'email' => 'nonotify@example.com',
            'role' => GameMasterRole::Assistant,
            'notify_by_email' => false,
            'is_name_public' => true,
        ]);

        // Associate both through pivot
        $gameTable->gameMasters()->attach($gmWithNotify->id, [
            'source' => 'local',
            'excluded' => false,
            'sort_order' => 1,
        ]);

        $gameTable->gameMasters()->attach($gmWithoutNotify->id, [
            'source' => 'local',
            'excluded' => false,
            'sort_order' => 2,
        ]);

        $result = $this->resolver->getGameMasterEmails($gameTable->id);

        $this->assertCount(1, $result);
        $this->assertContains('notify@example.com', $result);
        $this->assertNotContains('nonotify@example.com', $result);
    }

    public function test_get_game_master_emails_returns_empty_when_no_gms_for_table(): void
    {
        $gameSystem = GameSystemModel::create([
            'id' => (string) Str::uuid(),
            'name' => 'Warhammer',
            'slug' => 'warhammer',
            'game_master_title' => 'Game Master',
            'is_active' => true,
        ]);

        $gameTable = GameTableModel::create([
            'id' => (string) Str::uuid(),
            'game_system_id' => $gameSystem->id,
            'created_by' => (string) Str::uuid(),
            'title' => 'No GMs Table',
            'starts_at' => now()->addWeek(),
            'duration_minutes' => 240,
            'table_type' => TableType::OneShot,
            'table_format' => TableFormat::InPerson,
            'status' => TableStatus::Draft,
            'min_players' => 2,
            'max_players' => 6,
            'language' => 'es',
            'auto_confirm' => true,
        ]);

        // Create a GM but don't associate it with the table
        GameMasterModel::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Unassociated',
            'last_name' => 'GM',
            'email' => 'unassociated@example.com',
            'role' => GameMasterRole::Main,
            'notify_by_email' => true,
            'is_name_public' => true,
        ]);

        $result = $this->resolver->getGameMasterEmails($gameTable->id);

        $this->assertEmpty($result);
    }

    public function test_get_game_master_emails_uses_user_email_when_gm_email_is_null_through_pivot(): void
    {
        $user = UserModel::create([
            'id' => (string) Str::uuid(),
            'name' => 'User GM',
            'email' => 'user@example.com',
            'password' => 'password',
        ]);

        $gameSystem = GameSystemModel::create([
            'id' => (string) Str::uuid(),
            'name' => 'D&D',
            'slug' => 'dnd',
            'game_master_title' => 'DM',
            'is_active' => true,
        ]);

        $gameTable = GameTableModel::create([
            'id' => (string) Str::uuid(),
            'game_system_id' => $gameSystem->id,
            'created_by' => $user->id,
            'title' => 'User GM Table',
            'starts_at' => now()->addWeek(),
            'duration_minutes' => 240,
            'table_type' => TableType::OneShot,
            'table_format' => TableFormat::Online,
            'status' => TableStatus::Open,
            'min_players' => 2,
            'max_players' => 6,
            'language' => 'es',
            'auto_confirm' => true,
        ]);

        $gameMaster = GameMasterModel::create([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'email' => null,
            'role' => GameMasterRole::Main,
            'notify_by_email' => true,
            'is_name_public' => true,
        ]);

        $gameTable->gameMasters()->attach($gameMaster->id, [
            'source' => 'local',
            'excluded' => false,
            'sort_order' => 1,
        ]);

        $result = $this->resolver->getGameMasterEmails($gameTable->id);

        $this->assertCount(1, $result);
        $this->assertContains('user@example.com', $result);
    }
}