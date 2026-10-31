<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Integration\Infrastructure\Surveys;

use App\Application\Surveys\DTOs\SurveyGuestDTO;
use App\Application\Surveys\DTOs\SurveySubjectDTO;
use App\Application\Surveys\Enums\SurveySubjectStatus;
use App\Application\Surveys\Services\SurveySubjectSourceRegistryInterface;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use DateTimeImmutable;
use Illuminate\Support\Str;
use Modules\GameTables\Domain\Enums\GameMasterRole;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\CampaignModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameMasterModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameSystemModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameTableModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\ParticipantModel;
use Modules\GameTables\Infrastructure\Surveys\GameTableSurveySubjectSource;
use Tests\Support\Modules\ModuleTestCase;

/**
 * A table on its own (not a campaign session) as something a survey can be about: its snapshot,
 * who is asked to answer and who manages its surveys.
 */
final class GameTableSurveySubjectSourceTest extends ModuleTestCase
{
    protected ?string $moduleName = 'game-tables';

    protected bool $autoEnableModule = true;

    private GameTableSurveySubjectSource $source;

    private GameSystemModel $system;

    protected function setUp(): void
    {
        parent::setUp();

        $this->useUtcStorage();
        $this->source = $this->app->make(GameTableSurveySubjectSource::class);
        $this->system = GameSystemModel::create(['id' => (string) Str::uuid(), 'name' => 'Sistema de ejemplo', 'slug' => Str::random(10), 'is_active' => true]);
    }

    public function test_the_registry_offers_game_tables(): void
    {
        $source = $this->app->make(SurveySubjectSourceRegistryInterface::class)->source('game-table');

        $this->assertInstanceOf(GameTableSurveySubjectSource::class, $source);
        $this->assertSame('game-table', $source->sourceType());
        $this->assertSame(__('game-tables::messages.surveys.source_label'), $source->sourceLabel());
    }

    public function test_find_returns_the_table_snapshot(): void
    {
        $director = UserModel::factory()->create();
        $table = $this->table(['starts_at' => '2030-01-05 16:00:00', 'duration_minutes' => 240]);
        $this->direct($table, $director);

        $subject = $this->source->find($table->id);

        $this->assertInstanceOf(SurveySubjectDTO::class, $subject);
        $this->assertSame('game-table', $subject->sourceType);
        $this->assertSame($table->id, $subject->subjectId);
        $this->assertSame('Mesa de ejemplo', $subject->title);
        $this->assertEquals(new DateTimeImmutable('2030-01-05T16:00:00+00:00'), $subject->startsAt);
        $this->assertEquals(new DateTimeImmutable('2030-01-05T20:00:00+00:00'), $subject->endsAt);
        $this->assertSame([$director->id], $subject->organizerIds);
        $this->assertSame(route('gametables.show', $table->slug), $subject->url);
        $this->assertSame('Sistema de ejemplo', $subject->attribute('game_system')?->value);
        $this->assertSame(__('game-tables::messages.enums.table_format.online'), $subject->attribute('table_format')?->value);
        $this->assertSame(__('game-tables::messages.enums.table_type.one_shot'), $subject->attribute('table_type')?->value);
        $this->assertSame(__('game-tables::messages.surveys.attributes.game_system'), $subject->attribute('game_system')->label);
        $this->assertSame(SurveySubjectStatus::Active, $subject->status);
    }

    public function test_the_status_follows_the_table(): void
    {
        $this->assertSame(SurveySubjectStatus::Active, $this->source->find($this->table(['status' => 'in_progress'])->id)?->status);
        $this->assertSame(SurveySubjectStatus::Ended, $this->source->find($this->table(['status' => 'completed'])->id)?->status);
        $this->assertSame(SurveySubjectStatus::Cancelled, $this->source->find($this->table(['status' => 'cancelled'])->id)?->status);
    }

    public function test_find_ignores_campaign_sessions_and_unknown_ids(): void
    {
        $session = $this->table(['campaign_id' => $this->campaign()->id]);

        $this->assertNull($this->source->find($session->id));
        $this->assertNull($this->source->find((string) Str::uuid()));
        $this->assertNull($this->source->find('not-a-table'));
    }

    public function test_audience_lists_confirmed_players_only(): void
    {
        $table = $this->table();
        $player = UserModel::factory()->create();
        $this->participant($table, $player, 'player', 'confirmed');
        foreach ([['spectator', 'confirmed'], ['game_master', 'confirmed'], ['co_gm', 'confirmed'], ['player', 'pending'], ['player', 'waiting_list'], ['player', 'cancelled'], ['player', 'rejected'], ['player', 'no_show']] as [$role, $status]) {
            $this->participant($table, UserModel::factory()->create(), $role, $status);
        }

        $audience = $this->source->audience($table->id);

        $this->assertSame([$player->id], $audience->userIds);
        $this->assertSame([], $audience->guests);
    }

