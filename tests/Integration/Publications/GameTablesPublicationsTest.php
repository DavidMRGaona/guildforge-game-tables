<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Integration\Publications;

use App\Application\Publications\Enums\PublicationState;
use App\Application\Publications\Services\PublicationSourceRegistryInterface;
use App\Domain\Publications\Events\PublicationChanged;
use App\Domain\Publications\Events\PublicationRemoved;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use DateTimeImmutable;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Modules\GameTables\Domain\Events\CampaignRosterMemberLeft;
use Modules\GameTables\Domain\Repositories\CampaignRosterRepositoryInterface;
use Modules\GameTables\Domain\ValueObjects\CampaignId;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\CampaignModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameSystemModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameTableModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\ParticipantModel;
use Modules\GameTables\Infrastructure\Publications\CampaignPublicationSource;
use Modules\GameTables\Infrastructure\Publications\GameTablePublicationSource;
use Tests\Support\Modules\ModuleTestCase;

/**
 * Tables on their own and campaigns as publications (core contract): what the sources give and
 * when listeners are told. Campaign sessions are not publications.
 */
final class GameTablesPublicationsTest extends ModuleTestCase
{
    protected ?string $moduleName = 'game-tables';

    protected bool $autoEnableModule = true;

    private GameSystemModel $system;

    protected function setUp(): void
    {
        parent::setUp();

        $this->useUtcStorage();
        $this->system = GameSystemModel::create(['id' => (string) Str::uuid(), 'name' => 'Sistema de ejemplo', 'slug' => Str::random(10), 'is_active' => true]);
    }

    public function test_the_registry_offers_tables_and_campaigns(): void
    {
        $registry = $this->app->make(PublicationSourceRegistryInterface::class);

        $this->assertInstanceOf(GameTablePublicationSource::class, $registry->source('game-table'));
        $this->assertInstanceOf(CampaignPublicationSource::class, $registry->source('campaign'));
    }

    public function test_a_published_table_is_a_publication_with_its_page_system_and_registrations(): void
    {
        $table = $this->table(['title' => 'Mesa de ejemplo', 'synopsis' => '<p>Una <strong>sinopsis</strong></p>']);

        $publication = $this->tables()->find($table->id);

        $this->assertNotNull($publication);
        $this->assertSame('game-table', $publication->type);
        $this->assertSame('Mesa de ejemplo', $publication->title);
        $this->assertSame('Una sinopsis', $publication->summary);
        $this->assertSame(route('gametables.show', $table->slug), $publication->url);
        $this->assertSame(PublicationState::Active, $publication->state);
        $this->assertSame($table->starts_at->getTimestamp(), $publication->startsAt?->getTimestamp());
        $this->assertSame($table->starts_at->getTimestamp() + 240 * 60, $publication->endsAt?->getTimestamp());
        $this->assertCount(1, $publication->topics);
        $this->assertSame('game-system.'.$this->system->id, $publication->topics[0]->key);
        $this->assertSame('Sistema de ejemplo', $publication->topics[0]->label);
        $this->assertFalse($publication->isFull);
        $this->assertTrue($publication->registrationOpen);
        $this->assertSame($table->starts_at->getTimestamp(), $publication->registrationClosesAt?->getTimestamp());
    }

    public function test_a_table_with_every_seat_taken_is_full(): void
    {
        $table = $this->table(['max_players' => 1]);
        $this->participant($table, 'confirmed');

        $this->assertTrue($this->tables()->find($table->id)?->isFull);
    }

    public function test_a_table_whose_registrations_closed_is_not_open(): void
    {
        $table = $this->table(['registration_closes_at' => now()->subHour()]);

        $publication = $this->tables()->find($table->id);

        $this->assertFalse($publication?->registrationOpen);
        $this->assertSame(PublicationState::Active, $publication?->state);
    }

    public function test_cancelled_and_completed_tables_keep_their_state(): void
    {
        $cancelled = $this->table(['status' => 'cancelled']);
        $completed = $this->table(['status' => 'completed', 'starts_at' => now()->subWeek()]);

        $this->assertSame(PublicationState::Cancelled, $this->tables()->find($cancelled->id)?->state);
        $this->assertSame(PublicationState::Ended, $this->tables()->find($completed->id)?->state);
    }

    public function test_drafts_unpublished_tables_and_campaign_sessions_are_not_publications(): void
    {
        $campaign = $this->campaign();

        $this->assertNull($this->tables()->find($this->table(['status' => 'draft'])->id));
        $this->assertNull($this->tables()->find($this->table(['is_published' => false])->id));
        $this->assertNull($this->tables()->find($this->table(['campaign_id' => $campaign->id])->id));
        $this->assertNull($this->tables()->find((string) Str::uuid()));
        $this->assertNull($this->tables()->find('not-a-uuid'));
    }

