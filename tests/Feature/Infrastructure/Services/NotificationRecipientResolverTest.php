<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Feature\Infrastructure\Services;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Modules\GameTables\Domain\Enums\GameMasterRole;
use Modules\GameTables\Domain\Enums\ParticipantRole;
use Modules\GameTables\Domain\Enums\ParticipantStatus;
use Modules\GameTables\Domain\Enums\TableFormat;
use Modules\GameTables\Domain\Enums\TableStatus;
use Modules\GameTables\Domain\Enums\TableType;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameMasterModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameSystemModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameTableModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\ParticipantModel;
use Modules\GameTables\Infrastructure\Services\NotificationRecipientResolver;
use Tests\TestCase;

final class NotificationRecipientResolverTest extends TestCase
{
    use RefreshDatabase;

    private NotificationRecipientResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->resolver = new NotificationRecipientResolver();
    }

    public function test_get_game_master_emails_returns_gm_emails_with_notify_enabled(): void
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

        GameMasterModel::create([
            'id' => (string) Str::uuid(),
            'game_table_id' => $gameTable->id,
            'first_name' => 'GM',
            'last_name' => 'One',
            'email' => 'gm1@example.com',
            'role' => GameMasterRole::Primary,
            'notify_by_email' => true,
            'is_name_public' => true,
        ]);

        GameMasterModel::create([
            'id' => (string) Str::uuid(),
            'game_table_id' => $gameTable->id,
            'first_name' => 'GM',
            'last_name' => 'Two',
            'email' => 'gm2@example.com',
            'role' => GameMasterRole::Assistant,
            'notify_by_email' => true,
            'is_name_public' => true,
        ]);

        $result = $this->resolver->getGameMasterEmails($gameTable->id);

        $this->assertCount(2, $result);
        $this->assertContains('gm1@example.com', $result);
        $this->assertContains('gm2@example.com', $result);
    }

    public function test_get_game_master_emails_excludes_gms_without_notify(): void
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

        GameMasterModel::create([
            'id' => (string) Str::uuid(),
            'game_table_id' => $gameTable->id,
            'first_name' => 'GM',
            'last_name' => 'Enabled',
            'email' => 'enabled@example.com',
            'role' => GameMasterRole::Primary,
            'notify_by_email' => true,
            'is_name_public' => true,
        ]);

        GameMasterModel::create([
            'id' => (string) Str::uuid(),
            'game_table_id' => $gameTable->id,
            'first_name' => 'GM',
            'last_name' => 'Disabled',
            'email' => 'disabled@example.com',
            'role' => GameMasterRole::Assistant,
            'notify_by_email' => false,
            'is_name_public' => true,
        ]);

        $result = $this->resolver->getGameMasterEmails($gameTable->id);

        $this->assertCount(1, $result);
        $this->assertContains('enabled@example.com', $result);
        $this->assertNotContains('disabled@example.com', $result);
    }

    public function test_get_game_master_emails_uses_user_email_when_gm_email_is_null(): void
    {
        $user = UserModel::create([
            'id' => (string) Str::uuid(),
            'name' => 'User GM',
            'email' => 'user@example.com',
            'password' => 'password',
        ]);

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
            'created_by' => $user->id,
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

        GameMasterModel::create([
            'id' => (string) Str::uuid(),
            'game_table_id' => $gameTable->id,
            'user_id' => $user->id,
            'email' => null,
            'role' => GameMasterRole::Primary,
            'notify_by_email' => true,
            'is_name_public' => true,
        ]);

        $result = $this->resolver->getGameMasterEmails($gameTable->id);

        $this->assertCount(1, $result);
        $this->assertContains('user@example.com', $result);
    }

    public function test_get_table_notification_email_returns_email(): void
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
            'notification_email' => 'table@example.com',
        ]);

        $result = $this->resolver->getTableNotificationEmail($gameTable->id);

        $this->assertEquals('table@example.com', $result);
    }

    public function test_get_table_notification_email_returns_null_when_not_set(): void
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
            'notification_email' => null,
        ]);

        $result = $this->resolver->getTableNotificationEmail($gameTable->id);

        $this->assertNull($result);
    }

    public function test_get_participant_email_returns_guest_email(): void
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

        $participant = ParticipantModel::create([
            'id' => (string) Str::uuid(),
            'game_table_id' => $gameTable->id,
            'user_id' => null,
            'first_name' => 'Guest',
            'last_name' => 'Player',
            'email' => 'guest@example.com',
            'role' => ParticipantRole::Player,
            'status' => ParticipantStatus::Confirmed,
        ]);

        $result = $this->resolver->getParticipantEmail($participant->id);

        $this->assertEquals('guest@example.com', $result);
    }

    public function test_get_participant_email_returns_user_email(): void
    {
        $user = UserModel::create([
            'id' => (string) Str::uuid(),
            'name' => 'User Player',
            'email' => 'user@example.com',
            'password' => 'password',
        ]);

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
            'created_by' => $user->id,
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

        $participant = ParticipantModel::create([
            'id' => (string) Str::uuid(),
            'game_table_id' => $gameTable->id,
            'user_id' => $user->id,
            'email' => null,
            'role' => ParticipantRole::Player,
            'status' => ParticipantStatus::Confirmed,
        ]);

        $result = $this->resolver->getParticipantEmail($participant->id);

        $this->assertEquals('user@example.com', $result);
    }

    public function test_get_participant_display_name_returns_guest_name(): void
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

        $participant = ParticipantModel::create([
            'id' => (string) Str::uuid(),
            'game_table_id' => $gameTable->id,
            'user_id' => null,
            'first_name' => 'Guest',
            'last_name' => 'Player',
            'email' => 'guest@example.com',
            'role' => ParticipantRole::Player,
            'status' => ParticipantStatus::Confirmed,
        ]);

        $result = $this->resolver->getParticipantDisplayName($participant->id);

        $this->assertEquals('Guest Player', $result);
    }

    public function test_get_participant_display_name_returns_user_name(): void
    {
        $user = UserModel::create([
            'id' => (string) Str::uuid(),
            'name' => 'User Player',
            'email' => 'user@example.com',
            'password' => 'password',
        ]);

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
            'created_by' => $user->id,
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

        $participant = ParticipantModel::create([
            'id' => (string) Str::uuid(),
            'game_table_id' => $gameTable->id,
            'user_id' => $user->id,
            'email' => null,
            'role' => ParticipantRole::Player,
            'status' => ParticipantStatus::Confirmed,
        ]);

        $result = $this->resolver->getParticipantDisplayName($participant->id);

        $this->assertEquals('User Player', $result);
    }
}
