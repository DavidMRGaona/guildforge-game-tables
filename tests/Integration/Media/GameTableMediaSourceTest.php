<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Integration\Media;

use App\Application\Media\Contracts\MediaUsageSourceInterface;
use App\Application\Media\Contracts\ShowsMediaPreviews;
use App\Application\Media\Services\MediaIndexerInterface;
use App\Application\Media\Services\MediaSourceRegistryInterface;
use App\Domain\Media\ValueObjects\MediaFileId;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\GameTables\Filament\Resources\GameTableResource;
use Modules\GameTables\Infrastructure\Media\GameTableMediaSource;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameSystemModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameTableModel;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\Media\ColumnSourceContractTests;
use Tests\Support\Modules\InteractsWithModulePanel;
use Tests\Support\Modules\ModuleTestCase;

/**
 * Game table images in the panel media library: the shared contract of column sources plus what is
 * specific to tables.
 */
final class GameTableMediaSourceTest extends ModuleTestCase
{
    use ColumnSourceContractTests;
    use InteractsWithModulePanel;

    protected ?string $moduleName = 'game-tables';

    protected bool $autoEnableModule = true;

    private GameSystemModel $system;

    protected function setUp(): void
    {
        parent::setUp();

        $this->useUtcStorage();
        $this->registerOnAdminPanel([GameTableResource::class]);
        $this->system = GameSystemModel::create(['id' => (string) Str::uuid(), 'name' => 'Sistema de ejemplo', 'slug' => Str::random(10), 'is_active' => true]);
    }

    protected function source(): MediaUsageSourceInterface
    {
        return $this->app->make(GameTableMediaSource::class);
    }

