<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Feature\Http\Controllers;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\GameTables\Application\DTOs\GameTableResponseDTO;
use Modules\GameTables\Application\Services\GameTableQueryServiceInterface;
use Modules\GameTables\Application\Services\NotificationRecipientResolverInterface;
use Modules\GameTables\Domain\Enums\GameMasterRole;
use Modules\GameTables\Domain\Repositories\CampaignRosterRepositoryInterface;
use Modules\GameTables\Domain\ValueObjects\CampaignId;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\CampaignJoinRequestModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\CampaignModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameMasterModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameSystemModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameTableModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\ParticipantModel;
use Tests\Support\Modules\ModuleTestCase;

/**
 * Outside the panel, a player linked to an account goes by the account's public name, or by its account
 * name when it has none, the rule the directors follow; a guest by the name written when registering.
 * The panel, which staff alone see, keeps the account name.
 */
final class PlayerPublicNameTest extends ModuleTestCase
{
    protected ?string $moduleName = 'game-tables';

    protected bool $autoEnableModule = true;

    private GameSystemModel $system;

    private UserModel $director;

    private UserModel $ana;

    private UserModel $bob;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        app('router')->getRoutes()->refreshNameLookups();
        config()->set('modules.settings.game-tables', []);
        $this->travelTo(CarbonImmutable::parse('2026-11-02 10:00:00', 'Europe/Madrid'));

