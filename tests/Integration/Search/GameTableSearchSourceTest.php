<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Integration\Search;

use App\Application\Search\DTOs\SearchDocumentDTO;
use App\Application\Search\Services\SearchSourceRegistryInterface;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use App\Infrastructure\Search\Persistence\Eloquent\Models\SearchDocumentModel;
use DateTimeImmutable;
use Illuminate\Support\Str;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameSystemModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameTableModel;
use Modules\GameTables\Infrastructure\Search\GameTableSearchSource;
use Tests\Support\Modules\ModuleTestCase;

/**
 * Game tables in the site search: the tables of the public listing, past ones included, kept up to date
 * on save. The test queue is "sync", so the after-commit refresh runs inside the test.
 */
final class GameTableSearchSourceTest extends ModuleTestCase
{
    protected ?string $moduleName = 'game-tables';

    protected bool $autoEnableModule = true;

    private GameTableSearchSource $source;

    private GameSystemModel $system;

    protected function setUp(): void
    {
        parent::setUp();

        $this->useUtcStorage();
        $this->source = $this->app->make(GameTableSearchSource::class);
        $this->system = GameSystemModel::create(['id' => (string) Str::uuid(), 'name' => 'Sistema de ejemplo', 'slug' => Str::random(10), 'is_active' => true]);
    }

    public function test_the_registry_offers_game_tables_with_their_labels(): void
    {
        $sources = array_values(array_filter(
            $this->app->make(SearchSourceRegistryInterface::class)->indexedSources(),
            static fn ($source): bool => $source->type() === 'gametables.tables',
        ));

        $this->assertCount(1, $sources);
        $this->assertInstanceOf(GameTableSearchSource::class, $sources[0]);
        $this->assertSame('Mesas', $this->source->label());
        $this->assertSame('Mesa', $this->source->itemLabel());
        $this->assertSame('Jugada', $this->source->pastLabel());
    }

    public function test_draft_pending_rejected_and_cancelled_tables_have_no_document(): void
    {
        $hidden = [
            $this->table(['status' => 'draft']),
            $this->table(['status' => 'draft', 'is_published' => false, 'frontend_creation_status' => 'pending_review']),
            $this->table(['status' => 'draft', 'is_published' => false, 'frontend_creation_status' => 'rejected']),
            $this->table(['status' => 'cancelled']),
            $this->table(['status' => 'scheduled', 'is_published' => false]),
        ];

        foreach ($hidden as $table) {
            $this->assertNull($this->source->document($table->id), $table->status->value);
        }

        $this->assertSame([], $this->documentIds());
    }

    public function test_every_listed_table_has_a_document(): void
    {
        $listed = [
            $this->table(['status' => 'scheduled']),
            $this->table(['status' => 'full']),
            $this->table(['status' => 'in_progress', 'starts_at' => now()->subHour()]),
            $this->table(['status' => 'completed', 'starts_at' => now()->subMonth()]),
        ];
        $this->table(['status' => 'cancelled']);

        $this->assertEqualsCanonicalizing(
            array_map(static fn (GameTableModel $table): string => $table->id, $listed),
            $this->documentIds(),
        );
    }

    public function test_past_table_keeps_its_document_with_end_time(): void
    {
        $table = $this->table(['starts_at' => '2020-01-05 16:00:00', 'duration_minutes' => 240]);

        $document = $this->source->document($table->id);

        $this->assertInstanceOf(SearchDocumentDTO::class, $document);
        $this->assertEquals(new DateTimeImmutable('2020-01-05T16:00:00+00:00'), $document->startsAt);
        $this->assertEquals(new DateTimeImmutable('2020-01-05T20:00:00+00:00'), $document->endsAt);
        // Being over by date is worked out when searching: only the status sets finished
        $this->assertFalse($document->finished);
    }

    public function test_completed_table_is_finished(): void
    {
        $document = $this->source->document($this->table(['status' => 'completed', 'starts_at' => now()->subDay()])->id);

        $this->assertTrue($document?->finished);
    }

