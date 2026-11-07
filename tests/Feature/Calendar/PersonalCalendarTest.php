<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Feature\Calendar;

use App\Application\Calendar\Services\PersonalCalendarServiceInterface;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\GameTables\Domain\Enums\CampaignStatus;
use Modules\GameTables\Domain\Enums\ParticipantRole;
use Modules\GameTables\Domain\Enums\ParticipantStatus;
use Modules\GameTables\Domain\Enums\TableFormat;
use Modules\GameTables\Domain\Enums\TableStatus;
use Modules\GameTables\Domain\Enums\TableType;
use Modules\GameTables\Infrastructure\Calendar\GameTableAttendanceSource;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\CampaignModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameMasterModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameSystemModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameTableModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\ParticipantModel;
use Tests\Support\Modules\ModuleTestCase;

final class PersonalCalendarTest extends ModuleTestCase
{
    protected ?string $moduleName = 'game-tables';

    protected bool $autoEnableModule = true;

    private GameSystemModel $gameSystem;

    private UserModel $creator;

    private UserModel $person;

    protected function setUp(): void
    {
        parent::setUp();

        $this->creator = $this->makeUser('creator');
        $this->person = $this->makeUser('person');
        $this->gameSystem = GameSystemModel::create([
            'id' => (string) Str::uuid(),
            'name' => 'Dungeons & Dragons',
            'slug' => 'dnd',
            'game_master_title' => 'Dungeon Master',
            'is_active' => true,
        ]);
    }

    public function test_core_personal_calendar_is_available_with_the_module_enabled(): void
    {
        $this->assertTrue(app(PersonalCalendarServiceInterface::class)->isAvailable());
    }

    public function test_confirmed_players_and_spectators_see_their_tables(): void
    {
        $asPlayer = $this->table('player');
        $asSpectator = $this->table('spectator');
        $this->participate($asPlayer, ParticipantStatus::Confirmed, ParticipantRole::Player);
        $this->participate($asSpectator, ParticipantStatus::Confirmed, ParticipantRole::Spectator);

        $this->assertEqualsCanonicalizing([$asPlayer->id, $asSpectator->id], $this->attendedIds());
    }

    public function test_unconfirmed_participation_is_left_out(): void
    {
        foreach ([
            ParticipantStatus::Pending,
            ParticipantStatus::WaitingList,
            ParticipantStatus::Cancelled,
            ParticipantStatus::Rejected,
            ParticipantStatus::NoShow,
            ParticipantStatus::Expired,
        ] as $status) {
            $this->participate($this->table($status->value), $status);
        }

        $this->assertSame([], $this->attendedIds());
    }

    public function test_other_people_and_guests_are_not_included(): void
    {
        $table = $this->table('shared');
        $this->participate($table, ParticipantStatus::Confirmed, user: $this->makeUser('other'));
        ParticipantModel::create([
            'id' => (string) Str::uuid(),
            'game_table_id' => $table->id,
            'user_id' => null,
            'first_name' => 'Guest',
            'last_name' => 'Person',
            'email' => 'guest@example.com',
            'role' => ParticipantRole::Player,
            'status' => ParticipantStatus::Confirmed,
        ]);

        $this->assertSame([], $this->attendedIds());
    }

    public function test_game_masters_see_the_tables_they_run(): void
    {
        $table = $this->table('mine');
        $this->attachGameMaster($table, $this->person);

        $this->assertSame([$table->id], $this->attendedIds());
    }

    public function test_excluded_local_game_master_is_left_out(): void
    {
        $table = $this->table('excluded');
        $this->attachGameMaster($table, $this->person, excluded: true);

        $this->assertSame([], $this->attendedIds());
    }