    public function test_published_ids_are_the_tables_on_their_own_not_over_yet(): void
    {
        $scheduled = $this->table();
        $inPlay = $this->table(['status' => 'in_progress', 'starts_at' => now()->subHour()]);
        $cancelledUpcoming = $this->table(['status' => 'cancelled']);
        $this->table(['status' => 'cancelled', 'starts_at' => now()->subWeek()]);
        $this->table(['status' => 'completed', 'starts_at' => now()->subWeek()]);
        $this->table(['status' => 'draft']);
        $this->table(['campaign_id' => $this->campaign()->id]);

        $ids = iterator_to_array($this->tables()->publishedIds(), false);
        sort($ids);
        $expected = [$scheduled->id, $inPlay->id, $cancelledUpcoming->id];
        sort($expected);

        $this->assertSame($expected, $ids);
    }

    public function test_a_published_campaign_is_a_publication(): void
    {
        $campaign = $this->campaign(['title' => 'Campaña de ejemplo', 'description' => 'Una campaña']);

        $publication = $this->campaigns()->find($campaign->id);

        $this->assertNotNull($publication);
        $this->assertSame('campaign', $publication->type);
        $this->assertSame('Una campaña', $publication->summary);
        $this->assertSame(route('campaigns.show', $campaign->slug), $publication->url);
        $this->assertSame(PublicationState::Active, $publication->state);
        $this->assertNull($publication->startsAt);
        $this->assertSame('game-system.'.$this->system->id, $publication->topics[0]->key);
        $this->assertFalse($publication->isFull);
        $this->assertTrue($publication->registrationOpen);
    }

    public function test_a_campaign_with_a_full_roster_is_full_and_one_closed_to_players_is_not_open(): void
    {
        $full = $this->campaign(['max_players' => 1]);
        $this->app->make(CampaignRosterRepositoryInterface::class)->add(new CampaignId($full->id), UserModel::factory()->create()->id, new DateTimeImmutable);
        $closed = $this->campaign(['accepts_new_players' => false]);

        $this->assertTrue($this->campaigns()->find($full->id)?->isFull);
        $this->assertFalse($this->campaigns()->find($closed->id)?->registrationOpen);
        $this->assertSame(PublicationState::Cancelled, $this->campaigns()->find($this->campaign(['status' => 'cancelled'])->id)?->state);
        $this->assertSame(PublicationState::Ended, $this->campaigns()->find($this->campaign(['status' => 'completed'])->id)?->state);
        $this->assertNull($this->campaigns()->find($this->campaign(['is_published' => false])->id));
    }

    public function test_published_campaign_ids_leave_out_finished_and_unpublished_ones(): void
    {
        $recruiting = $this->campaign();
        $this->campaign(['status' => 'completed']);
        $this->campaign(['status' => 'cancelled']);
        $this->campaign(['is_published' => false]);

        $this->assertSame([$recruiting->id], iterator_to_array($this->campaigns()->publishedIds(), false));
    }

    public function test_saving_and_deleting_a_table_on_its_own_tells_the_listeners(): void
    {
        Event::fake([PublicationChanged::class, PublicationRemoved::class]);

        $table = $this->table();
        $table->update(['starts_at' => now()->addWeeks(2)]);
        $table->delete();

        $this->assertGreaterThanOrEqual(2, Event::dispatched(PublicationChanged::class, fn (PublicationChanged $change): bool => $change->type === 'game-table' && $change->id === $table->id)->count());
        Event::assertDispatched(PublicationRemoved::class, fn (PublicationRemoved $removal): bool => $removal->type === 'game-table' && $removal->id === $table->id);
    }

    public function test_drafts_and_campaign_sessions_say_nothing(): void
    {
        $campaign = $this->campaign();
        Event::fake([PublicationChanged::class, PublicationRemoved::class]);

        $this->table(['status' => 'draft', 'is_published' => false])->update(['title' => 'Otra']);
        $session = $this->table(['campaign_id' => $campaign->id]);
        $this->participant($session, 'confirmed');
        $session->delete();

        Event::assertNotDispatched(PublicationChanged::class);
        Event::assertNotDispatched(PublicationRemoved::class);
    }

    public function test_a_registration_tells_the_listeners_about_its_table(): void
    {
        $table = $this->table();
        Event::fake([PublicationChanged::class]);

        $participant = $this->participant($table, 'confirmed');
        $participant->delete();

        $this->assertGreaterThanOrEqual(2, Event::dispatched(PublicationChanged::class, fn (PublicationChanged $change): bool => $change->type === 'game-table' && $change->id === $table->id)->count());
    }

