<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Feature\Http\Controllers;

use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\GameTables\Application\DTOs\FrontendCreateGameTableDTO;
use Modules\GameTables\Application\Services\FrontendCreationServiceInterface;
use Modules\GameTables\Domain\Entities\EventGameTableConfig;
use Modules\GameTables\Domain\Enums\LocationMode;
use Modules\GameTables\Domain\Enums\SchedulingMode;
use Modules\GameTables\Domain\Enums\FrontendCreationStatus;
use Modules\GameTables\Domain\Enums\GameMasterRole;
use Modules\GameTables\Domain\Exceptions\SessionBelowRosterException;
use Modules\GameTables\Domain\Repositories\CampaignRosterRepositoryInterface;
use Modules\GameTables\Domain\Repositories\EventGameTableConfigRepositoryInterface;
use Modules\GameTables\Domain\ValueObjects\CampaignId;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\CampaignModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameMasterModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameSystemModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameTableModel;
use Tests\Support\Modules\ModuleTestCase;

/**
 * A session is created together with its campaign, chosen among the campaigns the person
 * directs; directing an approved campaign is enough to create its sessions even when the
 * general creation rules would refuse the person.
 */
final class CampaignSessionCreationTest extends ModuleTestCase
{
    private const string REQUIRED = 'Elige la campaña de la sesión.';

    private const string NOT_OWNED = 'Solo puedes vincular la mesa a una campaña que dirijas.';

    protected ?string $moduleName = 'game-tables';

    protected bool $autoEnableModule = true;

    private UserModel $jane;

    private GameSystemModel $gameSystem;

    private int $members = 0;

    protected function setUp(): void
    {
        parent::setUp();

        app('router')->getRoutes()->refreshNameLookups();
        $this->configure('permission');

        $this->jane = UserModel::factory()->create(['name' => 'Jane Doe', 'email' => 'jane@example.com']);
        $this->gameSystem = GameSystemModel::create([
            'id' => (string) Str::uuid(),
            'name' => 'Sistema de prueba',
            'slug' => 'sistema-de-prueba',
            'is_active' => true,
        ]);
    }

    public function test_a_director_without_creation_access_can_create_a_session(): void
    {
        $campaign = $this->campaignDirectedAsGm('Campaña de Jane');

        $this->actingAs($this->jane)
            ->post(route('gametables.store'), $this->sessionData('Sesión uno', $campaign->id))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('gametables.my-tables'));

