<?php

declare(strict_types=1);

namespace Modules\GameTables\Tests\Feature\Filament;

use App\Application\Media\Contracts\ReleasesMediaThroughCoreInterface;
use App\Application\Media\Services\MediaFileReleaserInterface;
use App\Filament\Forms\Components\MediaLibraryPicker;
use App\Infrastructure\Media\Persistence\Eloquent\Models\MediaFileModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Filament\Actions\Testing\TestAction;
use Filament\Forms\Components\FileUpload;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Mockery\MockInterface;
use Modules\GameTables\Filament\Resources\CampaignResource;
use Modules\GameTables\Filament\Resources\CampaignResource\Pages\CreateCampaign;
use Modules\GameTables\Filament\Resources\CampaignResource\Pages\EditCampaign;
use Modules\GameTables\Filament\Resources\GameTableResource;
use Modules\GameTables\Filament\Resources\GameTableResource\Pages\CreateGameTable;
use Modules\GameTables\Filament\Resources\GameTableResource\Pages\EditGameTable;
use Modules\GameTables\Infrastructure\Media\CampaignMediaSource;
use Modules\GameTables\Infrastructure\Media\GameTableMediaSource;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\CampaignModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameSystemModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameTableModel;
use ReflectionProperty;
use Tests\Support\Modules\InteractsWithModulePanel;
use Tests\Support\Modules\ModuleTestCase;

/**
 * The image fields of tables and campaigns offer «Elegir de la biblioteca» (core#480): the sources are marked as
 * releasing through the core, the pages save in a transaction and a replaced file is released, never deleted.
 */
final class MediaPickerFieldsTest extends ModuleTestCase
{
    use InteractsWithModulePanel;

    protected ?string $moduleName = 'game-tables';

    protected bool $autoEnableModule = true;

    private UserModel $admin;

