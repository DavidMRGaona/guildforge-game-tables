<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Feature\SiteNotifications;

use App\Application\Mail\Services\MailSubjectMuteServiceInterface;
use App\Application\SiteNotifications\DTOs\SiteNotificationItemDTO;
use App\Application\SiteNotifications\Services\SiteNotificationServiceInterface;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Modules\GameTables\Domain\Enums\GameMasterRole;
use Modules\GameTables\Domain\Enums\ParticipantStatus;
use Modules\GameTables\Filament\Resources\CampaignResource;
use Modules\GameTables\Filament\Resources\GameTableResource;
use Modules\GameTables\Infrastructure\Mail\GameTablesMailSubjects;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\CampaignInvitationModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\CampaignModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameMasterModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameSystemModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameTableModel;
use Modules\GameTables\Notifications\CampaignInvitationNotification;
use Modules\GameTables\Notifications\CampaignJoinRequestAcceptedNotification;
use Modules\GameTables\Notifications\CampaignJoinRequestReceivedNotification;
use Modules\GameTables\Notifications\CampaignModerationSubmittedNotification;
use Modules\GameTables\Notifications\CampaignSessionScheduledNotification;
use Modules\GameTables\Notifications\ModerationRejectedNotification;
use Modules\GameTables\Notifications\ModerationSubmittedNotification;
use Modules\GameTables\Notifications\ParticipantCancelledNotification;
use Modules\GameTables\Notifications\ParticipantRegisteredNotification;
use Modules\GameTables\Notifications\RegistrationConfirmation;
use Modules\GameTables\Notifications\TableCancelledNotification;
use Tests\Support\Modules\ModuleTestCase;

/**
 * The module's notices in the core's notification centre: stored for accounts only, with a path
 * of the site, checked again when they are delivered, grouped for the direction and forgotten
 * when their table or campaign is deleted.
 */