        $table = GameTableModel::query()->where('title', 'Sesión uno')->first();
        $this->assertNotNull($table);
        $this->assertSame($campaign->id, $table->campaign_id);
        $this->assertSame('campaign_session', $table->table_type->value);
    }

    public function test_the_same_user_cannot_create_a_one_shot(): void
    {
        $campaign = $this->campaignDirectedAsGm('Campaña de Jane');

        $this->actingAs($this->jane)
            ->post(route('gametables.store'), [...$this->sessionData('Partida suelta', $campaign->id), 'table_type' => 'one_shot'])
            ->assertForbidden();

        $this->assertFalse(GameTableModel::query()->where('title', 'Partida suelta')->exists());
    }

    public function test_creation_disabled_blocks_directors_too(): void
    {
        $campaign = $this->campaignDirectedAsGm('Campaña de Jane');
        config()->set('modules.settings.game-tables.frontend_creation.enabled', false);

        $this->actingAs($this->jane)
            ->post(route('gametables.store'), $this->sessionData('Sesión uno', $campaign->id))
            ->assertForbidden();
        $this->actingAs($this->jane)
            ->get(route('gametables.create'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('GameTables/CreateNotEligible', false)
                ->where('reason', 'frontend_creation_disabled'));

        $this->assertFalse(GameTableModel::query()->where('title', 'Sesión uno')->exists());
    }

    public function test_a_session_needs_a_campaign(): void
    {
        $this->configure('registered');

        $this->actingAs($this->jane)
            ->post(route('gametables.store'), $this->sessionData('Sesión sin campaña', null))
            ->assertSessionHasErrors(['campaign_id' => self::REQUIRED]);

        $this->assertFalse(GameTableModel::query()->where('title', 'Sesión sin campaña')->exists());
    }

    public function test_a_draft_or_finished_campaign_is_refused(): void
    {
        $this->configure('registered');
        $draft = $this->campaign('Borrador de Jane', $this->jane, FrontendCreationStatus::Draft, isPublished: false);
        $finished = $this->campaignDirectedAsGm('Campaña terminada', status: 'completed');

        foreach ([$draft, $finished] as $campaign) {
            $this->actingAs($this->jane)
                ->post(route('gametables.store'), $this->sessionData('Sesión rechazada', $campaign->id))
                ->assertSessionHasErrors(['campaign_id' => self::NOT_OWNED]);
        }

        $this->assertFalse(GameTableModel::query()->where('title', 'Sesión rechazada')->exists());
    }

    public function test_a_campaign_directed_as_gm_is_accepted(): void
    {
        $this->configure('registered');
        $campaign = $this->campaignDirectedAsGm('Campaña de Jane');

        $this->actingAs($this->jane)
            ->post(route('gametables.store'), $this->sessionData('Sesión uno', $campaign->id))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('gametables.my-tables'));

        $this->assertSame($campaign->id, GameTableModel::query()->where('title', 'Sesión uno')->value('campaign_id'));
    }

    public function test_a_campaign_created_by_the_user_in_the_panel_without_being_gm_is_refused(): void
    {
        $this->configure('registered');
        $campaign = $this->campaign('Campaña del panel', $this->jane, null);

        $this->actingAs($this->jane)
            ->post(route('gametables.store'), $this->sessionData('Sesión uno', $campaign->id))
            ->assertSessionHasErrors(['campaign_id' => self::NOT_OWNED]);

        $this->assertFalse(GameTableModel::query()->where('title', 'Sesión uno')->exists());
    }

    public function test_another_type_drops_the_campaign(): void
    {
        $this->configure('registered');
        $campaign = $this->campaignDirectedAsGm('Campaña de Jane');

        $this->actingAs($this->jane)
            ->post(route('gametables.store'), [...$this->sessionData('Partida suelta', $campaign->id), 'table_type' => 'one_shot'])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('gametables.my-tables'));

        $table = GameTableModel::query()->where('title', 'Partida suelta')->first();
        $this->assertNotNull($table);
        $this->assertNull($table->campaign_id);
    }

    public function test_max_players_below_the_roster_is_refused(): void
    {
        $campaign = $this->campaignDirectedAsGm('Campaña de Jane');
        $this->fillRoster($campaign, 4);

        $this->actingAs($this->jane)
            ->post(route('gametables.store'), [...$this->sessionData('Sesión pequeña', $campaign->id), 'min_players' => 1, 'max_players' => 3])
            ->assertSessionHasErrors(['max_players' => 'El máximo de jugadores no puede ser menor que la plantilla de la campaña (4).']);
        $this->actingAs($this->jane)
            ->post(route('gametables.store'), [...$this->sessionData('Sesión justa', $campaign->id), 'min_players' => 1, 'max_players' => 4])
            ->assertSessionHasNoErrors();

        $this->assertFalse(GameTableModel::query()->where('title', 'Sesión pequeña')->exists());
        $this->assertTrue(GameTableModel::query()->where('title', 'Sesión justa')->exists());
    }

    public function test_the_form_offers_directed_campaigns_with_roster_size(): void
    {
        $this->configure('registered');
        $zeta = $this->campaignDirectedAsGm('Zeta, campaña dirigida', maxPlayers: null);
        $alpha = $this->campaign('Alfa, creada en la web', $this->jane, FrontendCreationStatus::Approved, isPublished: false, maxPlayers: 6);
        $this->fillRoster($alpha, 2);
        $this->campaign('Borrador de Jane', $this->jane, FrontendCreationStatus::Draft, isPublished: false);
        $this->campaign('Campaña ajena', UserModel::factory()->create(['name' => 'John Doe']), FrontendCreationStatus::Approved);
        $this->campaignDirectedAsGm('Campaña terminada', status: 'completed');

        $this->actingAs($this->jane)
            ->get(route('gametables.create'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('GameTables/Create', false)
                ->where('formData.campaigns', [
                    ['id' => $alpha->id, 'name' => 'Alfa, creada en la web', 'game_system_id' => $this->gameSystem->id, 'max_players' => 6, 'roster_count' => 2],
                    ['id' => $zeta->id, 'name' => 'Zeta, campaña dirigida', 'game_system_id' => $this->gameSystem->id, 'max_players' => null, 'roster_count' => 0],
                ])
                ->where('sessionOnly', false)
                ->where('prefill', null));
    }

    public function test_add_session_prefills_type_and_campaign(): void
    {
        $campaign = $this->campaignDirectedAsGm('Campaña de Jane');

        $this->actingAs($this->jane)
            ->get(route('gametables.create', ['campaign' => $campaign->id]))
            ->assertInertia(fn (Assert $page) => $page
                ->component('GameTables/Create', false)
                ->where('prefill', ['table_type' => 'campaign_session', 'campaign_id' => $campaign->id]));
    }

    public function test_a_foreign_campaign_in_the_query_is_ignored(): void
    {
        $this->campaignDirectedAsGm('Campaña de Jane');
        $foreign = $this->campaign('Campaña ajena', UserModel::factory()->create(['name' => 'John Doe']), FrontendCreationStatus::Approved);

        foreach ([$foreign->id, 'no-es-un-id'] as $query) {
            $this->actingAs($this->jane)
                ->get(route('gametables.create', ['campaign' => $query]))
                ->assertInertia(fn (Assert $page) => $page
                    ->component('GameTables/Create', false)
                    ->where('prefill', null));
        }
    }

    public function test_session_only_page_for_directors(): void
    {
        $this->campaignDirectedAsGm('Campaña de Jane');
        $richard = UserModel::factory()->create(['name' => 'Richard Roe', 'email' => 'richard@example.com']);

        $this->actingAs($this->jane)
            ->get(route('gametables.create'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('GameTables/Create', false)
                ->where('sessionOnly', true)
                ->has('formData.campaigns', 1));

        $this->actingAs($richard)
            ->get(route('gametables.create'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('GameTables/CreateNotEligible', false)
                ->where('reason', 'permission_denied'));
    }

    public function test_the_edit_form_tells_a_session_only_director_so(): void
    {
        $campaign = $this->campaignDirectedAsGm('Campaña de Jane');
        $draft = $this->sessionDraft($campaign);

        $this->actingAs($this->jane)
            ->get(route('gametables.edit', $draft->slug))
            ->assertInertia(fn (Assert $page) => $page
                ->component('GameTables/Edit', false)
                ->where('sessionOnly', true));

        // Allowed by the general rules: the type stays free
        $this->configure('registered');
        $this->actingAs($this->jane)
            ->get(route('gametables.edit', $draft->slug))
            ->assertInertia(fn (Assert $page) => $page
                ->component('GameTables/Edit', false)
                ->where('sessionOnly', false));
    }

    public function test_the_edit_form_keeps_the_stored_campaign(): void
    {
        $this->configure('registered');
        $own = $this->campaignDirectedAsGm('Campaña de Jane');
        $linkedByAdmin = $this->campaign('Campaña del panel', UserModel::factory()->create(['name' => 'John Doe']), null, maxPlayers: 4);
        $this->fillRoster($linkedByAdmin, 1);
        $draft = $this->sessionDraft($linkedByAdmin);

        $this->actingAs($this->jane)
            ->get(route('gametables.edit', $draft->slug))
            ->assertInertia(fn (Assert $page) => $page
                ->component('GameTables/Edit', false)
                ->where('formData.campaigns', [
                    ['id' => $own->id, 'name' => 'Campaña de Jane', 'game_system_id' => $this->gameSystem->id, 'max_players' => 5, 'roster_count' => 0],
                    ['id' => $linkedByAdmin->id, 'name' => 'Campaña del panel', 'game_system_id' => $this->gameSystem->id, 'max_players' => 4, 'roster_count' => 1],
                ]));
    }

    public function test_an_auto_published_session_below_the_roster_creates_nothing(): void
    {
        $this->configure('registered', 'auto');
        $campaign = $this->campaignDirectedAsGm('Campaña de Jane');
        $this->fillRoster($campaign, 3);

        try {
            app(FrontendCreationServiceInterface::class)->createGameTable(FrontendCreateGameTableDTO::fromArray([
                ...$this->sessionData('Sesión directa', $campaign->id),
                'min_players' => 1,
                'max_players' => 2,
                'created_by' => $this->jane->id,
            ]));
            $this->fail('A session below the roster must not be auto-published');
        } catch (SessionBelowRosterException $e) {
            $this->assertSame('Esta sesión tiene menos plazas (2) que la plantilla de la campaña (3). Sube el máximo de jugadores antes de publicarla.', $e->getMessage());
        }

        $this->assertSame(0, GameTableModel::query()->count());

        $this->actingAs($this->jane)
            ->post(route('gametables.store'), [...$this->sessionData('Sesión directa', $campaign->id), 'min_players' => 1, 'max_players' => 2])
            ->assertSessionHasErrors(['max_players' => 'El máximo de jugadores no puede ser menor que la plantilla de la campaña (3).']);

        $this->assertSame(0, GameTableModel::query()->count());
    }

    public function test_a_session_only_director_posting_with_an_event_is_refused(): void
    {
        $campaign = $this->campaignDirectedAsGm('Campaña de Jane');
        $event = $this->eventWithTables();

        $this->actingAs($this->jane)
            ->post(route('gametables.store'), [...$this->sessionData('Sesión en evento', $campaign->id), 'event_id' => $event->id])
            ->assertForbidden();

        $this->assertFalse(GameTableModel::query()->where('title', 'Sesión en evento')->exists());
    }

    public function test_a_session_only_director_may_edit_and_send_their_session(): void
    {
        $campaign = $this->campaignDirectedAsGm('Campaña de Jane');
        $draft = $this->sessionDraft($campaign);

        $this->actingAs($this->jane)
            ->put(route('gametables.update', $draft->slug), $this->sessionData('Sesión corregida', $campaign->id))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('gametables.my-tables'));
        // A new title renews the slug
        $draft->refresh();
        $this->actingAs($this->jane)
            ->post(route('gametables.submit-review', $draft->slug))
            ->assertRedirect(route('gametables.my-tables'));

        $draft->refresh();
        $this->assertSame('Sesión corregida', $draft->title);
        $this->assertSame('pending_review', $draft->frontend_creation_status->value);
    }

    public function test_a_session_only_director_cannot_edit_the_draft_into_a_one_shot(): void
    {
        $campaign = $this->campaignDirectedAsGm('Campaña de Jane');
        $draft = $this->sessionDraft($campaign);

        $this->actingAs($this->jane)
            ->put(route('gametables.update', $draft->slug), [...$this->sessionData('Partida suelta', $campaign->id), 'table_type' => 'one_shot'])
            ->assertForbidden();

        $draft->refresh();
        $this->assertSame('Sesión en borrador', $draft->title);
        $this->assertSame('campaign_session', $draft->table_type->value);
        $this->assertSame($campaign->id, $draft->campaign_id);
    }

    public function test_a_session_only_director_cannot_move_the_draft_into_an_event(): void
    {
        $campaign = $this->campaignDirectedAsGm('Campaña de Jane');
        $draft = $this->sessionDraft($campaign);
        $event = $this->eventWithTables();

        $this->actingAs($this->jane)
            ->put(route('gametables.update', $draft->slug), [...$this->sessionData('Sesión en evento', $campaign->id), 'event_id' => $event->id])
            ->assertForbidden();

        $draft->refresh();
        $this->assertSame('Sesión en borrador', $draft->title);
        $this->assertNull($draft->event_id);
    }

    public function test_a_session_only_director_cannot_send_a_draft_that_is_no_longer_a_directed_session(): void
    {
        $campaign = $this->campaignDirectedAsGm('Campaña de Jane');
        $oneShot = $this->sessionDraft($campaign);
        $oneShot->update(['table_type' => 'one_shot', 'campaign_id' => null]);

        $this->actingAs($this->jane)
            ->post(route('gametables.submit-review', $oneShot->slug))
            ->assertForbidden();

        $this->assertSame('draft', $oneShot->refresh()->frontend_creation_status->value);
    }

    public function test_an_eligible_author_keeps_editing_and_sending_any_draft(): void
    {
        $this->configure('registered');
        $campaign = $this->campaignDirectedAsGm('Campaña de Jane');
        $draft = $this->sessionDraft($campaign);

        $this->actingAs($this->jane)
            ->put(route('gametables.update', $draft->slug), [...$this->sessionData('Partida suelta', null), 'table_type' => 'one_shot'])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('gametables.my-tables'));
        // A new title renews the slug
        $draft->refresh();
        $this->actingAs($this->jane)
            ->post(route('gametables.submit-review', $draft->slug))
            ->assertRedirect(route('gametables.my-tables'));

        $draft->refresh();
        $this->assertSame('one_shot', $draft->table_type->value);
        $this->assertNull($draft->campaign_id);
        $this->assertSame('pending_review', $draft->frontend_creation_status->value);
    }

    /**
     * A roster that grows between the early check and the publication leaves nothing behind.
     */
    public function test_a_roster_that_grows_during_an_auto_publication_leaves_no_table(): void
    {
        $this->configure('registered', 'auto');
        $campaign = $this->campaignDirectedAsGm('Campaña de Jane');
        $this->fillRoster($campaign, 2);
        GameTableModel::created(function () use ($campaign): void {
            $this->fillRoster($campaign, 1);
        });

        try {
            app(FrontendCreationServiceInterface::class)->createGameTable(FrontendCreateGameTableDTO::fromArray([
                ...$this->sessionData('Sesión directa', $campaign->id),
                'min_players' => 1,
                'max_players' => 2,
                'created_by' => $this->jane->id,
            ]));
            $this->fail('A session below the roster must not be auto-published');
        } catch (SessionBelowRosterException $e) {
            $this->assertSame('Esta sesión tiene menos plazas (2) que la plantilla de la campaña (3). Sube el máximo de jugadores antes de publicarla.', $e->getMessage());
        }

        $this->assertSame(0, GameTableModel::query()->count());
    }

    /**
     * The roster may grow between validation and publication: the service refusal becomes a
     * form error instead of a server error.
     */
    public function test_a_service_refusal_below_the_roster_is_a_form_error(): void
    {
        $campaign = $this->campaignDirectedAsGm('Campaña de Jane');
        $this->mock(FrontendCreationServiceInterface::class)
            ->shouldReceive('createGameTable')
            ->andThrow(SessionBelowRosterException::forNewSession(5, 6));

        $this->actingAs($this->jane)
            ->from(route('gametables.create'))
            ->post(route('gametables.store'), $this->sessionData('Sesión directa', $campaign->id))
            ->assertRedirect(route('gametables.create'))
            ->assertSessionHasErrors(['max_players' => 'Esta sesión tiene menos plazas (5) que la plantilla de la campaña (6). Sube el máximo de jugadores antes de publicarla.']);
    }

    public function test_the_new_copy_is_translated_in_both_languages(): void
    {
        $expected = [
            'es' => [
                'campaign_required' => self::REQUIRED,
                'campaign_not_owned' => self::NOT_OWNED,
                'max_players_below_roster' => 'El máximo de jugadores no puede ser menor que la plantilla de la campaña (:count).',
            ],
            'en' => [
                'campaign_required' => 'Choose the campaign of the session.',
                'campaign_not_owned' => 'You can only link the table to a campaign you direct.',
                'max_players_below_roster' => 'The maximum number of players cannot be lower than the campaign roster (:count).',
            ],
        ];

        foreach ($expected as $locale => $keys) {
            foreach ($keys as $key => $text) {
                $this->assertSame($text, trans('game-tables::messages.validation.'.$key, [], $locale), "$locale $key");
            }
        }
    }

    private function configure(string $accessLevel, string $publicationMode = 'approval'): void
    {
        config()->set('modules.settings.game-tables', [
            'frontend_creation' => [
                'enabled' => true,
                'access_level' => $accessLevel,
                'required_permission' => 'gametables.create_from_web',
                'allowed_content' => 'both',
                'publication' => ['mode' => $publicationMode],
            ],
        ]);
    }

    /**
     * An approved campaign someone else created in the panel, with Jane as a game master with an account.
     */
    private function campaignDirectedAsGm(string $title, string $status = 'recruiting', ?int $maxPlayers = 5): CampaignModel
    {
        $campaign = $this->campaign($title, UserModel::factory()->create(['name' => 'Mary Major']), null, status: $status, maxPlayers: $maxPlayers);

        $gm = GameMasterModel::create([
            'id' => (string) Str::uuid(),
            'user_id' => $this->jane->id,
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'role' => GameMasterRole::Main,
            'notify_by_email' => true,
            'is_name_public' => true,
        ]);
        $campaign->gameMasters()->attach($gm->id, ['sort_order' => 1]);

        return $campaign;
    }

    private function campaign(
        string $title,
        UserModel $creator,
        ?FrontendCreationStatus $creationStatus,
        bool $isPublished = true,
        string $status = 'recruiting',
        ?int $maxPlayers = 5,
    ): CampaignModel {
        return CampaignModel::create([
            'id' => (string) Str::uuid(),
            'game_system_id' => $this->gameSystem->id,
            'created_by' => $creator->id,
            'title' => $title,
            'slug' => Str::slug($title).'-'.Str::random(6),
            'frequency' => 'weekly',
            'status' => $status,
            'max_players' => $maxPlayers,
            'is_published' => $isPublished,
            'frontend_creation_status' => $creationStatus,
        ]);
    }

    private function eventWithTables(): EventModel
    {
        $event = EventModel::factory()->published()->upcoming()->create();
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

    private function fillRoster(CampaignModel $campaign, int $size): void
    {
        $names = ['John Doe', 'Richard Roe', 'Mary Major', 'Jane Doe'];
        $roster = app(CampaignRosterRepositoryInterface::class);

        for ($i = 0; $i < $size; $i++) {
            $this->members++;
            $member = UserModel::factory()->create([
                'name' => $names[$i % count($names)],
                'email' => 'member'.$this->members.'@example.com',
            ]);
            $roster->add(new CampaignId($campaign->id), $member->id, CarbonImmutable::now());
        }
    }

    private function sessionDraft(CampaignModel $campaign): GameTableModel
    {
        return GameTableModel::create([
            'id' => (string) Str::uuid(),
            'game_system_id' => $this->gameSystem->id,
            'campaign_id' => $campaign->id,
            'created_by' => $this->jane->id,
            'title' => 'Sesión en borrador',
            'slug' => 'sesion-en-borrador',
            'starts_at' => now()->addWeek(),
            'duration_minutes' => 240,
            'table_type' => 'campaign_session',
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

    /**
     * @return array<string, mixed>
     */
    private function sessionData(string $title, ?string $campaignId): array
    {
        return [
            'game_system_id' => $this->gameSystem->id,
            'campaign_id' => $campaignId,
            'title' => $title,
            'starts_at' => now()->addWeeks(2)->toIso8601String(),
            'duration_minutes' => 240,
            'table_type' => 'campaign_session',
            'table_format' => 'in_person',
            'min_players' => 2,
            'max_players' => 5,
            'language' => 'es',
            'experience_level' => 'none',
            'character_creation' => 'pre_generated',
        ];
    }
}
