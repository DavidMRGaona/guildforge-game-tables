<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Feature\Proposals;

use App\Application\Proposals\Contracts\EventProposalRequestInterface;
use App\Application\Proposals\Services\EventProposalRequestRegistryInterface;
use App\Domain\Proposals\Enums\ProposalStatus;
use App\Domain\Proposals\Exceptions\ProposalRequestRefusedException;
use App\Domain\ValueObjects\EventId;
use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\GameTables\Domain\Enums\LocationMode;
use Modules\GameTables\Domain\Enums\SchedulingMode;
use Modules\GameTables\GameTablesServiceProvider;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\EventGameTableConfigModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameSystemModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameTableModel;
use Modules\GameTables\Infrastructure\Proposals\TablesProposalRequest;
use Modules\GameTables\Infrastructure\Services\EventWithTablesQuery;
use Tests\Support\Modules\ModuleTestCase;

/**
 * The tables request of event proposals (core#581): its fields, what approving it creates, and
 * that nothing of the module offers or counts an unapproved proposal as a real event.
 */
final class TablesProposalRequestTest extends ModuleTestCase
{
    protected ?string $moduleName = 'game-tables';

    protected bool $autoEnableModule = true;

    private TablesProposalRequest $request;

    protected function setUp(): void
    {
        parent::setUp();

        app('router')->getRoutes()->refreshNameLookups();
        config()->set('modules.settings.game-tables', [
            'frontend_creation' => ['enabled' => true, 'access_level' => 'registered', 'allowed_content' => 'both'],
        ]);

        $this->request = app(TablesProposalRequest::class);
    }

    public function test_the_provider_registers_the_request(): void
    {
        $provider = new GameTablesServiceProvider($this->app);

        $this->assertSame([TablesProposalRequest::class], $provider->registerEventProposalRequests());

        $registry = app(EventProposalRequestRegistryInterface::class);
        $registry->clear();
        $registry->register(TablesProposalRequest::class, 'game-tables');
        $this->assertInstanceOf(EventProposalRequestInterface::class, $registry->request('game-tables.tables'));
    }

    public function test_it_describes_itself_in_spanish_and_english(): void
    {
        $this->assertSame('game-tables.tables', $this->request->key());
        $this->assertSame('¿Habrá mesas de rol?', $this->request->question());
        $this->assertSame('Mesas de rol', $this->request->label());
        $this->assertTrue($this->request->isAvailable());

        $this->app->setLocale('en');
        $this->assertSame('Will there be tabletop role-playing tables?', $this->request->question());
    }

    public function test_it_asks_only_for_an_optional_short_note(): void
    {
        $fields = $this->request->fields();

        $this->assertCount(1, $fields);
        $this->assertSame('note', $fields[0]->name);
        $this->assertFalse($fields[0]->required);
    }

    public function test_applying_enables_tables_with_the_defaults(): void
    {
        $event = EventModel::factory()->draft()->upcoming()->create();

        $this->request->apply(new EventId((string) $event->id), ['note' => 'Tardes de sábado']);

        $config = EventGameTableConfigModel::query()->findOrFail($event->id);
        $this->assertTrue($config->tables_enabled);
        $this->assertSame(SchedulingMode::FreeSchedule, $config->scheduling_mode);
        $this->assertSame(LocationMode::FreeChoice, $config->location_mode);
        $this->assertNull($config->fixed_location);
        $this->assertFalse($config->early_access_enabled);
    }

    public function test_applying_refuses_when_the_event_already_has_a_configuration(): void
    {
        $event = EventModel::factory()->draft()->upcoming()->create();
        EventGameTableConfigModel::query()->create(['event_id' => $event->id, 'tables_enabled' => false]);

        try {
            $this->request->apply(new EventId((string) $event->id), ['note' => null]);
            $this->fail('A configuration that exists must stop the approval');
        } catch (ProposalRequestRefusedException $exception) {
            $this->assertSame(__('game-tables::messages.proposal_request.already_configured'), $exception->getMessage());
        }

        $this->assertFalse(EventGameTableConfigModel::query()->findOrFail($event->id)->tables_enabled);
    }

    public function test_it_describes_the_values(): void
    {
        $this->assertSame('Mesas de rol', $this->request->describe(['note' => null]));
        $this->assertSame('Mesas de rol: tardes de sábado', $this->request->describe(['note' => 'tardes de sábado']));
    }

    public function test_an_unapproved_proposal_is_never_offered_nor_counted(): void
    {
        $author = UserModel::factory()->create();
        $game = GameSystemModel::create([
            'id' => (string) Str::uuid(),
            'name' => 'Sistema de prueba',
            'slug' => 'sistema-de-prueba',
            'is_active' => true,
        ]);

        $real = EventModel::factory()->published()->upcoming()->create(['title' => 'Jornadas reales']);
        $this->request->apply(new EventId((string) $real->id), ['note' => null]);
        $this->table($real, $game, $author);

        $proposals = [];
        foreach ([ProposalStatus::PendingReview, ProposalStatus::Rejected, ProposalStatus::Draft] as $status) {
            $proposal = EventModel::factory()->draft()->upcoming()->create([
                'title' => 'Propuesta '.$status->value,
                'proposal_status' => $status,
                'submitted_at' => $status === ProposalStatus::Draft ? null : now(),
            ]);
            $this->request->apply(new EventId((string) $proposal->id), ['note' => null]);
            $this->table($proposal, $game, $author);
            $proposals[] = $proposal;
        }

        // The event filter of the tables list
        $this->assertSame(
            [['id' => $real->id, 'title' => 'Jornadas reales', 'count' => 1]],
            (new EventWithTablesQuery)->getEventsWithPublishedTables(),
        );

        // The web form offers the real event only
        $this->actingAs($author)
            ->get(route('gametables.create'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('formData.events', fn (Collection $events): bool => $events->pluck('id')->all() === [$real->id]));

        // And the server refuses to link a table to a proposal, whatever its state
        foreach ($proposals as $proposal) {
            $this->actingAs($author)
                ->post(route('gametables.store'), [
                    'game_system_id' => $game->id,
                    'event_id' => $proposal->id,
                    'title' => 'Mesa en una propuesta',
                    'starts_at' => now()->addWeeks(2)->toIso8601String(),
                    'duration_minutes' => 240,
                    'table_type' => 'one_shot',
                    'table_format' => 'in_person',
                    'location' => 'Local de la asociación',
                    'min_players' => 2,
                    'max_players' => 5,
                    'language' => 'es',
                    'experience_level' => 'none',
                    'character_creation' => 'pre_generated',
                ])
                ->assertSessionHasErrors('event_id');
        }
        $this->assertFalse(GameTableModel::query()->where('title', 'Mesa en una propuesta')->exists());
    }

    private function table(EventModel $event, GameSystemModel $game, UserModel $author): void
    {
        GameTableModel::create([
            'id' => (string) Str::uuid(),
            'game_system_id' => $game->id,
            'event_id' => $event->id,
            'created_by' => $author->id,
            'title' => 'Mesa '.Str::random(6),
            'slug' => 'mesa-'.Str::lower(Str::random(8)),
            'starts_at' => now()->addWeek(),
            'duration_minutes' => 240,
            'table_type' => 'one_shot',
            'table_format' => 'in_person',
            'location' => 'Local',
            'status' => 'scheduled',
            'min_players' => 1,
            'max_players' => 5,
            'language' => 'es',
            'registration_type' => 'everyone',
            'auto_confirm' => true,
            'is_published' => true,
        ]);
    }
}
