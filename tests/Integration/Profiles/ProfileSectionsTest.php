<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Integration\Profiles;

use App\Application\Profiles\DTOs\ProfileLayoutOptionDTO;
use App\Application\Profiles\DTOs\ProfileOwnerDTO;
use App\Application\Profiles\DTOs\ResolvedProfileDTO;
use App\Application\Profiles\DTOs\ResolvedProfileSectionDTO;
use App\Application\Profiles\DTOs\ResolvedProfileStatDTO;
use App\Application\Profiles\Services\ProfileLayoutOptionsInterface;
use App\Application\Profiles\Services\ProfileRendererInterface;
use App\Application\Profiles\Services\ProfileSectionRegistryInterface;
use App\Application\Profiles\Services\ProfileStatRegistryInterface;
use App\Application\Services\SettingsServiceInterface;
use App\Application\Services\TimezoneServiceInterface;
use App\Infrastructure\Persistence\Eloquent\Models\RoleModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\GameTables\Domain\Enums\GameMasterRole;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\CampaignModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameMasterModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameSystemModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameTableModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\ParticipantModel;
use Tests\Support\Modules\ModuleTestCase;

/**
 * The tables and campaigns of a public profile (game-tables#282), rendered as the core's profile page
 * does: only what the person viewing could see on the tables and campaigns pages.
 */
final class ProfileSectionsTest extends ModuleTestCase
{
    protected ?string $moduleName = 'game-tables';

    protected bool $autoEnableModule = true;

    /** Every query of a whole profile render for a director viewer, the core's included */
    private const int MAX_RENDER_QUERIES = 13;

    private GameSystemModel $system;