    protected function makeRecord(?string $path): Model
    {
        return $this->makeTable(['image_public_id' => $path]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function makeTable(array $attributes = []): GameTableModel
    {
        return GameTableModel::create([
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
            'image_public_id' => 'game-tables/2026/11/a.jpg',
            ...$attributes,
        ]);
    }

    protected function updatePermission(): string
    {
        return 'gametables:gametables.update';
    }

    public function test_declares_its_type_label_and_folders(): void
    {
        $this->assertSame('gametables.tables', $this->source()->type());
        $this->assertSame('Mesas', $this->source()->label());
        $this->assertSame(['game-tables/'], $this->source()->folders());
    }

    public function test_usage_carries_the_table_title_and_role(): void
    {
        $table = $this->makeRecord('game-tables/2026/11/a.jpg');

        $usage = $this->source()->usagesFor((string) $table->getKey())[0];

        $this->assertSame('Mesa de ejemplo', $usage->title);
        $this->assertSame('Imagen', $usage->role);
    }

    public function test_the_claimed_folder_does_not_match_a_sibling_folder(): void
    {
        $folder = $this->source()->folders()[0];

        $this->assertStringEndsWith('/', $folder);
        $this->assertFalse(str_starts_with('game-tables-old/a.jpg', $folder));
    }

    public function test_a_trashed_table_still_uses_the_file(): void
    {
        if (! in_array(SoftDeletes::class, class_uses_recursive(GameTableModel::class), true)) {
            $this->markTestSkipped('Tables have no trash: deleting one ends its use of the file.');
        }

        $table = $this->makeRecord('game-tables/2026/11/a.jpg');
        $table->delete();

        $this->assertTrue($this->source()->usesFile(new MediaFileId('game-tables/2026/11/a')));
    }

    public function test_a_deleted_table_no_longer_uses_the_file(): void
    {
        if (in_array(SoftDeletes::class, class_uses_recursive(GameTableModel::class), true)) {
            $this->markTestSkipped('Tables have a trash: see the trashed test.');
        }

        $table = $this->makeRecord('game-tables/2026/11/a.jpg');
        $this->assertTrue($this->source()->usesFile(new MediaFileId('game-tables/2026/11/a')));

        $table->delete();

        $this->assertFalse($this->source()->usesFile(new MediaFileId('game-tables/2026/11/a')));
    }

    public function test_edit_url_points_to_the_table_edit_page_for_that_user(): void
    {
        $table = $this->makeRecord('game-tables/a.jpg');
        $with = $this->editorWithPermissions(['gametables:gametables.update']);
        $without = $this->editorWithPermissions([]);

        // The person asking decides, not whoever is signed in
        $this->actingAs($with);
        $this->assertNull($this->source()->editUrl((string) $table->getKey(), $without));

        $this->actingAs($without);
        $url = $this->source()->editUrl((string) $table->getKey(), $with);
        $this->assertStringContainsString((string) $table->getAttribute('slug'), (string) $url);
        $this->assertStringEndsWith('/edit', (string) $url);
    }

    public function test_the_module_registers_the_source_once(): void
    {
        $types = [];
        foreach ($this->app->make(MediaSourceRegistryInterface::class)->sources() as $source) {
            $types[] = $source->type();
        }

        $this->assertSame(1, count(array_keys($types, 'gametables.tables', true)));
        $this->assertSame(1, count(array_keys($types, 'gametables.campaigns', true)));
    }

    public function test_shows_its_previews_in_the_media_library(): void
    {
        $this->assertInstanceOf(ShowsMediaPreviews::class, $this->source());
    }

    public function test_the_image_of_a_published_table_is_shown_with_its_title(): void
    {
        $table = $this->makeTable();

        $usage = $this->source()->usagesFor((string) $table->getKey())[0];

        $this->assertFalse($usage->hidden);
        $this->assertSame('Mesa de ejemplo', $usage->title);
    }

    public function test_a_published_table_stays_shown_once_cancelled_or_completed(): void
    {
        // Its page still opens, image included, so the library may show it too
        foreach (['cancelled', 'completed'] as $status) {
            $table = $this->makeTable(['status' => $status]);

            $this->assertFalse($this->source()->usagesFor((string) $table->getKey())[0]->hidden, $status);
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    #[DataProvider('unpublishedTables')]
    public function test_the_image_of_an_unpublished_table_is_hidden_under_a_neutral_title(array $attributes): void
    {
        $table = $this->makeTable(['title' => 'Sorpresa de fin de año', 'is_published' => false, ...$attributes]);

        $usage = $this->source()->usagesFor((string) $table->getKey())[0];

        $this->assertTrue($usage->hidden);
        $this->assertSame('Mesa sin publicar', $usage->title);
    }

    /**
     * @return array<string, array{array<string, mixed>}>
     */
    public static function unpublishedTables(): array
    {
        return [
            'draft from the panel' => [['status' => 'draft']],
            'scheduled but not published' => [['status' => 'scheduled']],
            'draft from the site' => [['status' => 'draft', 'frontend_creation_status' => 'draft']],
            'pending moderation' => [['status' => 'draft', 'frontend_creation_status' => 'pending_review']],
            'rejected' => [['status' => 'draft', 'frontend_creation_status' => 'rejected']],
        ];
    }

    public function test_publishing_or_unpublishing_a_table_refreshes_its_usages(): void
    {
        $table = $this->makeTable(['is_published' => false]);
        $indexer = $this->spy(MediaIndexerInterface::class);

        $table->update(['is_published' => true]);
        $table->update(['is_published' => false]);

        $indexer->shouldHaveReceived('refresh')->with(GameTableMediaSource::TYPE, (string) $table->getKey())->twice();
    }

    public function test_the_index_follows_the_table_when_it_is_published_and_unpublished(): void
    {
        $table = $this->makeTable(['title' => 'Sorpresa de fin de año', 'is_published' => false]);
        $this->assertSame([true, 'Mesa sin publicar'], $this->indexedUsage($table));

        $table->update(['is_published' => true]);
        $this->assertSame([false, 'Sorpresa de fin de año'], $this->indexedUsage($table));

        $table->update(['is_published' => false]);
        $this->assertSame([true, 'Mesa sin publicar'], $this->indexedUsage($table));
    }

    public function test_a_change_the_library_does_not_show_leaves_the_index_alone(): void
    {
        $table = $this->makeTable();
        $indexer = $this->spy(MediaIndexerInterface::class);

        $table->update(['notes' => 'Traed dados']);

        $indexer->shouldNotHaveReceived('refresh');
    }

    /**
     * @return array{bool, string}
     */
    private function indexedUsage(GameTableModel $table): array
    {
        $row = DB::table('media_usages')
            ->where('source_type', GameTableMediaSource::TYPE)
            ->where('record_id', (string) $table->getKey())
            ->sole();

        return [(bool) $row->is_hidden, (string) $row->title];
    }
}