    public function test_campaign_saves_and_roster_changes_tell_the_listeners(): void
    {
        $campaign = $this->campaign();
        Event::fake([PublicationChanged::class, PublicationRemoved::class]);

        $campaign->update(['accepts_new_players' => false]);
        event(new CampaignRosterMemberLeft($campaign->id, (string) Str::uuid()));
        $campaign->delete();

        $this->assertGreaterThanOrEqual(2, Event::dispatched(PublicationChanged::class, fn (PublicationChanged $change): bool => $change->type === 'campaign' && $change->id === $campaign->id)->count());
        Event::assertDispatched(PublicationRemoved::class, fn (PublicationRemoved $removal): bool => $removal->type === 'campaign' && $removal->id === $campaign->id);
    }

    public function test_renaming_a_game_system_tells_the_listeners_about_its_published_records(): void
    {
        $table = $this->table();
        $campaign = $this->campaign();
        $draft = $this->table(['status' => 'draft', 'is_published' => false]);
        Event::fake([PublicationChanged::class]);

        $this->system->update(['name' => 'Sistema renombrado']);

        Event::assertNotDispatched(PublicationChanged::class, fn (PublicationChanged $change): bool => $change->id === $draft->id);
        Event::assertDispatched(PublicationChanged::class, fn (PublicationChanged $change): bool => $change->type === 'game-table' && $change->id === $table->id);
        Event::assertDispatched(PublicationChanged::class, fn (PublicationChanged $change): bool => $change->type === 'campaign' && $change->id === $campaign->id);
    }

    public function test_an_account_deleted_for_good_removes_its_published_records(): void
    {
        $creator = UserModel::factory()->create();
        $table = $this->table(['created_by' => $creator->id]);
        $campaign = $this->campaign(['created_by' => $creator->id]);
        Event::fake([PublicationRemoved::class]);

        $creator->forceDelete();

        Event::assertDispatched(PublicationRemoved::class, fn (PublicationRemoved $removal): bool => $removal->id === $table->id);
        Event::assertDispatched(PublicationRemoved::class, fn (PublicationRemoved $removal): bool => $removal->id === $campaign->id);
    }

    public function test_deleting_a_campaign_tells_the_listeners_about_its_sessions_left_on_their_own(): void
    {
        $campaign = $this->campaign();
        $session = $this->table(['campaign_id' => $campaign->id]);
        $draftSession = $this->table(['campaign_id' => $campaign->id, 'status' => 'draft', 'is_published' => false]);
        Event::fake([PublicationChanged::class, PublicationRemoved::class]);

        $campaign->delete();

        Event::assertDispatched(PublicationRemoved::class, fn (PublicationRemoved $removal): bool => $removal->type === 'campaign' && $removal->id === $campaign->id);
        Event::assertDispatched(PublicationChanged::class, fn (PublicationChanged $change): bool => $change->type === 'game-table' && $change->id === $session->id);
        Event::assertNotDispatched(PublicationChanged::class, fn (PublicationChanged $change): bool => $change->id === $draftSession->id);
        $this->assertNotNull($this->tables()->find($session->id));
    }

    public function test_an_account_deleted_for_good_frees_its_seats_and_roster_places(): void
    {
        $player = UserModel::factory()->create();
        $table = $this->table(['max_players' => 1]);
        ParticipantModel::create(['id' => (string) Str::uuid(), 'game_table_id' => $table->id, 'user_id' => $player->id, 'role' => 'player', 'status' => 'confirmed', 'confirmed_at' => now()]);
        $campaign = $this->campaign(['max_players' => 1]);
        $this->app->make(CampaignRosterRepositoryInterface::class)->add(new CampaignId($campaign->id), $player->id, new DateTimeImmutable);
        Event::fake([PublicationChanged::class, PublicationRemoved::class]);

        $player->forceDelete();

        Event::assertDispatched(PublicationChanged::class, fn (PublicationChanged $change): bool => $change->type === 'game-table' && $change->id === $table->id);
        Event::assertDispatched(PublicationChanged::class, fn (PublicationChanged $change): bool => $change->type === 'campaign' && $change->id === $campaign->id);
        Event::assertNotDispatched(PublicationRemoved::class);
    }

    private function tables(): GameTablePublicationSource
    {
        return $this->app->make(GameTablePublicationSource::class);
    }

    private function campaigns(): CampaignPublicationSource
    {
        return $this->app->make(CampaignPublicationSource::class);
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

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function campaign(array $attributes = []): CampaignModel
    {
        return CampaignModel::create(array_merge([
            'id' => (string) Str::uuid(),
            'game_system_id' => $this->system->id,
            'created_by' => UserModel::factory()->create()->id,
            'title' => 'Campaña de ejemplo',
            'slug' => 'campana-'.Str::lower(Str::random(8)),
            'status' => 'recruiting',
            'is_published' => true,
        ], $attributes));
    }

    private function participant(GameTableModel $table, string $status): ParticipantModel
    {
        return ParticipantModel::create([
            'id' => (string) Str::uuid(),
            'game_table_id' => $table->id,
            'user_id' => UserModel::factory()->create()->id,
            'role' => 'player',
            'status' => $status,
            'confirmed_at' => $status === 'confirmed' ? now() : null,
        ]);
    }
}
