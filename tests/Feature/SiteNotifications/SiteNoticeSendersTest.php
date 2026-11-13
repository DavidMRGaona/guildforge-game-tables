<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Feature\SiteNotifications;

use App\Application\Mail\Services\MailSubjectMuteServiceInterface;
use App\Application\SiteNotifications\DTOs\SiteNotificationItemDTO;
use App\Application\SiteNotifications\Services\SiteNotificationServiceInterface;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Str;
use Illuminate\Testing\PendingCommand;
use Modules\GameTables\Console\Commands\SendTableRemindersCommand;
use Modules\GameTables\Domain\Enums\GameMasterRole;
use Modules\GameTables\Domain\Enums\ParticipantStatus;
use Modules\GameTables\Domain\Events\CampaignInvitationSent;
use Modules\GameTables\Domain\Events\CampaignJoinRequestAccepted;
use Modules\GameTables\Domain\Events\CampaignRosterMemberLeft;
use Modules\GameTables\Domain\Events\CampaignSubmittedForReview;
use Modules\GameTables\Domain\Events\GameTableCancelled;
use Modules\GameTables\Domain\Events\ParticipantRegistered;
use Modules\GameTables\Domain\Events\ParticipantRejected;
use Modules\GameTables\Domain\Events\RosterMemberEnrolledInSession;
use Modules\GameTables\Filament\Resources\CampaignResource;
use Modules\GameTables\Filament\Resources\GameTableResource;
use Modules\GameTables\Infrastructure\Mail\GameTablesMailSubjects;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\CampaignInvitationModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\CampaignModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameMasterModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameSystemModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameTableModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\ParticipantModel;
use Tests\Support\Modules\ModuleTestCase;

/**
 * Whoever sends a notification gives it the ids its notice needs: the table or campaign it is
 * about and the account that caused it. Without them the mail would go out and the bell stay empty.
 */
final class SiteNoticeSendersTest extends ModuleTestCase
{
    protected ?string $moduleName = 'game-tables';

    protected bool $autoEnableModule = true;

    private GameSystemModel $system;

    private UserModel $player;

    private UserModel $director;

    protected function setUp(): void
    {
        parent::setUp();

        app()->setLocale('es');
        config()->set('modules.settings.game-tables', []);

        $this->system = GameSystemModel::create(['id' => (string) Str::uuid(), 'name' => 'Sistema de prueba', 'slug' => 'sistema-de-prueba', 'is_active' => true]);
        $this->player = UserModel::factory()->create(['name' => 'Mary Major']);
        $this->director = UserModel::factory()->create(['name' => 'John Doe']);
    }

    public function test_a_rejection_names_its_table(): void
    {
        $table = $this->table();
        $participant = $this->participant($table, 'pending');

        event(new ParticipantRejected($participant->id, $table->id, $this->player->id, 'Mesa completa'));

        $item = $this->only($this->player);
        $this->assertSame('Tu inscripción en «La tumba» no ha sido aceptada', $item->title);
        $this->assertSame('/mesas/la-tumba', $item->url);
    }

    public function test_a_cancelled_table_names_itself(): void
    {
        $table = $this->table();
        $this->participant($table, 'confirmed');
        $table->update(['status' => 'cancelled']);

        event(new GameTableCancelled($table->id, 'La tumba', $table->starts_at->toDateTimeImmutable()));

        $this->assertSame('/mesas/la-tumba', $this->only($this->player)->url);
    }

    public function test_a_reminder_names_its_table(): void
    {
        config()->set('modules.settings.game-tables.notifications', ['send_reminder' => true, 'reminder_hours_before' => 24]);
        $this->app->make(Kernel::class)->registerCommand($this->app->make(SendTableRemindersCommand::class));
        $table = $this->table(['starts_at' => now()->addHours(3)]);
        $this->participant($table, 'confirmed');

        $command = $this->artisan('game-tables:send-reminders');
        $this->assertInstanceOf(PendingCommand::class, $command);
        $command->assertSuccessful()->run();

        $item = $this->only($this->player);
        $this->assertStringStartsWith('Recordatorio: «La tumba», ', $item->title);
        $this->assertSame('/mesas/la-tumba', $item->url);
    }

    public function test_a_sign_up_tells_the_direction_who_signed_up(): void
    {
        $table = $this->table();
        $this->directs($table, $this->director);
        $participant = $this->participant($table, 'confirmed');

        event(new ParticipantRegistered($participant->id, $table->id, $this->player->id, 'player', ParticipantStatus::Confirmed));

        $this->assertSame('Mary Major se ha apuntado a «La tumba»', $this->only($this->director)->title);

        // The notice names the player: anonymizing them forgets it
        app(SiteNotificationServiceInterface::class)->forgetActor($this->player->id);
        $this->assertSame(0, $this->unread($this->director));
    }

    public function test_an_accepted_join_request_names_its_campaign(): void
    {
        $campaign = $this->campaign();

        event(new CampaignJoinRequestAccepted((string) Str::uuid(), $campaign->id, $this->player->id, []));

        $this->assertSame('/campanas/los-ecos', $this->only($this->player)->url);
    }