    public function test_table_document_has_system_genres_and_location(): void
    {
        $table = $this->table([
            'title' => 'La cripta olvidada',
            'synopsis' => '<p>Una cripta &amp; sus <strong>secretos</strong></p><p>bajo la ciudad</p>',
            'genres' => ['fantasy', 'horror'],
            'table_format' => 'in_person',
            'location' => 'Club del dado',
            'image_public_id' => 'gametables/cripta',
        ]);

        $document = $this->source->document($table->id);

        $this->assertInstanceOf(SearchDocumentDTO::class, $document);
        $this->assertSame($table->id, $document->id);
        $this->assertSame('La cripta olvidada', $document->title);
        $this->assertSame('Una cripta & sus secretos bajo la ciudad', $document->body);
        $this->assertSame('Sistema de ejemplo Fantasía Terror', $document->keywords);
        $this->assertSame('Sistema de ejemplo · Club del dado', $document->meta);
        $this->assertSame('gametables/cripta', $document->imagePublicId);
        $this->assertSame(route('gametables.show', $table->slug, false), $document->url);
        $this->assertStringStartsWith('/', $document->url);
    }

    public function test_meta_and_body_skip_what_the_table_lacks(): void
    {
        $document = $this->source->document($this->table(['location' => null, 'synopsis' => null, 'genres' => null])->id);

        $this->assertSame('Sistema de ejemplo', $document?->meta);
        $this->assertSame('Sistema de ejemplo', $document->keywords);
        $this->assertSame('', $document->body);
    }

    public function test_moderation_notes_are_never_indexed(): void
    {
        $table = $this->table(['notes' => 'Nota interna secreta', 'moderation_notes' => 'Motivo de moderación secreto']);

        $document = $this->source->document($table->id);
        $stored = $this->stored($table->id);

        $this->assertNotNull($document);
        $this->assertNotNull($stored);
        foreach ([json_encode(get_object_vars($document), JSON_UNESCAPED_UNICODE), json_encode($stored->toArray(), JSON_UNESCAPED_UNICODE)] as $indexed) {
            $this->assertIsString($indexed);
            $this->assertStringNotContainsString('secret', $indexed);
            $this->assertStringNotContainsString('Nota interna', $indexed);
            $this->assertStringNotContainsString('Motivo de moderación', $indexed);
        }
    }

    public function test_ids_that_are_not_uuids_or_unknown_have_no_document(): void
    {
        $this->assertNull($this->source->document('not-a-uuid'));
        $this->assertNull($this->source->document((string) Str::uuid()));
    }

    public function test_publishing_a_table_stores_its_document(): void
    {
        $table = $this->table(['title' => 'Mesa recién publicada']);

        $this->assertSame('Mesa recién publicada', $this->stored($table->id)?->title);
    }

    public function test_cancelling_a_table_forgets_it_on_save(): void
    {
        $table = $this->table();
        $this->assertNotNull($this->stored($table->id));

        $table->update(['status' => 'cancelled']);

        $this->assertNull($this->stored($table->id));
    }

    public function test_deleting_a_table_forgets_its_document(): void
    {
        $table = $this->table();
        $this->assertNotNull($this->stored($table->id));

        $table->delete();

        $this->assertNull($this->stored($table->id));
    }

    public function test_deleting_the_creator_for_good_forgets_the_tables_document(): void
    {
        // The table goes with its creator through the foreign key, without model events
        $creator = UserModel::factory()->create();
        $table = $this->table(['created_by' => $creator->id]);
        $other = $this->table();

        $creator->forceDelete();

        $this->assertNull($this->stored($table->id));
        $this->assertNotNull($this->stored($other->id));
    }

    public function test_a_soft_deleted_creator_keeps_the_tables_document(): void
    {
        $creator = UserModel::factory()->create();
        $table = $this->table(['created_by' => $creator->id]);

        $creator->delete();

        $this->assertNotNull($this->stored($table->id));
    }

    public function test_renaming_the_game_system_refreshes_its_tables(): void
    {
        $table = $this->table();

        $this->system->update(['name' => 'Sistema renombrado']);

        $this->assertStringContainsString('Sistema renombrado', (string) $this->stored($table->id)?->keywords);
        $this->assertSame('Sistema renombrado', $this->stored($table->id)?->meta);
    }

    /**
     * @return list<string>
     */
    private function documentIds(): array
    {
        $ids = [];

        foreach ($this->source->documents() as $document) {
            $ids[] = $document->id;
        }

        return $ids;
    }

    private function stored(string $id): ?SearchDocumentModel
    {
        return SearchDocumentModel::query()->where('type', 'gametables.tables')->where('source_id', $id)->first();
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
}