    public function test_campaign_game_masters_see_the_sessions_unless_excluded(): void
    {
        $campaign = $this->campaign();
        $gameMaster = $this->makeGameMaster($this->person);
        DB::table('gametables_campaign_gm')->insert([
            'campaign_id' => $campaign->id,
            'game_master_id' => $gameMaster->id,
            'sort_order' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $session = $this->table('session', ['campaign_id' => $campaign->id]);
        $skipped = $this->table('skipped', ['campaign_id' => $campaign->id]);
        DB::table('gametables_table_gm')->insert([
            'game_table_id' => $skipped->id,
            'game_master_id' => $gameMaster->id,
            'source' => 'inherited',
            'excluded' => true,
            'sort_order' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertSame([$session->id], $this->attendedIds());
    }

    public function test_roster_registrations_of_campaign_sessions_count_when_confirmed(): void
    {
        $campaign = $this->campaign();
        $confirmed = $this->table('confirmed', ['campaign_id' => $campaign->id]);
        $waiting = $this->table('waiting', ['campaign_id' => $campaign->id]);
        $this->participate($confirmed, ParticipantStatus::Confirmed, fromRoster: true);
        $this->participate($waiting, ParticipantStatus::WaitingList, fromRoster: true);

        $this->assertSame([$confirmed->id], $this->attendedIds());
    }

    public function test_only_tables_starting_inside_the_range_are_returned(): void
    {
        $inside = $this->table('inside', ['starts_at' => now()->addDays(2)]);
        $outside = $this->table('outside', ['starts_at' => now()->addDays(40)]);
        $this->participate($inside, ParticipantStatus::Confirmed);
        $this->participate($outside, ParticipantStatus::Confirmed);

        $this->assertSame([$inside->id], $this->attendedIds());
    }

    public function test_a_table_is_returned_once_even_if_the_person_is_player_and_game_master(): void
    {
        $table = $this->table('both');
        $this->participate($table, ParticipantStatus::Confirmed);
        $this->attachGameMaster($table, $this->person);

        $this->assertSame([$table->id], $this->attendedIds());
    }

    public function test_query_count_does_not_grow_with_the_number_of_tables(): void
    {
        $this->participate($this->table('first'), ParticipantStatus::Confirmed);
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->attendedIds();
        $few = count(DB::getQueryLog());
        DB::flushQueryLog();

        for ($i = 0; $i < 5; $i++) {
            $table = $this->table("more-{$i}");
            $this->participate($table, ParticipantStatus::Confirmed);
            $this->attachGameMaster($table, $this->person);
        }
        DB::flushQueryLog();
        $this->attendedIds();
        $many = count(DB::getQueryLog());

        $this->assertSame($few, $many);
    }

    public function test_personal_calendar_service_returns_only_published_tables_of_the_person(): void
    {
        $shown = $this->table('shown');
        $draft = $this->table('draft', ['is_published' => false]);
        $this->participate($shown, ParticipantStatus::Confirmed);
        $this->participate($draft, ParticipantStatus::Confirmed);

        $entries = app(PersonalCalendarServiceInterface::class)->entriesFor(
            $this->person->id,
            new DateTimeImmutable('-1 day'),
            new DateTimeImmutable('+30 days'),
        );

        $this->assertSame([$shown->id], array_map(static fn ($entry): string => $entry->id, $entries));
    }

    public function test_mine_filter_of_the_public_calendar_answers_the_persons_tables(): void
    {
        $mine = $this->table('mine-http');
        $other = $this->table('not-mine');
        $this->participate($mine, ParticipantStatus::Confirmed);

        $from = now()->subDay()->toIso8601String();
        $to = now()->addDays(30)->toIso8601String();
        $response = $this->actingAs($this->person)->getJson('/eventos/calendario?mine=1&start='.urlencode($from).'&end='.urlencode($to));

        $response->assertOk();
        $response->assertJsonFragment(['id' => $mine->id]);
        $response->assertJsonMissing(['id' => $other->id]);
    }

    /**
     * @return array<int, string>
     */
    private function attendedIds(): array
    {
        $references = app(GameTableAttendanceSource::class)->attendedEntries(
            $this->person->id,
            new DateTimeImmutable('-1 day'),
            new DateTimeImmutable('+30 days'),
        );

        foreach ($references as $reference) {
            $this->assertSame('game-table', $reference->sourceType);
        }

        return array_values(array_map(static fn ($reference): string => $reference->id, $references));
    }

    private function makeUser(string $name): UserModel
    {
        return UserModel::create([
            'id' => (string) Str::uuid(),
            'name' => $name,
            'email' => "{$name}-".Str::random(6).'@example.com',
            'password' => 'password',
        ]);
    }

    private function campaign(): CampaignModel
    {
        return CampaignModel::create([
            'id' => (string) Str::uuid(),
            'game_system_id' => $this->gameSystem->id,
            'created_by' => $this->creator->id,
            'title' => 'Campaign',
            'slug' => 'campaign-'.Str::random(6),
            'status' => CampaignStatus::Active,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function table(string $slug, array $overrides = []): GameTableModel
    {
        return GameTableModel::create(array_merge([
            'id' => (string) Str::uuid(),
            'game_system_id' => $this->gameSystem->id,
            'created_by' => $this->creator->id,
            'title' => "Table {$slug}",
            'slug' => $slug,
            'starts_at' => now()->addWeek(),
            'duration_minutes' => 180,
            'table_type' => TableType::OneShot,
            'table_format' => TableFormat::InPerson,
            'status' => TableStatus::Scheduled,
            'min_players' => 2,
            'max_players' => 6,
            'language' => 'es',
            'auto_confirm' => true,
            'is_published' => true,
            'published_at' => now(),
        ], $overrides));
    }

    private function participate(
        GameTableModel $table,
        ParticipantStatus $status,
        ParticipantRole $role = ParticipantRole::Player,
        ?UserModel $user = null,
        bool $fromRoster = false,
    ): void {
        ParticipantModel::create([
            'id' => (string) Str::uuid(),
            'game_table_id' => $table->id,
            'user_id' => ($user ?? $this->person)->id,
            'role' => $role,
            'status' => $status,
            'from_roster' => $fromRoster,
        ]);
    }

    private function makeGameMaster(UserModel $user): GameMasterModel
    {
        return GameMasterModel::create([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'role' => 'main',
        ]);
    }

    private function attachGameMaster(GameTableModel $table, UserModel $user, bool $excluded = false): void
    {
        $gameMaster = $this->makeGameMaster($user);
        DB::table('gametables_table_gm')->insert([
            'game_table_id' => $table->id,
            'game_master_id' => $gameMaster->id,
            'source' => 'local',
            'excluded' => $excluded,
            'sort_order' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
