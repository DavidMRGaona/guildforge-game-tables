<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Feature\Http\Controllers;

use App\Application\Services\SettingsServiceInterface;
use App\Http\Middleware\HandleInertiaRequests;
use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\GameTables\Domain\Entities\EventGameTableConfig;
use Modules\GameTables\Domain\Enums\LocationMode;
use Modules\GameTables\Domain\Enums\SchedulingMode;
use Modules\GameTables\Domain\Repositories\EventGameTableConfigRepositoryInterface;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\CampaignModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameSystemModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameTableModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\ParticipantModel;
use Tests\Support\Modules\ModuleTestCase;

/**
 * What the pages give the module's buttons to gate them on email verification, and what the
 * server does with a request sent anyway. An account that must verify still gets the eligibility
 * it would have (so the registration and creation buttons can say why they are disabled) and
 * auth.user.mustVerifyEmail; its registrations, cancellations and creations land on the notice
 * (Inertia) or get the verification 403 (JSON) and change nothing. Guests, verified accounts,
 * accounts created before the cutoff and every account while the setting is off work as before.
 */
final class EmailVerificationGateTest extends ModuleTestCase
{
    protected ?string $moduleName = 'game-tables';

    protected bool $autoEnableModule = true;

    private const string CUTOFF = '2026-01-01T00:00:00+00:00';

    private GameTableModel $table;

    protected function setUp(): void
    {
        parent::setUp();

        app('router')->getRoutes()->refreshNameLookups();
        app(SettingsServiceInterface::class)->set('email_verification_enforced_since', self::CUTOFF);
        config()->set('modules.settings.game-tables', [
            'frontend_creation' => [
                'enabled' => true,
                'access_level' => 'registered',
                'allowed_content' => 'both',
            ],
        ]);

        $gameSystem = GameSystemModel::create([
            'id' => (string) Str::uuid(),
            'name' => 'Sistema de prueba',
            'slug' => 'sistema-de-prueba',
            'is_active' => true,
        ]);
        $this->table = GameTableModel::create([
            'id' => (string) Str::uuid(),
            'game_system_id' => $gameSystem->id,
            'created_by' => UserModel::factory()->create()->id,
            'title' => 'Mesa de prueba',
            'slug' => 'mesa-de-prueba',
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
        ]);
    }

    // Table page: the registration button's props