        $this->system = GameSystemModel::create(['id' => (string) Str::uuid(), 'name' => 'Sistema', 'slug' => 'sistema', 'is_active' => true]);
        $this->director = UserModel::factory()->create(['name' => 'Dana Director', 'display_name' => 'Dana Director']);
        $this->ana = UserModel::factory()->create(['name' => 'Ana Account', 'display_name' => 'Ana Reader']);
        $this->bob = UserModel::factory()->create(['name' => 'Bob Account', 'display_name' => null]);
    }

    public function test_the_registrations_page_names_players_by_their_public_name(): void
    {
        $table = $this->table();
        $this->player($table, $this->ana, 'pending');
        $this->player($table, $this->bob, 'pending');
        $this->guest($table);

        $response = $this->actingAs($this->director)->get('/mesas/mis-mesas/'.$table->slug.'/inscritos');

        $response->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('rows', 3)
                ->where('rows.0.name', 'Ana Reader')
                ->where('rows.1.name', 'Bob Account')
                ->where('rows.2.name', 'Janie Roe'));
        $this->assertStringNotContainsString('Ana Account', (string) $response->getContent());
    }

    public function test_the_roster_names_members_and_join_requests_by_their_public_name(): void
    {
        $campaign = $this->campaign();
        app(CampaignRosterRepositoryInterface::class)->add(new CampaignId($campaign->id), $this->ana->id, CarbonImmutable::parse('2026-10-01 12:00:00', 'Europe/Madrid'));
        $carla = UserModel::factory()->create(['name' => 'Carla Account', 'display_name' => 'Carla Reader']);
        CampaignJoinRequestModel::create([
            'id' => (string) Str::uuid(),
            'campaign_id' => $campaign->id,
            'user_id' => $carla->id,
            'status' => 'pending',
            'message' => null,
        ]);

        $response = $this->actingAs($this->director)->get('/campanas/mis-campanas/'.$campaign->id.'/plantilla');

        $response->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('players.0.name', 'Ana Reader')
                ->where('requests.0.user_name', 'Carla Reader'));
        $this->assertStringNotContainsString('Ana Account', (string) $response->getContent());
        $this->assertStringNotContainsString('Carla Account', (string) $response->getContent());
    }

    public function test_the_cancellation_page_names_the_registration_by_its_public_name(): void
    {
        $token = Str::random(64);
        $this->player($this->table(), $this->ana, 'confirmed', ['cancellation_token' => $token]);

        $response = $this->get('/mesas/cancelar/'.$token);

        $response->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('GameTables/CancelRegistration', shouldExist: false)
                ->where('participant.user_name', 'Ana Reader'));
        $this->assertStringNotContainsString('Ana Account', (string) $response->getContent());
    }

    public function test_the_notices_to_the_organisation_name_the_player_by_the_public_name(): void
    {
        $table = $this->table();
        $resolver = app(NotificationRecipientResolverInterface::class);

        $this->assertSame('Ana Reader', $resolver->getParticipantDisplayName($this->player($table, $this->ana, 'pending')->id));
        $this->assertSame('Bob Account', $resolver->getParticipantDisplayName($this->player($table, $this->bob, 'pending')->id));
        $this->assertSame('Janie Roe', $resolver->getParticipantDisplayName($this->guest($table)->id));
    }

    public function test_the_table_data_names_its_players_by_their_public_name(): void
    {
        $table = $this->table();
        $this->player($table, $this->ana, 'confirmed');

        $found = app(GameTableQueryServiceInterface::class)->findPublished($table->id);

        $this->assertInstanceOf(GameTableResponseDTO::class, $found);
        $this->assertSame(['Ana Reader'], array_map(static fn ($participant): string => $participant->userName, $found->participants));
    }

    public function test_the_table_data_reads_the_players_accounts_with_the_table(): void
    {
        $table = $this->table();
        $this->player($table, $this->ana, 'confirmed');
        $queries = $this->queriesToFind($table);

        foreach (range(1, 3) as $i) {
            $this->player($table, UserModel::factory()->create(['name' => "Account {$i}", 'display_name' => "Reader {$i}"]), 'confirmed');
        }

        $this->assertSame($queries, $this->queriesToFind($table));
    }

    public function test_the_panel_keeps_the_account_name(): void
    {
        $table = $this->table();
        $row = $this->player($table, $this->ana, 'confirmed')->load('user');

        $this->assertSame('Ana Account', $row->accountName());
        $this->assertSame('Ana Reader', $row->display_name);
        $this->assertSame('Janie Roe', $this->guest($table)->accountName());
    }

    private function queriesToFind(GameTableModel $table): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        app(GameTableQueryServiceInterface::class)->findPublished($table->id);
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }

    /**
     * A published table that delegates its registrations, directed by the director's account.
     */
    private function table(): GameTableModel
    {
        $table = GameTableModel::create([
            'id' => (string) Str::uuid(),
            'game_system_id' => $this->system->id,
            'created_by' => $this->director->id,
            'title' => 'La cripta olvidada',
            'slug' => 'la-cripta-olvidada-'.Str::lower(Str::random(4)),
            'starts_at' => CarbonImmutable::parse('2026-11-09 18:00:00', 'Europe/Madrid'),
            'duration_minutes' => 240,
            'table_type' => 'one_shot',
            'table_format' => 'in_person',
            'status' => 'scheduled',
            'min_players' => 1,
            'max_players' => 6,
            'max_spectators' => 0,
            'language' => 'es',
            'registration_type' => 'everyone',
            'auto_confirm' => false,
            'is_published' => true,
            'delegates_registrations' => true,
        ]);
        $gameMaster = GameMasterModel::create([
            'id' => (string) Str::uuid(),
            'user_id' => $this->director->id,
            'role' => GameMasterRole::Main,
            'notify_by_email' => true,
            'is_name_public' => true,
        ]);
        $table->gameMasters()->attach($gameMaster->id, ['source' => 'local', 'excluded' => false, 'sort_order' => 1]);

        return $table;
    }

    private function campaign(): CampaignModel
    {
        return CampaignModel::create([
            'id' => (string) Str::uuid(),
            'game_system_id' => $this->system->id,
            'created_by' => $this->director->id,
            'title' => 'Campaña de prueba',
            'slug' => 'campana-de-prueba',
            'status' => 'active',
            'frontend_creation_status' => 'approved',
            'is_published' => true,
            'accepts_new_players' => true,
            'max_players' => 5,
            'roster_access' => 'anyone',
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function player(GameTableModel $table, UserModel $user, string $status, array $attributes = []): ParticipantModel
    {
        $row = ParticipantModel::create([
            'id' => (string) Str::uuid(),
            'game_table_id' => $table->id,
            'user_id' => $user->id,
            'role' => 'player',
            'status' => $status,
            'confirmed_at' => $status === 'confirmed' ? now() : null,
            ...$attributes,
        ]);
        // Registered in order, a minute apart: the registrations page lists them by request
        ParticipantModel::query()->whereKey($row->id)->update(['created_at' => now()->addMinutes(ParticipantModel::query()->count())]);

        return $row->refresh();
    }

    private function guest(GameTableModel $table): ParticipantModel
    {
        $row = ParticipantModel::create([
            'id' => (string) Str::uuid(),
            'game_table_id' => $table->id,
            'user_id' => null,
            'first_name' => 'Janie',
            'last_name' => 'Roe',
            'email' => 'janie@example.com',
            'role' => 'player',
            'status' => 'pending',
            'email_confirmed_at' => now(),
            'cancellation_token' => Str::random(64),
        ]);
        ParticipantModel::query()->whereKey($row->id)->update(['created_at' => now()->addMinutes(ParticipantModel::query()->count())]);

        return $row->refresh();
    }
}