    private GameSystemModel $system;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('images');
        $this->registerOnAdminPanel([CampaignResource::class, GameTableResource::class]);
        $this->admin = $this->actingAsPanelAdmin();
        $this->system = GameSystemModel::create(['id' => (string) Str::uuid(), 'name' => 'Sistema de prueba', 'slug' => 'sistema-de-prueba', 'is_active' => true]);
    }

    public function test_sources_release_through_the_core(): void
    {
        $this->assertContains(ReleasesMediaThroughCoreInterface::class, class_implements(GameTableMediaSource::class));
        $this->assertContains(ReleasesMediaThroughCoreInterface::class, class_implements(CampaignMediaSource::class));
    }

    public function test_image_fields_offer_the_library(): void
    {
        $this->assertOffersLibrary(Livewire::test(CreateGameTable::class));
        $this->assertOffersLibrary(Livewire::test(EditGameTable::class, ['record' => $this->table()->getRouteKey()]));
        $this->assertOffersLibrary(Livewire::test(CreateCampaign::class));
        $this->assertOffersLibrary(Livewire::test(EditCampaign::class, ['record' => $this->campaign()->getRouteKey()]));
    }

    public function test_pages_save_inside_a_transaction(): void
    {
        foreach ([CreateGameTable::class, EditGameTable::class, CreateCampaign::class, EditCampaign::class] as $page) {
            $property = new ReflectionProperty($page, 'hasDatabaseTransactions');
            $this->assertTrue($property->getDefaultValue(), $page);
        }
    }

    public function test_a_file_chosen_from_another_source_is_saved_on_a_table(): void
    {
        $this->indexFile('campaigns/2026/11/cover');
        $table = $this->table();

        Livewire::test(EditGameTable::class, ['record' => $table->getRouteKey()])
            ->set('data.image_public_id', ['a' => 'campaigns/2026/11/cover.png'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('campaigns/2026/11/cover.png', $table->refresh()->image_public_id);
    }

    public function test_a_file_chosen_from_another_source_is_saved_on_a_campaign(): void
    {
        $this->indexFile('game-tables/2026/11/cover');
        $campaign = $this->campaign();

        Livewire::test(EditCampaign::class, ['record' => $campaign->getRouteKey()])
            ->set('data.image_public_id', ['a' => 'game-tables/2026/11/cover.png'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('game-tables/2026/11/cover.png', $campaign->refresh()->image_public_id);
    }

    public function test_a_forged_path_is_rejected(): void
    {
        $table = $this->table();
        $campaign = $this->campaign();

        Livewire::test(EditGameTable::class, ['record' => $table->getRouteKey()])
            ->set('data.image_public_id', ['a' => 'articles/2026/11/forged.png'])
            ->call('save')
            ->assertHasFormErrors(['image_public_id']);
        Livewire::test(EditCampaign::class, ['record' => $campaign->getRouteKey()])
            ->set('data.image_public_id', ['a' => 'articles/2026/11/forged.png'])
            ->call('save')
            ->assertHasFormErrors(['image_public_id']);

        $this->assertNull($table->refresh()->image_public_id);
        $this->assertNull($campaign->refresh()->image_public_id);
    }

    public function test_replacing_the_image_releases_the_old_one_through_the_core(): void
    {
        $this->indexFile('campaigns/2026/11/new');
        $table = $this->table('game-tables/2026/11/old.png');
        $this->mock(MediaFileReleaserInterface::class, function (MockInterface $releaser): void {
            $releaser->shouldReceive('release')->once()->with('game-tables/2026/11/old.png');
        });

        Livewire::test(EditGameTable::class, ['record' => $table->getRouteKey()])
            ->set('data.image_public_id', ['a' => 'campaigns/2026/11/new.png'])
            ->call('save')
            ->assertHasNoFormErrors();
    }

    /**
     * @param  Testable<Component>  $page
     */
    private function assertOffersLibrary(Testable $page): void
    {
        $component = $page->instance();
        $this->assertInstanceOf(HasSchemas::class, $component);
        $form = $component->getSchema('form');
        $this->assertInstanceOf(Schema::class, $form);

        foreach ($form->getFlatFields(withHidden: true) as $field) {
            if ($field instanceof FileUpload && $field->getName() === 'image_public_id') {
                $page->assertActionVisible(TestAction::make(MediaLibraryPicker::ACTION)->schemaComponent(Str::after((string) $field->getKey(), 'form.'), schema: 'form'));
                $this->assertTrue($field->shouldPreventFilePathTampering());

                return;
            }
        }

        $this->fail('No image field');
    }

    private function indexFile(string $fileId): void
    {
        MediaFileModel::query()->create([
            'file_id' => $fileId,
            'resource_type' => 'image',
            'folder' => Str::beforeLast($fileId, '/').'/',
            'format' => 'png',
            'bytes' => 1000,
            'width' => 800,
            'height' => 600,
            'uploaded_at' => now()->subMonth(),
            'last_seen_at' => now(),
        ]);
    }

    private function campaign(): CampaignModel
    {
        return CampaignModel::create([
            'id' => (string) Str::uuid(),
            'game_system_id' => $this->system->id,
            'created_by' => $this->admin->id,
            'title' => 'La llamada',
            'slug' => 'la-llamada',
            'status' => 'recruiting',
            'location' => 'Local de prueba',
        ]);
    }

    private function table(?string $image = null): GameTableModel
    {
        return GameTableModel::create([
            'id' => (string) Str::uuid(),
            'game_system_id' => $this->system->id,
            'created_by' => $this->admin->id,
            'title' => 'Mesa de prueba',
            'slug' => 'mesa-de-prueba',
            'starts_at' => now()->addWeek(),
            'duration_minutes' => 240,
            'table_type' => 'one_shot',
            'table_format' => 'in_person',
            'status' => 'scheduled',
            'min_players' => 2,
            'max_players' => 5,
            'language' => 'es',
            'location' => 'Local de prueba',
            'experience_level' => 'none',
            'character_creation' => 'any',
            'image_public_id' => $image,
        ]);
    }
}