    public function test_a_player_who_leaves_is_named_to_the_direction(): void
    {
        $campaign = $this->campaign(['created_by' => $this->director->id, 'frontend_creation_status' => 'approved']);

        event(new CampaignRosterMemberLeft($campaign->id, $this->player->id));

        $item = $this->only($this->director);
        $this->assertSame('Mary Major ha salido de «Los ecos»', $item->title);
        $this->assertSame("/campanas/mis-campanas/{$campaign->id}/plantilla", $item->url);

        app(SiteNotificationServiceInterface::class)->forgetActor($this->player->id);
        $this->assertSame(0, $this->unread($this->director));
    }

    public function test_an_invitation_to_an_account_shows_in_its_bell(): void
    {
        $campaign = $this->campaign();
        $token = str_repeat('b', 64);
        $invitation = CampaignInvitationModel::create([
            'id' => (string) Str::uuid(),
            'campaign_id' => $campaign->id,
            'email' => mb_strtolower($this->player->email),
            'invited_by_user_id' => $this->director->id,
            'inviter_name' => 'John Doe',
            'token_hash' => hash('sha256', $token),
            'expires_at' => now()->addDays(14),
            'send_count' => 1,
            'last_sent_at' => now(),
        ]);

        event(new CampaignInvitationSent($invitation->id, $campaign->id, $this->player->email, $token));

        $item = $this->only($this->player);
        $this->assertSame('/perfil?tab=gametables-invitations', $item->url);

        // The notice names who invited: anonymizing them forgets it
        app(SiteNotificationServiceInterface::class)->forgetActor($this->director->id);
        $this->assertSame(0, $this->unread($this->player));
    }

    public function test_a_muted_campaign_holds_back_its_new_sessions(): void
    {
        $campaign = $this->campaign();
        $table = $this->table(['campaign_id' => $campaign->id]);
        $participant = $this->participant($table, 'confirmed');
        $subject = GameTablesMailSubjects::campaign($campaign->id, 'Los ecos');
        $this->assertNotNull($subject);
        app(MailSubjectMuteServiceInterface::class)->mute($this->player, $subject);

        event(new RosterMemberEnrolledInSession($participant->id, $table->id, $this->player->id, false));

        $this->assertSame(0, $this->unread($this->player));
    }

    public function test_a_moderator_never_gets_a_notice_about_their_own_proposal(): void
    {
        $this->registerOnAdminPanel([CampaignResource::class, GameTableResource::class]);
        $author = UserModel::factory()->admin()->create();
        $other = UserModel::factory()->admin()->create();
        $campaign = $this->campaign(['created_by' => $author->id, 'is_published' => false, 'frontend_creation_status' => 'pending_review']);

        event(new CampaignSubmittedForReview($campaign->id, 'Los ecos', $author->id));

        $this->assertSame(0, $this->unread($author));
        $this->assertSame('Campaña pendiente de revisar: «Los ecos»', $this->only($other)->title);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function table(array $attributes = []): GameTableModel
    {
        return GameTableModel::create(array_merge([
            'id' => (string) Str::uuid(),
            'game_system_id' => $this->system->id,
            'created_by' => $this->director->id,
            'title' => 'La tumba',
            'slug' => 'la-tumba',
            'starts_at' => now()->addWeek(),
            'duration_minutes' => 240,
            'table_type' => 'one_shot',
            'table_format' => 'in_person',
            'status' => 'scheduled',
            'min_players' => 1,
            'max_players' => 5,
            'language' => 'es',
            'registration_type' => 'everyone',
            'auto_confirm' => true,
            'is_published' => true,
        ], $attributes));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function campaign(array $attributes = []): CampaignModel
    {
        return CampaignModel::create(array_merge([
            'id' => (string) Str::uuid(),
            'game_system_id' => $this->system->id,
            'created_by' => UserModel::factory()->create()->id,
            'title' => 'Los ecos',
            'slug' => 'los-ecos',
            'status' => 'recruiting',
            'is_published' => true,
        ], $attributes));
    }

    private function participant(GameTableModel $table, string $status): ParticipantModel
    {
        return ParticipantModel::create([
            'id' => (string) Str::uuid(),
            'game_table_id' => $table->id,
            'user_id' => $this->player->id,
            'role' => 'player',
            'status' => $status,
            'confirmed_at' => $status === 'confirmed' ? now() : null,
        ]);
    }

    private function directs(GameTableModel $table, UserModel $user): void
    {
        $gm = GameMasterModel::create([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'first_name' => 'GM',
            'last_name' => 'Doe',
            'role' => GameMasterRole::Main,
            'notify_by_email' => true,
            'is_name_public' => true,
        ]);
        $table->gameMasters()->attach($gm->id, ['source' => 'local', 'excluded' => false, 'sort_order' => 1]);
    }

    private function only(UserModel $user): SiteNotificationItemDTO
    {
        $items = app(SiteNotificationServiceInterface::class)->latest($user, 10);
        $this->assertCount(1, $items);

        return $items[0];
    }

    private function unread(UserModel $user): int
    {
        return app(SiteNotificationServiceInterface::class)->unreadCount($user);
    }
}