    public function test_the_table_page_tells_an_account_that_must_verify_to_disable_the_button(): void
    {
        $this->requireVerification();

        $this->actingAs($this->mustVerifyUser())
            ->get(route('gametables.show', $this->table->slug))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('auth.user.mustVerifyEmail', true)
                ->where('eligibility.eligible', true)
                ->where('guestEligibility', null)
                ->where('userRegistration', null));
    }

    public function test_the_table_page_tells_a_registered_account_that_must_verify_about_its_registration(): void
    {
        $this->requireVerification();
        $user = $this->mustVerifyUser();
        $participant = $this->registerConfirmed($user);

        $this->actingAs($user)
            ->get(route('gametables.show', $this->table->slug))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('auth.user.mustVerifyEmail', true)
                ->where('userRegistration.id', $participant->id)
                ->where('userRegistration.status', 'confirmed'));
    }

    public function test_the_table_page_leaves_the_button_as_before_for_accounts_that_need_not_verify(): void
    {
        foreach ($this->accountsThatNeedNotVerify() as $user) {
            $this->actingAs($user)
                ->get(route('gametables.show', $this->table->slug))
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->where('auth.user.mustVerifyEmail', false)
                    ->where('eligibility.eligible', true)
                    ->where('guestEligibility', null));
        }
    }

    public function test_the_table_page_offers_guest_registration_as_before(): void
    {
        $this->requireVerification();

        $this->get(route('gametables.show', $this->table->slug))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('auth.user', null)
                ->where('eligibility', null)
                ->where('guestEligibility.eligible', true));
    }

    // Registration and cancellation sent anyway

    public function test_a_registration_sent_anyway_by_inertia_lands_on_the_notice_and_registers_nothing(): void
    {
        $this->requireVerification();
        $user = $this->mustVerifyUser();

        $this->actingAs($user)
            ->withHeaders($this->inertiaHeaders())
            ->from(route('gametables.show', $this->table->slug))
            ->post(route('gametables.register', $this->table->slug), ['role' => 'player'])
            ->assertRedirect(route('verification.notice'));

        $this->assertFalse($this->hasRegistration($user));
    }

    public function test_a_registration_sent_anyway_as_json_gets_the_verification_403(): void
    {
        $this->requireVerification();
        $user = $this->mustVerifyUser();

        $this->actingAs($user)
            ->postJson(route('gametables.register', $this->table->slug), ['role' => 'player'])
            ->assertForbidden()
            ->assertHeader('X-Email-Verification-Required', '1')
            ->assertJsonPath('error', 'email_verification_required');

        $this->assertFalse($this->hasRegistration($user));
    }

    public function test_accounts_that_need_not_verify_register_as_before(): void
    {
        foreach ($this->accountsThatNeedNotVerify() as $label => $user) {
            $this->actingAs($user)
                ->withHeaders($this->inertiaHeaders())
                ->from(route('gametables.show', $this->table->slug))
                ->post(route('gametables.register', $this->table->slug), ['role' => 'player'])
                ->assertRedirect(route('gametables.show', $this->table->slug))
                ->assertSessionHas('success');

            $this->assertTrue($this->hasRegistration($user), $label);
        }
    }

    public function test_a_cancellation_sent_anyway_lands_on_the_notice_and_keeps_the_registration(): void
    {
        $this->requireVerification();
        $user = $this->mustVerifyUser();
        $participant = $this->registerConfirmed($user);

        $this->actingAs($user)
            ->withHeaders($this->inertiaHeaders())
            ->from(route('gametables.show', $this->table->slug))
            ->delete(route('gametables.cancel', $this->table->slug))
            ->assertRedirect(route('verification.notice'));

        $this->assertSame('confirmed', ParticipantModel::query()->findOrFail($participant->id)->status->value);
    }

    public function test_accounts_that_need_not_verify_cancel_as_before(): void
    {
        foreach ($this->accountsThatNeedNotVerify() as $label => $user) {
            $participant = $this->registerConfirmed($user);

            $this->actingAs($user)
                ->withHeaders($this->inertiaHeaders())
                ->from(route('gametables.show', $this->table->slug))
                ->delete(route('gametables.cancel', $this->table->slug))
                ->assertRedirect(route('gametables.show', $this->table->slug))
                ->assertSessionHas('success');

            $this->assertSame('cancelled', ParticipantModel::query()->findOrFail($participant->id)->status->value, $label);
        }
    }

    public function test_guest_registration_works_as_before_with_the_setting_on(): void
    {
        $this->requireVerification();

        $this->post(route('gametables.register-guest', $this->table->slug), [
            'first_name' => 'Invitada',
            'email' => 'invitada@example.com',
            'gdpr_consent' => true,
        ])->assertRedirect()->assertSessionHas('success');

        $this->assertTrue(ParticipantModel::query()
            ->where('game_table_id', $this->table->id)
            ->where('email', 'invitada@example.com')
            ->exists());
    }

    // Creation: the event page's button, the profile's links and the creation forms

    public function test_the_event_page_tells_an_account_that_must_verify_to_disable_the_create_button(): void
    {
        $this->requireVerification();
        $event = $this->eventWithTables();

        $this->actingAs($this->mustVerifyUser())
            ->get(route('events.show', $event->slug))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('auth.user.mustVerifyEmail', true)
                ->where('eventCreationEligibility.eligible', true)
                ->where('eventCreationEligibility.can_create_tables', true));
    }

    public function test_the_event_page_leaves_the_create_button_as_before_for_accounts_that_need_not_verify(): void
    {
        $event = $this->eventWithTables();

        foreach ($this->accountsThatNeedNotVerify() as $user) {
            $this->actingAs($user)
                ->get(route('events.show', $event->slug))
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->where('auth.user.mustVerifyEmail', false)
                    ->where('eventCreationEligibility.eligible', true)
                    ->where('eventCreationEligibility.can_create_tables', true));
        }
    }

    public function test_the_profile_tells_an_account_that_must_verify_to_disable_the_create_links(): void
    {
        $this->requireVerification();

        $this->actingAs($this->mustVerifyUser())
            ->get(route('profile.show'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('auth.user.mustVerifyEmail', true)
                ->where('profileCreatedTables.canCreate', true)
                ->where('profileCreatedCampaigns.canCreate', true));
    }

    public function test_the_profile_leaves_the_create_links_as_before_for_accounts_that_need_not_verify(): void
    {
        foreach ($this->accountsThatNeedNotVerify() as $user) {
            $this->actingAs($user)
                ->get(route('profile.show'))
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->where('auth.user.mustVerifyEmail', false)
                    ->where('profileCreatedTables.canCreate', true)
                    ->where('profileCreatedCampaigns.canCreate', true));
        }
    }

    public function test_the_creation_forms_send_an_account_that_must_verify_to_the_notice(): void
    {
        $this->requireVerification();
        $user = $this->mustVerifyUser();

        foreach (['gametables.create', 'campaigns.frontend-create', 'gametables.my-tables', 'campaigns.my-campaigns'] as $route) {
            $this->actingAs($user)
                ->withHeaders($this->inertiaHeaders())
                ->get(route($route))
                ->assertRedirect(route('verification.notice'));
        }
    }

    public function test_the_edit_forms_send_an_account_that_must_verify_to_the_notice_and_change_nothing(): void
    {
        $this->requireVerification();
        $user = $this->mustVerifyUser();
        $table = $this->tableDraftOf($user);
        $campaign = $this->campaignDraftOf($user);

        // The id form is the one rejection emails sent before 2.8.0 carry
        foreach ([route('gametables.edit', $table), "/mesas/mis-mesas/{$table->id}/editar", route('campaigns.frontend-edit', $campaign->id)] as $url) {
            $this->actingAs($user)
                ->withHeaders($this->inertiaHeaders())
                ->get($url)
                ->assertRedirect(route('verification.notice'));
        }

        foreach ([route('gametables.update', $table), route('campaigns.frontend-update', $campaign->id)] as $url) {
            $this->actingAs($user)
                ->withHeaders($this->inertiaHeaders())
                ->from(route('profile.show'))
                ->put($url, ['title' => 'Editada sin verificar'])
                ->assertRedirect(route('verification.notice'));
        }

        $this->assertSame('Mesa en borrador', GameTableModel::query()->findOrFail($table->id)->title);
        $this->assertSame('Campaña en borrador', CampaignModel::query()->findOrFail($campaign->id)->title);
    }

    public function test_authors_that_need_not_verify_open_the_edit_forms_as_before(): void
    {
        foreach ($this->accountsThatNeedNotVerify() as $user) {
            $table = $this->tableDraftOf($user);
            $campaign = $this->campaignDraftOf($user);

            $this->actingAs($user)
                ->get(route('gametables.edit', $table))
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page->component('GameTables/Edit', false));

            $this->actingAs($user)
                ->get(route('campaigns.frontend-edit', $campaign->id))
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page->component('Campaigns/Edit', false));
        }
    }

    public function test_a_creation_sent_anyway_lands_on_the_notice_and_creates_nothing(): void
    {
        $this->requireVerification();
        $user = $this->mustVerifyUser();

        foreach (['gametables.store', 'campaigns.frontend-store'] as $route) {
            $this->actingAs($user)
                ->withHeaders($this->inertiaHeaders())
                ->from(route('profile.show'))
                ->post(route($route), ['title' => 'Creada sin verificar'])
                ->assertRedirect(route('verification.notice'));
        }

        $this->assertFalse(GameTableModel::query()->where('created_by', $user->id)->exists());
        $this->assertFalse(CampaignModel::query()->where('created_by', $user->id)->exists());
    }

    public function test_accounts_that_need_not_verify_open_the_creation_forms_as_before(): void
    {
        foreach ($this->accountsThatNeedNotVerify() as $user) {
            $this->actingAs($user)
                ->get(route('gametables.create'))
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page->component('GameTables/Create', false));

            $this->actingAs($user)
                ->get(route('campaigns.frontend-create'))
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page->component('Campaigns/Create', false));
        }
    }

    public function test_the_manifest_requires_the_core_that_tells_the_pages_who_must_verify(): void
    {
        /** @var array{requires: array{core: string}} $manifest */
        $manifest = json_decode((string) file_get_contents(dirname(__DIR__, 4).'/module.json'), true, flags: JSON_THROW_ON_ERROR);

        // auth.user.mustVerifyEmail and useVerificationGate() (bundled into the module) come with core 3.10:
        // the module may require a later core, never an earlier one
        $this->assertMatchesRegularExpression('/^\^\d+\.\d+$/', $manifest['requires']['core']);
        $this->assertTrue(version_compare(ltrim($manifest['requires']['core'], '^'), '3.10', '>='));
    }

    private function requireVerification(): void
    {
        app(SettingsServiceInterface::class)->set('auth_email_verification_required', '1');
    }

    private function mustVerifyUser(): UserModel
    {
        return UserModel::factory()->unverified()->create();
    }

    /**
     * A verified account and one created before the cutoff with the setting on, and a new
     * unverified one with the setting off (each case turns the setting on or off itself).
     *
     * @return iterable<string, UserModel>
     */
    private function accountsThatNeedNotVerify(): iterable
    {
        app(SettingsServiceInterface::class)->set('auth_email_verification_required', '0');
        yield 'setting off' => UserModel::factory()->unverified()->create();

        $this->requireVerification();
        yield 'verified' => UserModel::factory()->create();
        yield 'before the cutoff' => UserModel::factory()->unverified()->create(['created_at' => '2025-06-01 12:00:00']);
    }

    private function registerConfirmed(UserModel $user): ParticipantModel
    {
        return ParticipantModel::create([
            'id' => (string) Str::uuid(),
            'game_table_id' => $this->table->id,
            'user_id' => $user->id,
            'role' => 'player',
            'status' => 'confirmed',
            'confirmed_at' => now(),
        ]);
    }

    private function tableDraftOf(UserModel $user): GameTableModel
    {
        $id = (string) Str::uuid();

        return GameTableModel::create([
            'id' => $id,
            'game_system_id' => $this->table->game_system_id,
            'created_by' => $user->id,
            'title' => 'Mesa en borrador',
            'slug' => 'mesa-en-borrador-'.$id,
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

    private function campaignDraftOf(UserModel $user): CampaignModel
    {
        $id = (string) Str::uuid();

        return CampaignModel::create([
            'id' => $id,
            'game_system_id' => $this->table->game_system_id,
            'created_by' => $user->id,
            'title' => 'Campaña en borrador',
            'slug' => 'campana-en-borrador-'.$id,
            'frequency' => 'weekly',
            'status' => 'recruiting',
            'max_players' => 5,
            'is_published' => false,
            'frontend_creation_status' => 'draft',
        ]);
    }

    private function hasRegistration(UserModel $user): bool
    {
        return ParticipantModel::query()
            ->where('game_table_id', $this->table->id)
            ->where('user_id', $user->id)
            ->exists();
    }

    private function eventWithTables(): EventModel
    {
        $event = EventModel::factory()->published()->create();

        app(EventGameTableConfigRepositoryInterface::class)->save(new EventGameTableConfig(
            eventId: (string) $event->id,
            tablesEnabled: true,
            schedulingMode: SchedulingMode::FreeSchedule,
            timeSlots: [],
            locationMode: LocationMode::FreeChoice,
            fixedLocation: null,
            eligibilityOverride: null,
            earlyAccessEnabled: false,
            creationOpensAt: null,
            earlyAccessTier: null,
        ));

        return $event;
    }

    /**
     * @return array<string, string>
     */
    private function inertiaHeaders(): array
    {
        return [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => (string) app(HandleInertiaRequests::class)->version(request()),
            'X-Requested-With' => 'XMLHttpRequest',
        ];
    }
}