    public function test_audience_keeps_a_director_who_played(): void
    {
        $table = $this->table();
        $director = UserModel::factory()->create();
        $this->direct($table, $director);
        $this->participant($table, $director, 'player', 'confirmed');

        $this->assertSame([$director->id], $this->source->audience($table->id)->userIds);
    }

    public function test_audience_leaves_out_anonymized_accounts(): void
    {
        $table = $this->table();
        $gone = UserModel::factory()->create(['anonymized_at' => now()]);
        $this->participant($table, $gone, 'player', 'confirmed');

        $this->assertSame([], $this->source->audience($table->id)->userIds);
    }

    public function test_audience_lists_guests_with_a_confirmed_email(): void
    {
        $table = $this->table();
        $guest = $this->guest($table, 'invitada@example.com', confirmed: true);
        $this->guest($table, 'sin-confirmar@example.com', confirmed: false);
        $this->guest($table, 'no es un correo', confirmed: true);

        $guests = $this->source->audience($table->id)->guests;

        $this->assertEquals([new SurveyGuestDTO($guest->id, 'Jane Doe', 'invitada@example.com')], $guests);
    }

    public function test_audience_is_empty_for_campaign_sessions_and_unknown_tables(): void
    {
        $session = $this->table(['campaign_id' => $this->campaign()->id]);
        $this->participant($session, UserModel::factory()->create(), 'player', 'confirmed');

        $this->assertSame(0, $this->source->audience($session->id)->count());
        $this->assertSame(0, $this->source->audience((string) Str::uuid())->count());
        $this->assertSame(0, $this->source->audience('not-a-table')->count());
    }

    public function test_only_live_directors_can_manage(): void
    {
        $table = $this->table();
        $director = UserModel::factory()->create();
        $player = UserModel::factory()->create();
        $gm = $this->direct($table, $director);
        $this->participant($table, $player, 'player', 'confirmed');

        $this->assertTrue($this->source->canManage($director->id, $table->id));
        $this->assertFalse($this->source->canManage($player->id, $table->id));
        $this->assertFalse($this->source->canManage($director->id, (string) Str::uuid()));
        $this->assertFalse($this->source->canManage($director->id, 'not-a-table'));

        $table->gameMasters()->detach($gm->id);

        $this->assertFalse($this->source->canManage($director->id, $table->id));
    }

    public function test_directors_of_a_campaign_session_do_not_manage_it_here(): void
    {
        $session = $this->table(['campaign_id' => $this->campaign()->id]);
        $director = UserModel::factory()->create();
        $this->direct($session, $director);

        $this->assertFalse($this->source->canManage($director->id, $session->id));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function table(array $attributes = []): GameTableModel
    {
        return GameTableModel::create(array_merge([
            'id' => (string) Str::uuid(),
            'game_system_id' => $this->system->id,
            'created_by' => UserModel::factory()->create()->id,
            'title' => 'Mesa de ejemplo',
            'slug' => 'mesa-'.Str::lower(Str::random(8)),
            'starts_at' => now()->addWeek(),
            'duration_minutes' => 240,
            'table_type' => 'one_shot',
            'table_format' => 'online',
            'status' => 'scheduled',
            'min_players' => 1,
            'max_players' => 5,
            'max_spectators' => 2,
            'language' => 'es',
            'registration_type' => 'everyone',
            'is_published' => true,
        ], $attributes));
    }

    private function campaign(): CampaignModel
    {
        return CampaignModel::create([
            'id' => (string) Str::uuid(),
            'game_system_id' => $this->system->id,
            'created_by' => UserModel::factory()->create()->id,
            'title' => 'Campaña de ejemplo',
            'slug' => Str::random(10),
            'status' => 'recruiting',
        ]);
    }

    private function direct(GameTableModel $table, UserModel $user): GameMasterModel
    {
        $gm = GameMasterModel::create([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'first_name' => 'John',
            'last_name' => 'Doe',
            'role' => GameMasterRole::Main,
            'notify_by_email' => true,
            'is_name_public' => true,
        ]);
        $table->gameMasters()->attach($gm->id, ['source' => 'local', 'excluded' => false, 'sort_order' => 1]);

        return $gm;
    }

    private function participant(GameTableModel $table, UserModel $user, string $role, string $status): ParticipantModel
    {
        return ParticipantModel::create([
            'id' => (string) Str::uuid(),
            'game_table_id' => $table->id,
            'user_id' => $user->id,
            'role' => $role,
            'status' => $status,
            'confirmed_at' => $status === 'confirmed' ? now() : null,
        ]);
    }

    private function guest(GameTableModel $table, string $email, bool $confirmed): ParticipantModel
    {
        return ParticipantModel::create([
            'id' => (string) Str::uuid(),
            'game_table_id' => $table->id,
            'user_id' => null,
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'email' => $email,
            'role' => 'player',
            'status' => 'confirmed',
            'confirmed_at' => now(),
            'email_confirmed_at' => $confirmed ? now() : null,
        ]);
    }
}