    private UserModel $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->useUtcStorage();
        $this->travelTo(CarbonImmutable::parse('2026-11-15 10:00:00', 'UTC'));
        $this->system = GameSystemModel::create(['id' => (string) Str::uuid(), 'name' => 'Sistema de ejemplo', 'slug' => Str::random(10), 'is_active' => true]);
        $this->owner = UserModel::factory()->create(['name' => 'Cuenta privada', 'display_name' => 'Ana', 'email' => 'ana@example.com']);
    }

    public function test_the_module_registers_its_sections_and_stats(): void
    {
        $sections = array_map(static fn ($registration): string => $registration->key, $this->app->make(ProfileSectionRegistryInterface::class)->all());
        $stats = array_map(static fn ($registration): string => $registration->key, $this->app->make(ProfileStatRegistryInterface::class)->all());

        $this->assertContains('game-tables.tables', $sections);
        $this->assertContains('game-tables.campaigns', $sections);
        $this->assertContains('game-tables.played-tables', $sections);
        $this->assertContains('game-tables.tables_run', $stats);
        $this->assertContains('game-tables.tables_played', $stats);
        $this->assertContains('game-tables.campaign_count', $stats);
    }

    public function test_the_profile_editor_offers_the_played_counter_and_section_on_by_default(): void
    {
        $options = $this->app->make(ProfileLayoutOptionsInterface::class);
        $stat = $this->option($options->stats(), 'game-tables.tables_played');
        $section = $this->option($options->sections(), 'game-tables.played-tables');

        $this->assertSame('Partidas jugadas', $stat?->label);
        $this->assertSame('Las mesas y sesiones de campaña que ya has jugado.', $stat->description);
        $this->assertTrue($stat->enabledByDefault);
        $this->assertSame('Últimas partidas', $section?->label);
        $this->assertTrue($section->enabledByDefault);
    }

    public function test_tables_go_in_the_main_column_and_campaigns_in_the_aside(): void
    {
        $this->directedBy($this->owner, $this->table(['title' => 'La torre']));
        $this->directedCampaign($this->owner, $this->campaign(['title' => 'Las crónicas']));

        $profile = $this->render(null);

        $tables = $this->section($profile, 'game-tables.tables');
        $campaigns = $this->section($profile, 'game-tables.campaigns');
        $this->assertNotNull($tables);
        $this->assertNotNull($campaigns);
        $this->assertContains($tables, $profile->main);
        $this->assertContains($campaigns, $profile->aside);
        $this->assertSame('Mesas', $tables->label);
        $this->assertSame('Campañas', $campaigns->label);
        $this->assertSame('components/profileSections/TablesProfileSection.vue', $tables->component);
        $this->assertSame('components/profileSections/CampaignsProfileSection.vue', $campaigns->component);
    }

    public function test_anyone_sees_the_upcoming_published_tables_the_owner_runs_under_their_name(): void
    {
        $listed = $this->directedBy($this->owner, $this->table(['title' => 'La torre', 'slug' => 'la-torre', 'starts_at' => '2026-11-20 17:00:00']));

        foreach ($this->viewers() as $label => $viewer) {
            $props = $this->section($this->render($viewer), 'game-tables.tables')?->props;

            $this->assertSame([[
                'id' => $listed->id,
                'title' => 'La torre',
                'url' => '/mesas/la-torre',
                'startsAt' => $this->app->make(TimezoneServiceInterface::class)->iso(CarbonImmutable::parse('2026-11-20 17:00:00', 'UTC')),
                'gameSystem' => 'Sistema de ejemplo',
            ]], $props['directing'] ?? null, $label);
        }
    }

    public function test_tables_the_pages_do_not_list_never_show_for_anyone(): void
    {
        $this->directedBy($this->owner, $this->table(['status' => 'draft']));
        $this->directedBy($this->owner, $this->table(['status' => 'cancelled']));
        $this->directedBy($this->owner, $this->table(['is_published' => false]));
        $this->directedBy($this->owner, $this->table(['is_published' => false, 'frontend_creation_status' => 'pending_review']));
        $this->directedBy($this->owner, $this->table(['is_published' => false, 'frontend_creation_status' => 'draft']));
        $this->directedBy($this->owner, $this->table(['starts_at' => '2026-11-14 17:00:00']));

        foreach ([...$this->viewers(), 'owner' => $this->owner] as $label => $viewer) {
            $this->assertNull($this->section($this->render($viewer), 'game-tables.tables'), $label);
        }
    }

    public function test_a_table_whose_game_master_name_is_not_public_is_not_tied_to_the_owner(): void
    {
        $this->directedBy($this->owner, $this->table(), namePublic: false);

        foreach ($this->viewers() as $label => $viewer) {
            $this->assertNull($this->section($this->render($viewer), 'game-tables.tables'), $label);
            $this->assertNull($this->stat($this->render($viewer), 'game-tables.tables_run'), $label);
        }

        // The owner is only told that it does not show, without the table
        $props = $this->section($this->render($this->owner), 'game-tables.tables')?->props;
        $this->assertSame(['directing' => [], 'playing' => [], 'hiddenDirecting' => 1], $props);
        $this->assertNull($this->stat($this->render($this->owner), 'game-tables.tables_run'));
    }

    public function test_the_owner_note_counts_only_upcoming_listed_tables_without_a_public_name(): void
    {
        $this->directedBy($this->owner, $this->table(), namePublic: false);
        $this->directedBy($this->owner, $this->table(['status' => 'draft']), namePublic: false);
        $this->directedBy($this->owner, $this->table(['starts_at' => '2026-11-01 17:00:00']), namePublic: false);
        // Run under a public name too: it shows, so it is not counted as hidden
        $both = $this->directedBy($this->owner, $this->table(), namePublic: false);
        $this->directedBy($this->owner, $both);

        $props = $this->section($this->render($this->owner), 'game-tables.tables')?->props;

        $this->assertSame([$both->id], array_column($props['directing'] ?? [], 'id'));
        $this->assertSame(1, $props['hiddenDirecting'] ?? null);
        $this->assertSame(0, $this->section($this->render(null), 'game-tables.tables')?->props['hiddenDirecting']);
    }

    public function test_a_campaign_session_counts_for_its_game_masters_unless_excluded(): void
    {
        $campaign = $this->campaign();
        $gm = $this->directedCampaign($this->owner, $campaign);
        $session = $this->table(['title' => 'Sesión 1', 'campaign_id' => $campaign->id]);
        $excluded = $this->table(['title' => 'Sesión 2', 'campaign_id' => $campaign->id, 'starts_at' => '2026-11-27 17:00:00']);
        $excluded->gameMasters()->attach($gm->id, ['source' => 'inherited', 'excluded' => true, 'sort_order' => 0]);

        $props = $this->section($this->render(null), 'game-tables.tables')?->props;

        $this->assertSame([$session->id], array_column($props['directing'] ?? [], 'id'));
    }

    public function test_the_owner_sees_the_upcoming_tables_they_play_in(): void
    {
        $confirmed = $this->table(['title' => 'Confirmada', 'starts_at' => '2026-11-21 17:00:00']);
        $this->joins($this->owner, $confirmed);
        $this->joins($this->owner, $this->table(['title' => 'En espera']), status: 'waiting_list');
        $this->joins($this->owner, $this->table(['title' => 'Pendiente']), status: 'pending');
        $this->joins($this->owner, $this->table(['title' => 'Cancelada']), status: 'cancelled');
        $this->joins($this->owner, $this->table(['title' => 'Espectador']), role: 'spectator');
        $this->joins($this->owner, $this->table(['title' => 'Pasada', 'starts_at' => '2026-11-10 17:00:00']));
        $this->joins($this->owner, $this->table(['title' => 'Borrador', 'status' => 'draft']));

        $props = $this->section($this->render($this->owner), 'game-tables.tables')?->props;

        $this->assertSame([], $props['directing'] ?? null);
        $this->assertSame([$confirmed->id], array_column($props['playing'] ?? [], 'id'));
    }

    public function test_nobody_but_the_owner_sees_where_they_play_when_the_page_shows_no_players_list(): void
    {
        $this->joins($this->owner, $this->table());
        // Directed by somebody else and not delegating its registrations: no players list on the site
        $other = UserModel::factory()->create();
        $this->joins($this->owner, $this->directedBy($other, $this->table(['delegates_registrations' => false])));

        foreach ([...$this->viewers(), 'other director' => $other] as $label => $viewer) {
            $this->assertNull($this->section($this->render($viewer), 'game-tables.tables'), $label);
        }
    }

    public function test_the_director_of_a_delegating_table_sees_the_owner_play_in_it(): void
    {
        $director = UserModel::factory()->create();
        $delegating = $this->directedBy($director, $this->table(['title' => 'Delegada', 'delegates_registrations' => true]));
        $this->joins($this->owner, $delegating);
        // Another table the owner plays in, which this director does not run
        $this->joins($this->owner, $this->directedBy(UserModel::factory()->create(), $this->table(['delegates_registrations' => true])));

        $props = $this->section($this->render($director), 'game-tables.tables')?->props;

        $this->assertSame([$delegating->id], array_column($props['playing'] ?? [], 'id'));
    }

    public function test_where_the_owner_plays_follows_the_registrations_policy_of_each_table(): void
    {
        $director = UserModel::factory()->create();
        $campaign = $this->campaign();
        $tables = [
            $this->directedBy($director, $this->table(['delegates_registrations' => true])),
            $this->directedBy($director, $this->table(['delegates_registrations' => false])),
            $this->directedBy($director, $this->table(['delegates_registrations' => true]), namePublic: false),
            $this->directedBy($director, $this->table(['delegates_registrations' => true, 'campaign_id' => $campaign->id])),
        ];
        foreach ($tables as $table) {
            $this->joins($this->owner, $table);
        }

        $shown = array_column($this->section($this->render($director), 'game-tables.tables')->props['playing'] ?? [], 'id');

        foreach ($tables as $index => $table) {
            $this->assertSame(
                $director->can('manageRegistrations', $table),
                in_array($table->id, $shown, true),
                "table {$index}",
            );
        }
    }

    public function test_a_campaign_director_sees_the_owner_play_a_session_only_when_they_are_on_the_roster(): void
    {
        $director = UserModel::factory()->create();
        $withRoster = $this->campaign();
        $withoutRoster = $this->campaign();
        $this->directedCampaign($director, $withRoster);
        $this->directedCampaign($director, $withoutRoster);
        $this->onRoster($this->owner, $withRoster);
        $session = $this->table(['campaign_id' => $withRoster->id]);
        $this->joins($this->owner, $session, fromRoster: true);
        $this->joins($this->owner, $this->table(['campaign_id' => $withoutRoster->id]));

        $props = $this->section($this->render($director), 'game-tables.tables')?->props;

        $this->assertSame([$session->id], array_column($props['playing'] ?? [], 'id'));
        $this->assertTrue($director->can('manageRoster', $withRoster));
    }

    public function test_a_director_who_must_verify_their_email_sees_no_players_as_on_their_pages(): void
    {
        $settings = $this->app->make(SettingsServiceInterface::class);
        $settings->set('email_verification_enforced_since', '2026-01-01T00:00:00+00:00');
        $settings->set('auth_email_verification_required', '1');
        $director = UserModel::factory()->unverified()->create();
        $this->joins($this->owner, $this->directedBy($director, $this->table(['delegates_registrations' => true])));
        $campaign = $this->campaign();
        $this->directedCampaign($director, $campaign);
        $this->onRoster($this->owner, $campaign);
        $this->joins($this->owner, $this->table(['campaign_id' => $campaign->id]), fromRoster: true);

        $profile = $this->render($director);

        $this->assertNull($this->section($profile, 'game-tables.tables'));
        $this->assertNull($this->section($profile, 'game-tables.campaigns'));
        // The total names no campaign, so it counts as for anybody else
        $this->assertSame(1, $this->stat($profile, 'game-tables.campaign_count')?->value);

        // Once verified, the same pages open and so does the profile
        $director->forceFill(['email_verified_at' => now()])->save();
        $profile = $this->render($director->refresh());
        $this->assertCount(2, $this->section($profile, 'game-tables.tables')->props['playing'] ?? []);
        $this->assertCount(1, $this->section($profile, 'game-tables.campaigns')->props['campaigns'] ?? []);
    }

    public function test_an_anonymized_director_sees_no_players(): void
    {
        $director = UserModel::factory()->create();
        $this->joins($this->owner, $this->directedBy($director, $this->table(['delegates_registrations' => true])));
        $director->forceFill(['anonymized_at' => now()])->save();

        $this->assertNull($this->section($this->render($director->refresh()), 'game-tables.tables'));
    }

    public function test_staff_permissions_never_show_where_the_owner_plays_nor_hidden_tables(): void
    {
        $admin = UserModel::factory()->admin()->create();
        $this->joins($this->owner, $this->directedBy(UserModel::factory()->create(), $this->table(['delegates_registrations' => true])));
        $this->directedBy($this->owner, $this->table(['is_published' => false, 'frontend_creation_status' => 'pending_review']));

        $this->assertNull($this->section($this->render($admin), 'game-tables.tables'));
    }

    public function test_each_list_shows_the_next_five_tables_in_order(): void
    {
        foreach ([7, 3, 9, 1, 5, 2, 8] as $day) {
            $this->directedBy($this->owner, $this->table(['title' => "Día {$day}", 'starts_at' => sprintf('2026-11-%02d 17:00:00', 15 + $day)]));
        }

        $props = $this->section($this->render(null), 'game-tables.tables')?->props;

        $this->assertSame(['Día 1', 'Día 2', 'Día 3', 'Día 5', 'Día 7'], array_column($props['directing'] ?? [], 'title'));
    }

    public function test_the_props_hold_nothing_private_about_the_owner(): void
    {
        $this->directedBy($this->owner, $this->table(['notes' => 'Nota interna', 'online_url' => 'https://meet.example.com/secreto']));
        $this->joins($this->owner, $this->table());
        $this->directedCampaign($this->owner, $this->campaign());

        $profile = $this->render($this->owner);
        $encoded = json_encode([$this->section($profile, 'game-tables.tables')?->props, $this->section($profile, 'game-tables.campaigns')?->props], JSON_THROW_ON_ERROR);

        $this->assertStringNotContainsString('ana@example.com', $encoded);
        $this->assertStringNotContainsString('Cuenta privada', $encoded);
        $this->assertStringNotContainsString('Nota interna', $encoded);
        $this->assertStringNotContainsString('secreto', $encoded);
        $this->assertStringNotContainsString($this->owner->id, $encoded);
    }

    public function test_anyone_sees_the_active_published_campaigns_the_owner_runs_under_their_name(): void
    {
        $recruiting = $this->directedCampaign($this->owner, $this->campaign(['title' => 'B reclutando', 'slug' => 'b-reclutando', 'status' => 'recruiting']))->campaigns()->first();
        $this->directedCampaign($this->owner, $this->campaign(['title' => 'A activa', 'status' => 'active']));
        $this->directedCampaign($this->owner, $this->campaign(['title' => 'C en pausa', 'status' => 'on_hold']));
        $this->directedCampaign($this->owner, $this->campaign(['status' => 'completed']));
        $this->directedCampaign($this->owner, $this->campaign(['status' => 'cancelled']));
        $this->directedCampaign($this->owner, $this->campaign(['is_published' => false, 'frontend_creation_status' => 'pending_review']));
        $this->directedCampaign($this->owner, $this->campaign(), namePublic: false);

        foreach ([...$this->viewers(), 'owner' => $this->owner] as $label => $viewer) {
            $props = $this->section($this->render($viewer), 'game-tables.campaigns')?->props;

            $this->assertSame(['A activa', 'B reclutando', 'C en pausa'], array_column($props['campaigns'] ?? [], 'title'), $label);
        }

        $recruitingRow = $this->section($this->render(null), 'game-tables.campaigns')?->props['campaigns'][1] ?? null;
        $this->assertSame([
            'id' => $recruiting?->id,
            'title' => 'B reclutando',
            'url' => '/campanas/b-reclutando',
            'gameSystem' => 'Sistema de ejemplo',
            'status' => 'Reclutando',
            'role' => 'director',
        ], $recruitingRow);
    }

    public function test_a_roster_membership_shows_only_to_the_owner_and_the_campaigns_directors(): void
    {
        $director = UserModel::factory()->create();
        $campaign = $this->campaign(['title' => 'La plantilla']);
        $this->directedCampaign($director, $campaign);
        $this->onRoster($this->owner, $campaign);

        foreach (['owner' => $this->owner, 'director' => $director] as $label => $viewer) {
            $props = $this->section($this->render($viewer), 'game-tables.campaigns')?->props;
            $this->assertSame([['La plantilla', 'player']], array_map(static fn (array $row): array => [$row['title'], $row['role']], $props['campaigns'] ?? []), $label);
        }

        foreach ($this->viewers() as $label => $viewer) {
            $this->assertNull($this->section($this->render($viewer), 'game-tables.campaigns'), $label);
            // A total names no campaign, so it counts for anybody (game-tables#325)
            $this->assertSame(1, $this->stat($this->render($viewer), 'game-tables.campaign_count')?->value, $label);
        }
    }

    public function test_nothing_to_show_draws_neither_sections_nor_stats(): void
    {
        $profile = $this->render(UserModel::factory()->create());

        $this->assertNull($this->section($profile, 'game-tables.tables'));
        $this->assertNull($this->section($profile, 'game-tables.campaigns'));
        $this->assertNull($this->stat($profile, 'game-tables.tables_run'));
        $this->assertNull($this->stat($profile, 'game-tables.campaign_count'));
        $this->assertNull($this->section($profile, 'game-tables.played-tables'));
        $this->assertNull($this->stat($profile, 'game-tables.tables_played'));
    }

    public function test_the_tables_run_counter_counts_every_listed_table_under_their_name(): void
    {
        $this->directedBy($this->owner, $this->table());
        $this->directedBy($this->owner, $this->table(['starts_at' => '2026-10-01 17:00:00']));
        $this->directedBy($this->owner, $this->table(['status' => 'completed', 'starts_at' => '2026-09-01 17:00:00']));
        $this->directedBy($this->owner, $this->table(['status' => 'draft']));
        $this->directedBy($this->owner, $this->table(['status' => 'cancelled']));
        $this->directedBy($this->owner, $this->table(['is_published' => false]));
        $this->directedBy($this->owner, $this->table(), namePublic: false);

        foreach ([...$this->viewers(), 'owner' => $this->owner] as $label => $viewer) {
            $stat = $this->stat($this->render($viewer), 'game-tables.tables_run');

            $this->assertSame(3, $stat?->value, $label);
            $this->assertNull($stat->url, $label);
            $this->assertSame('Mesas dirigidas', $stat->label, $label);
        }
    }

    public function test_the_campaign_counter_counts_for_anybody_the_campaigns_the_owner_runs_or_plays_in(): void
    {
        $this->directedCampaign($this->owner, $this->campaign());
        $this->directedCampaign($this->owner, $this->campaign(['status' => 'completed']));
        $this->directedCampaign($this->owner, $this->campaign(['status' => 'cancelled']));
        $director = UserModel::factory()->create();
        $played = $this->campaign();
        $this->directedCampaign($director, $played);
        $this->onRoster($this->owner, $played);
        // Run and played: counted once
        $both = $this->campaign();
        $this->directedCampaign($this->owner, $both);
        $this->onRoster($this->owner, $both);
        // Not listed: never counted
        $this->onRoster($this->owner, $this->campaign(['status' => 'cancelled']));
        $this->onRoster($this->owner, $this->campaign(['is_published' => false, 'frontend_creation_status' => 'pending_review']));

        foreach ([...$this->viewers(), 'owner' => $this->owner, 'director' => $director] as $label => $viewer) {
            $this->assertSame(4, $this->stat($this->render($viewer), 'game-tables.campaign_count')?->value, $label);
        }

        $this->assertSame('Campañas', $this->stat($this->render(null), 'game-tables.campaign_count')?->label);
    }

    public function test_anyone_sees_how_many_listed_tables_the_owner_has_played(): void
    {
        $campaign = $this->campaign();
        $this->joins($this->owner, $this->table(['starts_at' => '2026-11-10 17:00:00']));
        $this->joins($this->owner, $this->table(['status' => 'completed', 'starts_at' => '2026-10-01 17:00:00']));
        $this->joins($this->owner, $this->table(['campaign_id' => $campaign->id, 'starts_at' => '2026-11-01 17:00:00']), fromRoster: true);
        // Started already, still going on
        $this->joins($this->owner, $this->table(['starts_at' => '2026-11-15 09:00:00']));
        // Never counted
        $this->joins($this->owner, $this->table());
        $this->joins($this->owner, $this->table(['status' => 'cancelled', 'starts_at' => '2026-11-10 17:00:00']));
        $this->joins($this->owner, $this->table(['status' => 'draft', 'starts_at' => '2026-11-10 17:00:00']));
        $this->joins($this->owner, $this->table(['is_published' => false, 'starts_at' => '2026-11-10 17:00:00']));
        $this->joins($this->owner, $this->table(['starts_at' => '2026-11-10 17:00:00']), status: 'waiting_list');
        $this->joins($this->owner, $this->table(['starts_at' => '2026-11-10 17:00:00']), status: 'pending');
        $this->joins($this->owner, $this->table(['starts_at' => '2026-11-10 17:00:00']), status: 'cancelled');
        $this->joins($this->owner, $this->table(['starts_at' => '2026-11-10 17:00:00']), role: 'spectator');
        $this->directedBy($this->owner, $this->table(['starts_at' => '2026-11-10 17:00:00']));

        foreach ([...$this->viewers(), 'owner' => $this->owner] as $label => $viewer) {
            $stat = $this->stat($this->render($viewer), 'game-tables.tables_played');

            $this->assertSame(4, $stat?->value, $label);
            $this->assertNull($stat->url, $label);
            $this->assertSame('Partidas jugadas', $stat->label, $label);
        }
    }

    public function test_the_played_tables_show_only_to_the_owner_and_who_manages_them(): void
    {
        $director = UserModel::factory()->create();
        $delegating = $this->directedBy($director, $this->table(['title' => 'Delegada', 'delegates_registrations' => true, 'starts_at' => '2026-11-10 17:00:00']));
        $this->joins($this->owner, $delegating);
        $notDelegating = $this->directedBy($director, $this->table(['title' => 'Sin delegar', 'starts_at' => '2026-11-09 17:00:00']));
        $this->joins($this->owner, $notDelegating);
        $rosterDirector = UserModel::factory()->create();
        $campaign = $this->campaign();
        $this->directedCampaign($rosterDirector, $campaign);
        $this->onRoster($this->owner, $campaign);
        $session = $this->table(['title' => 'Sesión', 'campaign_id' => $campaign->id, 'starts_at' => '2026-11-08 17:00:00']);
        $this->joins($this->owner, $session, fromRoster: true);
        $other = UserModel::factory()->create();
        $this->joins($this->owner, $this->directedBy($other, $this->table(['delegates_registrations' => false, 'starts_at' => '2026-11-07 17:00:00'])));

        $this->assertCount(4, $this->playedIds($this->owner) ?? []);
        $this->assertSame([$delegating->id], $this->playedIds($director));
        $this->assertSame([$session->id], $this->playedIds($rosterDirector));

        foreach ([...$this->viewers(), 'other director' => $other] as $label => $viewer) {
            $this->assertNull($this->playedIds($viewer), $label);
            // The total names no table, so anybody sees it
            $this->assertSame(4, $this->stat($this->render($viewer), 'game-tables.tables_played')?->value, $label);
        }
    }

    public function test_a_manager_who_must_verify_their_email_sees_no_played_tables(): void
    {
        $settings = $this->app->make(SettingsServiceInterface::class);
        $settings->set('email_verification_enforced_since', '2026-01-01T00:00:00+00:00');
        $settings->set('auth_email_verification_required', '1');
        $director = UserModel::factory()->unverified()->create();
        $this->joins($this->owner, $this->directedBy($director, $this->table(['delegates_registrations' => true, 'starts_at' => '2026-11-10 17:00:00'])));

        $this->assertNull($this->section($this->render($director), 'game-tables.played-tables'));

        $director->forceFill(['email_verified_at' => now()])->save();
        $this->assertCount(1, $this->section($this->render($director->refresh()), 'game-tables.played-tables')->props['tables'] ?? []);
    }

    public function test_the_played_tables_list_the_five_latest_newest_first(): void
    {
        foreach ([7, 3, 9, 1, 5, 2, 8] as $day) {
            $this->joins($this->owner, $this->table(['title' => "Día {$day}", 'starts_at' => sprintf('2026-11-%02d 17:00:00', $day)]));
        }
        $this->joins($this->owner, $this->table(['title' => 'Futura']));
        $this->joins($this->owner, $this->table(['title' => 'Cancelada', 'status' => 'cancelled', 'starts_at' => '2026-11-14 17:00:00']));
        $this->joins($this->owner, $this->table(['title' => 'Borrador', 'status' => 'draft', 'starts_at' => '2026-11-14 17:00:00']));

        $profile = $this->render($this->owner);
        $section = $this->section($profile, 'game-tables.played-tables');

        $this->assertNotNull($section);
        $this->assertContains($section, $profile->main);
        $this->assertSame('Últimas partidas', $section->label);
        $this->assertSame('components/profileSections/PlayedTablesProfileSection.vue', $section->component);
        $this->assertSame(['Día 9', 'Día 8', 'Día 7', 'Día 5', 'Día 3'], array_column($section->props['tables'], 'title'));
        $this->assertSame(['id', 'title', 'url', 'startsAt', 'gameSystem'], array_keys($section->props['tables'][0]));
        $this->assertSame(
            $this->app->make(TimezoneServiceInterface::class)->iso(CarbonImmutable::parse('2026-11-09 17:00:00', 'UTC')),
            $section->props['tables'][0]['startsAt'],
        );
        // The upcoming tables stay in «Mesas»
        $this->assertSame(['Futura'], array_column($this->section($profile, 'game-tables.tables')->props['playing'] ?? [], 'title'));
    }

    public function test_an_anonymized_owner_has_played_nothing(): void
    {
        $this->joins($this->owner, $this->table(['starts_at' => '2026-11-10 17:00:00']));
        $campaign = $this->campaign();
        $this->onRoster($this->owner, $campaign);
        $this->owner->forceFill(['anonymized_at' => now()])->save();

        $profile = $this->render($this->owner->refresh());

        $this->assertNull($this->stat($profile, 'game-tables.tables_played'));
        $this->assertNull($this->stat($profile, 'game-tables.campaign_count'));
        $this->assertNull($this->section($profile, 'game-tables.played-tables'));
    }

    public function test_the_queries_do_not_grow_with_the_number_of_tables(): void
    {
        $director = UserModel::factory()->create();
        $this->seedActivity($director, 2);
        // The site settings are read once and cached by the first render
        $this->render($director);
        $few = $this->countQueries(fn () => $this->render($director));

        $this->seedActivity($director, 8);
        $many = $this->countQueries(fn () => $this->render($director));

        $this->assertSame($few, $many);
        // Four lists and three counts of the module, each list with its game systems, and the core's two reads
        $this->assertLessThanOrEqual(self::MAX_RENDER_QUERIES, $many);
    }

    private function seedActivity(UserModel $director, int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            $this->directedBy($this->owner, $this->table());
            $this->joins($this->owner, $this->directedBy($director, $this->table(['delegates_registrations' => true])));
            $this->joins($this->owner, $this->directedBy($director, $this->table(['delegates_registrations' => true, 'starts_at' => '2026-11-10 17:00:00'])));
            $campaign = $this->campaign();
            $this->directedCampaign($this->owner, $campaign);
            $played = $this->campaign();
            $this->directedCampaign($director, $played);
            $this->onRoster($this->owner, $played);
        }
    }

    private function countQueries(callable $callback): int
    {
        // A fresh request scope: the renderer and the sections are built again
        $this->app->forgetScopedInstances();
        DB::flushQueryLog();
        DB::enableQueryLog();
        $callback();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }

    /**
     * @return array<string, UserModel|null>
     */
    private function viewers(): array
    {
        $associationMember = UserModel::factory()->create();
        $role = RoleModel::query()->firstOrCreate(['name' => 'socio'], ['display_name' => 'Socio']);
        $associationMember->roles()->syncWithoutDetaching([$role->id]);

        return [
            'guest' => null,
            'member' => UserModel::factory()->create(),
            'association member' => $associationMember,
            'moderator' => UserModel::factory()->admin()->create(),
        ];
    }

    private function render(?UserModel $viewer): ResolvedProfileDTO
    {
        $this->app->forgetScopedInstances();

        return $this->app->make(ProfileRendererInterface::class)
            ->render(new ProfileOwnerDTO($this->owner->id, 'ana', 'Ana'), $viewer);
    }

    private function section(ResolvedProfileDTO $profile, string $key): ?ResolvedProfileSectionDTO
    {
        foreach ([...$profile->main, ...$profile->aside] as $section) {
            if ($section->key === $key) {
                return $section;
            }
        }

        return null;
    }

    /**
     * @param  list<ProfileLayoutOptionDTO>  $options
     */
    private function option(array $options, string $key): ?ProfileLayoutOptionDTO
    {
        foreach ($options as $option) {
            if ($option->key === $key) {
                return $option;
            }
        }

        return null;
    }

    /**
     * The ids «Últimas partidas» lists for the viewer, or null when the section does not show.
     *
     * @return list<string>|null
     */
    private function playedIds(?UserModel $viewer): ?array
    {
        $props = $this->section($this->render($viewer), 'game-tables.played-tables')?->props;

        return $props === null ? null : array_column($props['tables'], 'id');
    }

    private function stat(ResolvedProfileDTO $profile, string $key): ?ResolvedProfileStatDTO
    {
        foreach ($profile->stats as $stat) {
            if ($stat->key === $key) {
                return $stat;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function table(array $attributes = []): GameTableModel
    {
        return GameTableModel::create([
            'id' => (string) Str::uuid(),
            'game_system_id' => $this->system->id,
            'created_by' => UserModel::factory()->create()->id,
            'title' => 'Mesa de prueba',
            'slug' => 'mesa-'.Str::lower(Str::random(8)),
            'starts_at' => '2026-11-22 17:00:00',
            'duration_minutes' => 240,
            'table_type' => 'one_shot',
            'table_format' => 'in_person',
            'status' => 'scheduled',
            'min_players' => 1,
            'max_players' => 5,
            'max_spectators' => 2,
            'language' => 'es',
            'registration_type' => 'everyone',
            'is_published' => true,
            'delegates_registrations' => false,
            ...$attributes,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function campaign(array $attributes = []): CampaignModel
    {
        return CampaignModel::create([
            'id' => (string) Str::uuid(),
            'game_system_id' => $this->system->id,
            'created_by' => UserModel::factory()->create()->id,
            'title' => 'Campaña de prueba',
            'slug' => 'campana-'.Str::lower(Str::random(8)),
            'status' => 'active',
            'is_published' => true,
            ...$attributes,
        ]);
    }

    private function gameMaster(UserModel $user, bool $namePublic): GameMasterModel
    {
        return GameMasterModel::create([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'first_name' => 'Nombre de director',
            'role' => GameMasterRole::Main,
            'notify_by_email' => false,
            'is_name_public' => $namePublic,
        ]);
    }

    private function directedBy(UserModel $user, GameTableModel $table, bool $namePublic = true): GameTableModel
    {
        $table->gameMasters()->attach($this->gameMaster($user, $namePublic)->id, ['source' => 'local', 'excluded' => false, 'sort_order' => 1]);

        return $table;
    }

    private function directedCampaign(UserModel $user, CampaignModel $campaign, bool $namePublic = true): GameMasterModel
    {
        $gm = $this->gameMaster($user, $namePublic);
        $campaign->gameMasters()->attach($gm->id, ['sort_order' => 1]);

        return $gm;
    }

    private function joins(UserModel $user, GameTableModel $table, string $status = 'confirmed', string $role = 'player', bool $fromRoster = false): void
    {
        ParticipantModel::create([
            'id' => (string) Str::uuid(),
            'game_table_id' => $table->id,
            'user_id' => $user->id,
            'role' => $role,
            'status' => $status,
            'confirmed_at' => $status === 'confirmed' ? now() : null,
            'from_roster' => $fromRoster,
        ]);
    }

    private function onRoster(UserModel $user, CampaignModel $campaign): void
    {
        DB::table('gametables_campaign_players')->insert([
            'campaign_id' => $campaign->id,
            'user_id' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
