<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Feature\Filament;

use App\Infrastructure\Persistence\Eloquent\Models\PermissionModel;
use App\Infrastructure\Persistence\Eloquent\Models\RoleModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Carbon\CarbonImmutable;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Modules\GameTables\Domain\Enums\ExperienceLevel;
use Modules\GameTables\Domain\Enums\JoinRequestStatus;
use Modules\GameTables\Domain\Events\CampaignJoinRequestAccepted;
use Modules\GameTables\Domain\Events\CampaignJoinRequestRejected;
use Modules\GameTables\Domain\Events\CampaignRosterMemberRemoved;
use Modules\GameTables\Domain\Repositories\CampaignRosterRepositoryInterface;
use Modules\GameTables\Domain\ValueObjects\CampaignId;
use Modules\GameTables\Filament\Resources\CampaignResource;
use Modules\GameTables\Filament\Resources\CampaignResource\Pages\EditCampaign;
use Modules\GameTables\Filament\Resources\CampaignResource\RelationManagers\InvitationsRelationManager;
use Modules\GameTables\Filament\Resources\CampaignResource\RelationManagers\JoinRequestsRelationManager;
use Modules\GameTables\Filament\Resources\CampaignResource\RelationManagers\RosterRelationManager;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\CampaignInvitationModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\CampaignJoinRequestModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\CampaignModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameSystemModel;
use Tests\Support\Modules\InteractsWithModulePanel;
use Tests\Support\Modules\ModuleTestCase;

/**
 * The campaign roster in the panel: who may ask to join, the maximum against the roster, and the
 * roster and requests tabs, which only act through the roster service.
 */
final class CampaignRosterPanelTest extends ModuleTestCase
{
    use InteractsWithModulePanel;

    protected ?string $moduleName = 'game-tables';

    protected bool $autoEnableModule = true;

    private UserModel $admin;

    private GameSystemModel $system;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registerOnAdminPanel([CampaignResource::class]);
        $this->admin = $this->actingAsPanelAdmin();
        config()->set('modules.settings.game-tables', []);
        Notification::fake();

