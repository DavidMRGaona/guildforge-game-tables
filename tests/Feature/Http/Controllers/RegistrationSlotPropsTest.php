<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Feature\Http\Controllers;

use App\Application\Modules\DTOs\SlotRegistrationDTO;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Carbon\CarbonImmutable;
use Illuminate\Routing\Route;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\GameTables\GameTablesServiceProvider;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameSystemModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameTableModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\ParticipantModel;
use Tests\Support\Modules\ModuleTestCase;

/**
 * The registration button gets everything it shows from the table page: the page always sends
 * the props its slot reads, and the module has no API to fetch them from.
 */
final class RegistrationSlotPropsTest extends ModuleTestCase
{
    protected ?string $moduleName = 'game-tables';

    protected bool $autoEnableModule = true;

    private GameTableModel $table;

    protected function setUp(): void
    {
        parent::setUp();

        // Far from a clock change: start times are stored as wall-clock times, ambiguous in the hour
        // repeated in October (GuildForge#107), and these tests store times minutes or hours from now
        $this->travelTo(CarbonImmutable::parse('2026-11-04 18:00:00'));

        app('router')->getRoutes()->refreshNameLookups();
        config()->set('modules.settings.game-tables', []);

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

    public function test_a_visitor_gets_every_prop_of_the_registration_slot(): void
    {
        $this->get(route('gametables.show', $this->table->slug))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $this->assertSlotProps($page)
                ->where('table.id', $this->table->id)
                ->where('eligibility', null)
                ->where('guestEligibility.eligible', true)
                ->where('userRegistration', null));
    }