final class SiteNoticesTest extends ModuleTestCase
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

    public function test_a_sign_up_notice_is_stored_for_the_account_with_the_table_path(): void
    {
        $table = $this->table();

        $this->player->notify($this->registration($table));

        $item = $this->only($this->player);
        $this->assertSame('Inscripción confirmada en «La tumba»', $item->title);
        $this->assertSame('/mesas/la-tumba', $item->url);
        $this->assertSame('15/11/2026 18:00 · Sala de juegos', $item->body);
        $this->assertSame('game-tables.sign-ups', $item->category);
    }

    public function test_a_guest_address_gets_the_mail_and_nothing_on_the_site(): void
    {
        $table = $this->table();

        Notification::route('mail', 'guest@example.com')->notify($this->registration($table));

        $this->assertSame(0, DB::table('site_notifications')->count());
    }

    public function test_nothing_is_stored_once_the_person_can_no_longer_see_the_table(): void
    {
        $table = $this->table(['is_published' => false]);

        $this->player->notify($this->registration($table));

        $this->assertSame(0, $this->unread($this->player));
    }

    public function test_a_cancelled_table_still_leads_to_its_page(): void
    {
        $table = $this->table(['status' => 'cancelled']);

        $this->player->notify(new TableCancelledNotification(
            participantName: 'Mary Major',
            tableTitle: 'La tumba',
            tableDate: '15/11/2026 18:00',
            tableLocation: null,
            tableId: $table->id,
        ));

        $item = $this->only($this->player);
        $this->assertSame('Se ha cancelado la mesa «La tumba»', $item->title);
        $this->assertSame('/mesas/la-tumba', $item->url);
    }

    public function test_direction_notices_of_a_table_are_grouped_and_name_the_people(): void
    {
        $table = $this->table();
        $this->directs($table, $this->director);
        $other = UserModel::factory()->create(['name' => 'Ana Pérez']);

        $this->director->notify($this->registered($table, 'Mary Major', $this->player->id));
        $this->director->notify(new ParticipantCancelledNotification(
            participantName: 'Ana Pérez',
            tableId: $table->id,
            tableTitle: 'La tumba',
            tableDate: '15/11/2026 18:00',
            tableLocation: null,
            participantUserId: $other->id,
        ));

        $item = $this->only($this->director);
        $this->assertSame(2, $item->count);
        $this->assertSame('Ana Pérez y 1 más: novedades en «La tumba»', $item->title);
        $this->assertSame('/mesas/la-tumba', $item->url);
    }

    public function test_a_pending_sign_up_on_a_delegating_table_leads_its_director_to_manage_it(): void
    {
        $table = $this->table(['delegates_registrations' => true]);
        $this->directs($table, $this->director);

        $this->director->notify($this->registered($table, 'Mary Major', $this->player->id, url('/mesas/mis-mesas/la-tumba/inscritos')));

        $item = $this->only($this->director);
        $this->assertSame('Mary Major pide plaza en «La tumba»', $item->title);
        $this->assertSame('/mesas/mis-mesas/la-tumba/inscritos', $item->url);
    }

    public function test_someone_who_no_longer_directs_the_table_gets_no_notice(): void
    {
        $table = $this->table();

        $this->director->notify($this->registered($table, 'Mary Major', $this->player->id));

        $this->assertSame(0, $this->unread($this->director));
    }

    public function test_a_notice_about_the_person_themselves_is_not_stored(): void
    {
        $table = $this->table();
        $this->directs($table, $this->director);

        $this->director->notify($this->registered($table, 'John Doe', $this->director->id));

        $this->assertSame(0, $this->unread($this->director));
    }

    public function test_anonymizing_the_participant_forgets_the_direction_notice(): void
    {
        $table = $this->table();
        $this->directs($table, $this->director);
        $this->director->notify($this->registered($table, 'Mary Major', $this->player->id));

        app(SiteNotificationServiceInterface::class)->forgetActor($this->player->id);

        $this->assertSame(0, $this->unread($this->director));
    }

    public function test_deleting_the_table_forgets_its_notices(): void
    {
        $table = $this->table();
        $this->player->notify($this->registration($table));
        $this->assertSame(1, $this->unread($this->player));

        $table->delete();

        $this->assertSame(0, $this->unread($this->player));
    }

    public function test_the_mail_is_about_the_table(): void
    {
        $table = $this->table();

        $subject = $this->registration($table)->mailSubject();

        $this->assertNotNull($subject);
        $this->assertSame(GameTablesMailSubjects::TABLE, $subject->type);
        $this->assertSame($table->id, $subject->id);
        $this->assertSame('La tumba', $subject->label);
    }

    public function test_campaign_notices_lead_to_the_campaign_and_are_forgotten_with_it(): void
    {
        $campaign = $this->campaign();

        $this->player->notify(new CampaignJoinRequestAcceptedNotification(
            userName: 'Mary Major',
            campaignTitle: 'Los ecos',
            campaignSlug: 'los-ecos',
            sessions: [],
            campaignId: $campaign->id,
        ));

        $item = $this->only($this->player);
        $this->assertSame('Ya formas parte de «Los ecos»', $item->title);
        $this->assertSame('/campanas/los-ecos', $item->url);

        $campaign->delete();

        $this->assertSame(0, $this->unread($this->player));
    }

    public function test_a_join_request_leads_the_direction_to_the_roster(): void
    {
        $campaign = $this->campaign(['created_by' => $this->director->id, 'frontend_creation_status' => 'approved']);

        $this->director->notify(new CampaignJoinRequestReceivedNotification(
            userName: 'John Doe',
            campaignId: $campaign->id,
            campaignTitle: 'Los ecos',
            playerName: 'Mary Major',
            message: 'Me encantaría jugar',
            playerId: $this->player->id,
        ));

        $item = $this->only($this->director);
        $this->assertSame('Mary Major pide unirse a «Los ecos»', $item->title);
        $this->assertSame("/campanas/mis-campanas/{$campaign->id}/plantilla", $item->url);
        $this->assertSame('Me encantaría jugar', $item->body);
    }

    public function test_a_new_session_is_about_its_campaign(): void
    {
        $campaign = $this->campaign();
        $table = $this->table(['campaign_id' => $campaign->id]);
        $notification = new CampaignSessionScheduledNotification(
            userName: 'Mary Major',
            campaignTitle: 'Los ecos',
            tableId: $table->id,
            tableTitle: 'La tumba',
            tableDate: '15/11/2026 18:00',
            tableLocation: null,
            waiting: false,
            campaignId: $campaign->id,
        );

        $this->player->notify($notification);

        $this->assertSame('/mesas/la-tumba', $this->only($this->player)->url);
        $this->assertSame(GameTablesMailSubjects::CAMPAIGN, $notification->mailSubject()?->type);
        $this->assertSame($campaign->id, $notification->mailSubject()->id);
    }

    public function test_an_invitation_leads_to_the_profile_tab_and_never_to_its_token(): void
    {
        $campaign = $this->campaign();
        $invitation = $this->invitation($campaign, $this->player->email);

        $this->player->notify($this->invitationNotice($campaign, $invitation->id));

        $item = $this->only($this->player);
        $this->assertSame('John Doe te invita a la campaña «Los ecos»', $item->title);
        $this->assertSame('/perfil?tab=gametables-invitations', $item->url);
        $this->assertStringNotContainsString(str_repeat('a', 64), (string) DB::table('site_notifications')->value('url'));
    }

    public function test_an_invitation_that_is_no_longer_pending_gives_no_notice(): void
    {
        $campaign = $this->campaign();
        $invitation = $this->invitation($campaign, 'someone-else@example.com');

        $this->player->notify($this->invitationNotice($campaign, $invitation->id));

        $this->assertSame(0, $this->unread($this->player));
    }

    public function test_a_rejected_proposal_leads_its_author_to_edit_it(): void
    {
        $table = $this->table(['created_by' => $this->player->id, 'is_published' => false, 'status' => 'draft', 'frontend_creation_status' => 'rejected']);

        $this->player->notify(new ModerationRejectedNotification(
            tableId: $table->id,
            tableTitle: 'La tumba',
            userName: 'Mary Major',
            reason: 'Falta la sinopsis',
            tableSlug: 'la-tumba',
        ));

        $item = $this->only($this->player);
        $this->assertSame('Tu mesa «La tumba» no ha sido aprobada', $item->title);
        $this->assertSame('/mesas/mis-mesas/la-tumba/editar', $item->url);
        $this->assertSame('Falta la sinopsis', $item->body);
    }

    public function test_proposals_to_review_lead_moderators_to_the_panel_in_one_group(): void
    {
        $this->onThePanel();
        $moderator = $this->moderator();
        $table = $this->table(['created_by' => $this->player->id, 'is_published' => false, 'status' => 'draft', 'frontend_creation_status' => 'pending_review']);
        $campaign = $this->campaign(['created_by' => $this->player->id, 'is_published' => false, 'frontend_creation_status' => 'pending_review']);

        $moderator->notify(new ModerationSubmittedNotification(tableId: $table->id, tableTitle: 'La tumba', creatorName: 'Mary Major', creatorId: $this->player->id));
        $moderator->notify(new CampaignModerationSubmittedNotification(campaignId: $campaign->id, campaignTitle: 'Los ecos', creatorName: 'Mary Major', creatorId: $this->player->id));

        $item = $this->only($moderator);
        $this->assertSame(2, $item->count);
        $this->assertSame('2 propuestas pendientes de revisar', $item->title);
        $this->assertStringStartsWith('/admin/', $item->url);
        $this->assertStringContainsString($campaign->id, $item->url);
    }

    public function test_someone_who_cannot_moderate_gets_no_review_notice(): void
    {
        $this->onThePanel();
        $table = $this->table(['created_by' => $this->player->id, 'is_published' => false, 'status' => 'draft', 'frontend_creation_status' => 'pending_review']);

        $this->director->notify(new ModerationSubmittedNotification(tableId: $table->id, tableTitle: 'La tumba', creatorName: 'Mary Major', creatorId: $this->player->id));

        $this->assertSame(0, $this->unread($this->director));
    }

    public function test_a_proposal_already_reviewed_gives_no_review_notice(): void
    {
        $this->onThePanel();
        $moderator = $this->moderator();
        $table = $this->table(['created_by' => $this->player->id, 'frontend_creation_status' => 'approved']);

        $moderator->notify(new ModerationSubmittedNotification(tableId: $table->id, tableTitle: 'La tumba', creatorName: 'Mary Major', creatorId: $this->player->id));

        $this->assertSame(0, $this->unread($moderator));
    }

    public function test_forgetting_the_subject_is_what_deleting_calls(): void
    {
        $table = $this->table();
        $this->player->notify($this->registration($table));

        app(MailSubjectMuteServiceInterface::class)->forgetSubject(GameTablesMailSubjects::TABLE, $table->id);

        $this->assertSame(0, $this->unread($this->player));
    }

    public function test_deleting_the_author_account_forgets_the_notices_of_its_tables(): void
    {
        $author = UserModel::factory()->create();
        $table = $this->table(['created_by' => $author->id]);
        $this->player->notify($this->registration($table));

        $author->forceDelete();

        $this->assertSame(0, $this->unread($this->player));
    }

    private function registration(GameTableModel $table): RegistrationConfirmation
    {
        return new RegistrationConfirmation(
            participantName: 'Mary Major',
            tableId: $table->id,
            tableTitle: 'La tumba',
            tableDate: '15/11/2026 18:00',
            tableLocation: 'Sala de juegos',
            role: 'player',
            status: ParticipantStatus::Confirmed,
        );
    }

    private function registered(GameTableModel $table, string $name, ?string $userId, ?string $manageUrl = null): ParticipantRegisteredNotification
    {
        return new ParticipantRegisteredNotification(
            participantName: $name,
            tableId: $table->id,
            tableTitle: 'La tumba',
            tableDate: '15/11/2026 18:00',
            tableLocation: null,
            role: 'player',
            manageUrl: $manageUrl,
            participantUserId: $userId,
        );
    }

    private function invitationNotice(CampaignModel $campaign, string $invitationId): CampaignInvitationNotification
    {
        return new CampaignInvitationNotification(
            inviterName: 'John Doe',
            campaignTitle: 'Los ecos',
            campaignSlug: 'los-ecos',
            token: str_repeat('a', 64),
            expiresAt: '27/11/2026',
            legalText: 'Aviso legal de prueba.',
            invitationId: $invitationId,
            campaignId: $campaign->id,
            inviterId: $this->director->id,
        );
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

    private function invitation(CampaignModel $campaign, string $email): CampaignInvitationModel
    {
        return CampaignInvitationModel::create([
            'id' => (string) Str::uuid(),
            'campaign_id' => $campaign->id,
            'email' => mb_strtolower($email),
            'invited_by_user_id' => $this->director->id,
            'inviter_name' => 'John Doe',
            'token_hash' => hash('sha256', str_repeat('a', 64)),
            'expires_at' => now()->addDays(14),
            'send_count' => 1,
            'last_sent_at' => now(),
        ]);
    }

    /**
     * The review mails and notices link to the panel's pages, registered by the panel.
     */
    private function onThePanel(): void
    {
        $this->registerOnAdminPanel([CampaignResource::class, GameTableResource::class]);
    }

    private function moderator(): UserModel
    {
        return UserModel::factory()->admin()->create(['name' => 'Moderadora']);
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