        $this->system = GameSystemModel::create([
            'id' => (string) Str::uuid(),
            'name' => 'Sistema de prueba',
            'slug' => 'sistema-de-prueba',
            'is_active' => true,
        ]);
    }

    public function test_the_access_field_saves_members_only(): void
    {
        $campaign = $this->campaign();

        $this->edit($campaign)
            ->fillForm(['roster_access' => 'members_only'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('members_only', $campaign->refresh()->roster_access->value);
    }

    public function test_the_language_and_experience_level_fields_save(): void
    {
        $campaign = $this->campaign();

        $this->edit($campaign)
            ->assertFormSet(['language' => null, 'experience_level' => null])
            ->fillForm(['language' => 'en', 'experience_level' => 'advanced'])
            ->call('save')
            ->assertHasNoFormErrors();

        $campaign->refresh();
        $this->assertSame('en', $campaign->language);
        $this->assertSame(ExperienceLevel::Advanced, $campaign->experience_level);
    }

    public function test_a_maximum_below_the_roster_is_refused(): void
    {
        $campaign = $this->campaign();
        $this->member($campaign, 'John Doe');
        $this->member($campaign, 'Jane Doe');
        $this->member($campaign, 'Richard Roe');

        $component = $this->edit($campaign)
            ->fillForm(['max_players' => 2])
            ->call('save')
            ->assertHasFormErrors(['max_players']);

        $this->assertSame(
            ['El máximo de jugadores no puede ser menor que la plantilla actual (3).'],
            $component->errors()->get('data.max_players'),
        );

        $this->edit($campaign)
            ->fillForm(['max_players' => 3])
            ->call('save')
            ->assertHasNoFormErrors();
    }

    public function test_panel_invites_by_email(): void
    {
        $campaign = $this->campaign();

        $this->invitations($campaign)
            ->callAction(TestAction::make('invite')->table(), data: ['email' => 'John@Example.com', 'consent' => true])
            ->assertHasNoFormErrors()
            ->assertNotified('Invitación enviada a john@example.com.');

        $invitation = CampaignInvitationModel::query()->where('campaign_id', $campaign->id)->sole();
        $this->assertSame('john@example.com', $invitation->email);
        $this->invitations($campaign)->assertCanSeeTableRecords([$invitation]);
    }

    public function test_panel_invite_requires_consent(): void
    {
        $campaign = $this->campaign();

        $this->invitations($campaign)
            ->callAction(TestAction::make('invite')->table(), data: ['email' => 'john@example.com', 'consent' => false])
            ->assertHasFormErrors(['consent']);

        $this->assertSame(0, CampaignInvitationModel::query()->count());
    }

    public function test_panel_resends_and_cancels(): void
    {
        $campaign = $this->campaign();
        $this->invitations($campaign)
            ->callAction(TestAction::make('invite')->table(), data: ['email' => 'john@example.com', 'consent' => true]);
        $invitation = CampaignInvitationModel::query()->where('campaign_id', $campaign->id)->sole();
        // The service waits 24 hours between sends
        $this->travel(25)->hours();

        $this->invitations($campaign)
            ->callTableAction('resend', $invitation)
            ->assertNotified('Invitación reenviada.');
        $this->assertSame(2, $invitation->refresh()->send_count);

        $this->invitations($campaign)
            ->callTableAction('cancel', $invitation)
            ->assertNotified('Invitación cancelada.');
        $this->assertNull(CampaignInvitationModel::query()->find($invitation->id));
    }

    public function test_roster_tab_has_no_add_action(): void
    {
        $this->roster($this->campaign())->assertActionDoesNotExist(TestAction::make('add')->table());
    }

    public function test_expired_invitations_are_not_listed(): void
    {
        $campaign = $this->campaign();
        $live = $this->invitation($campaign, 'john@example.com', CarbonImmutable::now()->addDays(3));
        $expired = $this->invitation($campaign, 'jane@example.com', CarbonImmutable::now()->subDay());

        $this->invitations($campaign)
            ->assertCanSeeTableRecords([$live])
            ->assertCanNotSeeTableRecords([$expired]);
    }

    public function test_the_tab_warns_when_pending_invitations_outnumber_free_seats(): void
    {
        $warning = 'Hay más invitaciones pendientes que plazas libres; entrará quien acepte primero.';
        $campaign = $this->campaign(maxPlayers: 3);
        $this->member($campaign, 'Richard Roe');
        $this->invitation($campaign, 'john@example.com', CarbonImmutable::now()->addDays(3));
        $this->invitation($campaign, 'jane@example.com', CarbonImmutable::now()->addDays(3));

        // Two seats, two invitations: everyone fits
        $this->invitations($campaign)->assertDontSee($warning);

        // An expired one does not count
        $this->invitation($campaign, 'old@example.com', CarbonImmutable::now()->subDay());
        $this->invitations($campaign)->assertDontSee($warning);

        $this->invitation($campaign, 'mary@example.com', CarbonImmutable::now()->addDays(3));
        $this->invitations($campaign)->assertSee($warning);
    }

    public function test_the_tab_never_warns_without_a_maximum(): void
    {
        $campaign = $this->campaign();
        $this->invitation($campaign, 'john@example.com', CarbonImmutable::now()->addDays(3));
        $this->invitation($campaign, 'jane@example.com', CarbonImmutable::now()->addDays(3));

        $this->invitations($campaign)->assertDontSee('Hay más invitaciones pendientes que plazas libres');
    }

    public function test_invitations_tab_follows_the_campaign_update_permission(): void
    {
        $campaign = $this->campaign();
        $invitation = $this->invitation($campaign, 'john@example.com', CarbonImmutable::now()->addDays(3));

        $this->actingAs($this->editorWith(['gametables:campaigns.view_any', 'gametables:campaigns.view']));
        $this->assertFalse(InvitationsRelationManager::canViewForRecord($campaign, EditCampaign::class));
        try {
            $this->invitations($campaign)
                ->set('mountedActions', [['name' => 'cancel', 'arguments' => [], 'context' => ['table' => true, 'recordKey' => $invitation->getKey()], 'data' => []]])
                ->call('callMountedAction');
        } catch (\Throwable) {
            // Refused before reaching the action
        }
        $this->assertNotNull(CampaignInvitationModel::query()->find($invitation->id));

        $this->actingAs($this->editorWith(['gametables:campaigns.view_any', 'gametables:campaigns.view', 'gametables:campaigns.update']));
        $this->assertTrue(InvitationsRelationManager::canViewForRecord($campaign, EditCampaign::class));
        $this->invitations($campaign)
            ->assertCanSeeTableRecords([$invitation])
            ->callTableAction('cancel', $invitation)
            ->assertNotified('Invitación cancelada.');
    }

    public function test_removing_a_player_announces_it(): void
    {
        Event::fake([CampaignRosterMemberRemoved::class]);
        $campaign = $this->campaign();
        $user = $this->member($campaign, 'John Doe');

        $this->roster($campaign)
            ->callTableAction('remove', $user)
            ->assertNotified('Jugador quitado de la plantilla.');

        $this->assertFalse(app(CampaignRosterRepositoryInterface::class)->contains(new CampaignId($campaign->id), $user->id));
        Event::assertDispatched(CampaignRosterMemberRemoved::class, fn (CampaignRosterMemberRemoved $event): bool => $event->userId === $user->id);
    }

    public function test_an_anonymized_member_is_not_listed(): void
    {
        $campaign = $this->campaign();
        $john = $this->member($campaign, 'John Doe');
        $ghost = $this->member($campaign, 'Jane Doe');
        $ghost->forceFill(['anonymized_at' => CarbonImmutable::now()])->save();

        $this->roster($campaign)
            ->assertCanSeeTableRecords([$john])
            ->assertCanNotSeeTableRecords([$ghost]);
    }

    public function test_accepting_a_request_goes_through_the_service(): void
    {
        Event::fake([CampaignJoinRequestAccepted::class]);
        $campaign = $this->campaign();
        $user = $this->user('John Doe');
        $request = $this->request($campaign, $user);

        $this->requests($campaign)
            ->callTableAction('accept', $request)
            ->assertNotified('Solicitud aceptada.');

        $this->assertSame(JoinRequestStatus::Accepted, $request->refresh()->status);
        $this->assertTrue(app(CampaignRosterRepositoryInterface::class)->contains(new CampaignId($campaign->id), $user->id));
        Event::assertDispatched(CampaignJoinRequestAccepted::class);
    }

    public function test_rejecting_a_request_keeps_the_reason(): void
    {
        Event::fake([CampaignJoinRequestRejected::class]);
        $campaign = $this->campaign();
        $request = $this->request($campaign, $this->user('John Doe'));

        $this->requests($campaign)
            ->callTableAction('reject', $request, data: ['reason' => 'Plantilla completa por ahora'])
            ->assertNotified('Solicitud rechazada.');

        $request->refresh();
        $this->assertSame(JoinRequestStatus::Rejected, $request->status);
        $this->assertSame('Plantilla completa por ahora', $request->decision_reason);
        Event::assertDispatched(CampaignJoinRequestRejected::class);
    }

    public function test_the_reason_is_capped_at_500_characters(): void
    {
        $campaign = $this->campaign();
        $request = $this->request($campaign, $this->user('John Doe'));

        $this->requests($campaign)
            ->callTableAction('reject', $request, data: ['reason' => str_repeat('a', 501)])
            ->assertHasTableActionErrors(['reason']);

        $this->assertSame(JoinRequestStatus::Pending, $request->refresh()->status);
    }

    public function test_only_pending_requests_get_the_actions_and_the_list_defaults_to_pending(): void
    {
        $campaign = $this->campaign();
        $pending = $this->request($campaign, $this->user('John Doe'));
        $rejected = $this->request($campaign, $this->user('Jane Doe'), JoinRequestStatus::Rejected);

        $this->requests($campaign)
            ->assertCanSeeTableRecords([$pending])
            ->assertCanNotSeeTableRecords([$rejected])
            ->assertTableActionVisible('accept', $pending)
            ->filterTable('status', 'rejected')
            ->assertCanSeeTableRecords([$rejected])
            ->assertTableActionHidden('accept', $rejected)
            ->assertTableActionHidden('reject', $rejected);
    }

    public function test_a_request_of_an_anonymized_account_is_hidden(): void
    {
        $campaign = $this->campaign();
        $ghost = $this->user('Richard Roe', anonymized: true);
        $request = $this->request($campaign, $ghost);

        $this->requests($campaign)->assertCanNotSeeTableRecords([$request]);
    }

    public function test_the_tabs_follow_the_update_permission_without_the_users_permission(): void
    {
        Event::fake([CampaignRosterMemberRemoved::class, CampaignJoinRequestAccepted::class]);
        $campaign = $this->campaign();
        $member = $this->member($campaign, 'John Doe');
        $request = $this->request($campaign, $this->user('Jane Doe'));
        $this->actingAs($this->editorWith(['gametables:campaigns.view_any', 'gametables:campaigns.view', 'gametables:campaigns.update']));

        $this->assertTrue(RosterRelationManager::canViewForRecord($campaign, EditCampaign::class));
        $this->assertTrue(JoinRequestsRelationManager::canViewForRecord($campaign, EditCampaign::class));

        $this->roster($campaign)
            ->assertCanSeeTableRecords([$member])
            ->callTableAction('remove', $member)
            ->assertNotified('Jugador quitado de la plantilla.');
        $this->requests($campaign)
            ->assertCanSeeTableRecords([$request])
            ->callTableAction('accept', $request)
            ->assertNotified('Solicitud aceptada.');

        Event::assertDispatched(CampaignRosterMemberRemoved::class);
        Event::assertDispatched(CampaignJoinRequestAccepted::class);
    }

    /**
     * The campaign's update permission opens the tabs (viewAny, view) and nothing else: a stock
     * edit, delete or create action added later would not inherit it.
     */
    public function test_the_update_permission_only_answers_for_viewing(): void
    {
        $campaign = $this->campaign();
        $member = $this->member($campaign, 'John Doe');
        $request = $this->request($campaign, $this->user('Jane Doe'));
        $this->actingAs($this->editorWith(['gametables:campaigns.view_any', 'gametables:campaigns.view', 'gametables:campaigns.update']));

        foreach ([[$this->roster($campaign), $member], [$this->requests($campaign), $request]] as [$tab, $record]) {
            $manager = $tab->instance();

            $this->assertTrue($manager->getAuthorizationResponse('viewAny')->allowed());
            $this->assertTrue($manager->getAuthorizationResponse('view', $record)->allowed());
            foreach (['create', 'update', 'delete', 'deleteAny', 'attach', 'detach', 'associate', 'reorder'] as $ability) {
                $this->assertFalse($manager->getAuthorizationResponse($ability, $record)->allowed(), $ability);
            }
        }
    }

    public function test_a_user_without_the_update_permission_sees_no_tab_and_forged_calls_change_nothing(): void
    {
        Event::fake([CampaignRosterMemberRemoved::class, CampaignJoinRequestAccepted::class]);
        $campaign = $this->campaign();
        $member = $this->member($campaign, 'John Doe');
        $request = $this->request($campaign, $this->user('Jane Doe'));
        $this->actingAs($this->editorWith(['gametables:campaigns.view_any', 'gametables:campaigns.view']));

        $this->assertFalse(RosterRelationManager::canViewForRecord($campaign, EditCampaign::class));
        $this->assertFalse(JoinRequestsRelationManager::canViewForRecord($campaign, EditCampaign::class));

        // Hiding the buttons is not enough: the server refuses the calls as well (the component
        // itself may refuse to render, which is as good)
        $forged = [
            fn () => $this->roster($campaign)
                ->set('mountedActions', [['name' => 'remove', 'arguments' => [], 'context' => ['table' => true, 'recordKey' => $member->getKey()], 'data' => []]])
                ->call('callMountedAction'),
            fn () => $this->invitations($campaign)
                ->set('mountedActions', [['name' => 'invite', 'arguments' => [], 'context' => ['table' => true], 'data' => ['email' => 'mary@example.com', 'consent' => true]]])
                ->call('callMountedAction'),
            fn () => $this->requests($campaign)
                ->set('mountedActions', [['name' => 'accept', 'arguments' => [], 'context' => ['table' => true, 'recordKey' => $request->getKey()], 'data' => []]])
                ->call('callMountedAction'),
        ];

        foreach ($forged as $call) {
            try {
                $call();
            } catch (\Throwable) {
                // Refused before reaching the action
            }
        }

        $repository = app(CampaignRosterRepositoryInterface::class);
        $this->assertTrue($repository->contains(new CampaignId($campaign->id), $member->id));
        $this->assertSame(0, CampaignInvitationModel::query()->count());
        $this->assertSame(JoinRequestStatus::Pending, $request->refresh()->status);
        Event::assertNotDispatched(CampaignRosterMemberRemoved::class);
        Event::assertNotDispatched(CampaignJoinRequestAccepted::class);
    }

    /**
     * @return Testable<EditCampaign>
     */
    private function edit(CampaignModel $campaign): Testable
    {
        return Livewire::test(EditCampaign::class, ['record' => $campaign->getRouteKey()]);
    }

    /**
     * @return Testable<RosterRelationManager>
     */
    private function roster(CampaignModel $campaign): Testable
    {
        return Livewire::test(RosterRelationManager::class, ['ownerRecord' => $campaign, 'pageClass' => EditCampaign::class]);
    }

    /**
     * @return Testable<InvitationsRelationManager>
     */
    private function invitations(CampaignModel $campaign): Testable
    {
        return Livewire::test(InvitationsRelationManager::class, ['ownerRecord' => $campaign, 'pageClass' => EditCampaign::class]);
    }

    private function invitation(CampaignModel $campaign, string $email, CarbonImmutable $expiresAt): CampaignInvitationModel
    {
        return CampaignInvitationModel::create([
            'campaign_id' => $campaign->id,
            'email' => $email,
            'invited_by_user_id' => $this->admin->id,
            'inviter_name' => $this->admin->name,
            'token_hash' => hash('sha256', Str::random(64)),
            'expires_at' => $expiresAt,
            'send_count' => 1,
            'last_sent_at' => CarbonImmutable::now(),
        ]);
    }

    /**
     * @return Testable<JoinRequestsRelationManager>
     */
    private function requests(CampaignModel $campaign): Testable
    {
        return Livewire::test(JoinRequestsRelationManager::class, ['ownerRecord' => $campaign, 'pageClass' => EditCampaign::class]);
    }

    private function campaign(?int $maxPlayers = null): CampaignModel
    {
        return CampaignModel::create([
            'id' => (string) Str::uuid(),
            'game_system_id' => $this->system->id,
            'created_by' => $this->admin->id,
            'title' => 'Campaña de prueba',
            'slug' => Str::random(10),
            'frequency' => 'weekly',
            'status' => 'recruiting',
            'max_players' => $maxPlayers,
            'accepts_new_players' => true,
            'is_published' => true,
        ]);
    }

    private function user(string $name, ?string $email = null, bool $anonymized = false): UserModel
    {
        return UserModel::factory()->create([
            'name' => $name,
            'email' => $email ?? Str::random(8).'@example.com',
            'anonymized_at' => $anonymized ? CarbonImmutable::now() : null,
        ]);
    }

    private function member(CampaignModel $campaign, string $name): UserModel
    {
        $user = $this->user($name);
        app(CampaignRosterRepositoryInterface::class)->add(new CampaignId($campaign->id), $user->id, CarbonImmutable::now());

        return $user;
    }

    private function request(CampaignModel $campaign, UserModel $user, JoinRequestStatus $status = JoinRequestStatus::Pending): CampaignJoinRequestModel
    {
        return CampaignJoinRequestModel::create([
            'campaign_id' => $campaign->id,
            'user_id' => $user->id,
            'status' => $status->value,
            'message' => 'Me gustaría jugar',
        ]);
    }

    /**
     * @param  list<string>  $permissionKeys
     */
    private function editorWith(array $permissionKeys): UserModel
    {
        $role = RoleModel::create(['name' => 'tester_'.uniqid(), 'display_name' => 'Permissions tester']);

        foreach ($permissionKeys as $key) {
            $permission = PermissionModel::firstOrCreate(
                ['key' => $key],
                ['label' => $key, 'resource' => 'gametables', 'action' => Str::afterLast($key, '.'), 'module' => 'gametables'],
            );
            $role->permissions()->attach($permission->id);
        }

        $user = UserModel::factory()->editor()->create();
        $user->roles()->attach($role->id);

        return $user;
    }
}