    // The guest form says before sending it how long the place is held, as the server will apply it
    public function test_a_visitor_is_told_the_confirmation_deadline_of_a_held_place(): void
    {
        $now = CarbonImmutable::now();

        $this->get(route('gametables.show', $this->table->slug))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('guestEligibility.confirmation.mode', 'hold')
                ->where('guestEligibility.confirmation.deadline', $now->addHours(2)->format('c')));
    }

    public function test_a_visitor_is_told_the_place_is_not_guaranteed_once_the_cap_is_reached(): void
    {
        foreach (['ana@example.test', 'bea@example.test'] as $email) {
            ParticipantModel::create([
                'id' => (string) Str::uuid(),
                'game_table_id' => $this->table->id,
                'first_name' => 'Invitada',
                'email' => $email,
                'role' => 'player',
                'status' => 'confirmed',
                'confirmed_at' => now(),
                'confirmation_mode' => 'hold',
                'confirmation_token' => bin2hex(random_bytes(32)),
                'confirmation_expires_at' => now()->addHours(2),
                'confirmation_sent_at' => now(),
            ]);
        }

        $this->get(route('gametables.show', $this->table->slug))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('guestEligibility.confirmation.mode', 'wait')
                ->where('guestEligibility.confirmation.deadline', null));
    }

    public function test_a_signed_in_user_gets_every_prop_of_the_registration_slot(): void
    {
        $this->actingAs(UserModel::factory()->create())
            ->get(route('gametables.show', $this->table->slug))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $this->assertSlotProps($page)
                ->where('eligibility.eligible', true)
                ->where('guestEligibility', null)
                ->where('userRegistration', null));
    }

    public function test_a_registered_user_gets_their_registration_with_the_slot_props(): void
    {
        $user = UserModel::factory()->create();
        $participant = ParticipantModel::create([
            'id' => (string) Str::uuid(),
            'game_table_id' => $this->table->id,
            'user_id' => $user->id,
            'role' => 'player',
            'status' => 'confirmed',
            'confirmed_at' => now(),
        ]);

        $this->actingAs($user)
            ->get(route('gametables.show', $this->table->slug))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $this->assertSlotProps($page)
                ->where('userRegistration.id', $participant->id)
                ->where('userRegistration.status', 'confirmed'));
    }

    public function test_the_module_has_no_api_routes(): void
    {
        $apiRoutes = array_filter(
            app('router')->getRoutes()->getRoutes(),
            fn (Route $route): bool => str_starts_with($route->uri(), 'api/gametables'),
        );

        $this->assertSame([], array_values(array_map(fn (Route $route): string => $route->uri(), $apiRoutes)));
    }

    public function test_the_manifest_lists_the_route_files_the_module_has(): void
    {
        $modulePath = dirname(__DIR__, 4);
        /** @var array{provides: array{routes: list<string>}} $manifest */
        $manifest = json_decode((string) file_get_contents($modulePath.'/module.json'), true, flags: JSON_THROW_ON_ERROR);

        $routeFiles = array_map(
            fn (string $file): string => basename($file, '.php'),
            glob($modulePath.'/routes/*.php') ?: [],
        );
        sort($routeFiles);
        $listed = $manifest['provides']['routes'];
        sort($listed);

        $this->assertSame($routeFiles, $listed);
    }

    // The button shows why instead of "Las inscripciones abren el..."
    public function test_an_invite_table_tells_a_signed_in_user_it_is_by_invitation_only(): void
    {
        $this->table->update(['registration_type' => 'invite', 'registration_opens_at' => now()->addDay()]);

        $this->actingAs(UserModel::factory()->create())
            ->get(route('gametables.show', $this->table->slug))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('eligibility.eligible', false)
                ->where('eligibility.reason', 'invitation_only')
                ->where('eligibility.message', 'Esta mesa es solo por invitación: la organización inscribe a los jugadores')
                ->where('eligibility.canRegisterAt', null)
                ->where('eligibility.spectator.reason', 'invitation_only'));
    }

    // The button shows the server's message disabled instead of "Inicia sesión para inscribirte"
    public function test_an_invite_table_tells_a_visitor_it_is_by_invitation_only(): void
    {
        $this->table->update(['registration_type' => 'invite']);

        $this->get(route('gametables.show', $this->table->slug))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('eligibility', null)
                ->where('guestEligibility.eligible', false)
                ->where('guestEligibility.reason', 'invitation_only')
                ->where('guestEligibility.message', 'Esta mesa es solo por invitación: la organización inscribe a los jugadores')
                ->where('guestEligibility.spectator.eligible', false)
                ->where('guestEligibility.spectator.reason', 'invitation_only'));
    }

    // Members-only tables keep their own reason: a member can still sign in and register
    public function test_a_members_only_table_still_tells_a_visitor_guests_are_not_allowed(): void
    {
        $this->table->update(['registration_type' => 'members_only']);

        $this->get(route('gametables.show', $this->table->slug))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('guestEligibility.eligible', false)
                ->where('guestEligibility.reason', 'guests_not_allowed')
                ->where('guestEligibility.message', 'Esta mesa no permite inscripciones de invitados'));
    }

    public function test_the_invitation_only_message_is_translated_in_both_languages(): void
    {
        foreach (['es' => 'Esta mesa es solo por invitación: la organización inscribe a los jugadores', 'en' => 'This table is by invitation only: the organisation registers the players'] as $locale => $text) {
            $this->assertSame($text, trans('game-tables::messages.errors.invitation_only', [], $locale));
        }
    }

    public function test_the_cannot_cancel_started_message_is_translated_in_both_languages(): void
    {
        foreach (['es' => 'La partida ya ha comenzado: ya no puedes cancelar la inscripción', 'en' => 'The session has already started: you can no longer cancel your registration'] as $locale => $text) {
            $this->assertSame($text, trans('game-tables::messages.errors.cannot_cancel_started', [], $locale));
        }
    }

    public function test_the_in_play_help_text_is_translated_in_both_languages(): void
    {
        foreach (['es' => 'Admite inscripciones desde el inicio hasta el final de la partida, aunque no se haya pulsado «Iniciar», solo con plazas libres: sin lista de espera ni bajas', 'en' => 'Admits registrations from the start to the end of the session, even if «Start» was not pressed, only with free seats: no waiting list and no cancellations'] as $locale => $text) {
            $this->assertSame($text, trans('game-tables::messages.fields.accepts_registrations_in_progress_help', [], $locale));
        }

        $this->assertSame('Allow registrations during the session', trans('game-tables::messages.fields.accepts_registrations_in_progress', [], 'en'));
    }

    private function assertSlotProps(Assert $page): Assert
    {
        $page->component('GameTables/Show', shouldExist: false);

        foreach ($this->registrationSlot()->dataKeys as $key) {
            $page->has($key);
        }

        return $page;
    }

    private function registrationSlot(): SlotRegistrationDTO
    {
        $provider = app()->getProvider(GameTablesServiceProvider::class);
        $this->assertInstanceOf(GameTablesServiceProvider::class, $provider);

        foreach ($provider->registerSlots() as $slot) {
            if ($slot->slot === 'game-table-registration') {
                $this->assertSame(['table', 'eligibility', 'guestEligibility', 'userRegistration', 'campaignSession'], $slot->dataKeys);

                return $slot;
            }
        }

        $this->fail('The game-table-registration slot is not registered.');
    }
}
